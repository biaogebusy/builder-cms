<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai;

use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;
use Drupal\xinshi_ai\Exception\JobAlreadyTerminalException;
use Drupal\xinshi_ai\Exception\TaskExecutionException;
use Drupal\xinshi_ai\Service\ImageJobRun;
use Drupal\xinshi_ai\Service\ProviderRequestTrace;

/**
 * 图片类任务基类(文生图 / 图生图共享同一条落地管线)。
 *
 * 管线职责:markStarted → 调 Provider → 落 media + asset → markCompleted,
 * 并在每个阶段推 SSE 事件。失败时统一经 ProviderErrorMapper 归一错误码,
 * 抛 TaskExecutionException(携带错误码),由执行器决定重试 / 终态。
 *
 * 取消与完成的竞争(UB2.5):每一步状态变更都以存储中的状态为准。调用进行中被取消的
 * 任务,其迟到的图片保存为未发布 media 留作核验证据,不生成 asset、不推事件、不改变
 * cancelled;供应商用量照常记录,因为成本已经发生。
 */
abstract class AiImageTaskBase extends AiTaskBase {

  private const TERMINAL = ['succeeded', 'failed', 'cancelled'];

  /**
   * 跑完整图片管线。
   *
   * @param \Drupal\node\NodeInterface $job
   *   image_job 节点。
   * @param array $genConfig
   *   生成参数(n / size / response_format / user 等),不含连接信息。
   * @param string $operationType
   *   drupal/ai 操作类型(text_to_image / image_to_image)。
   * @param callable $invoke
   *   fn(object $provider, string $model): 输出对象(带 getNormalized/getRawOutput)。
   * @param \Drupal\xinshi_ai\Service\ImageJobRun|null $run
   *   本次执行的租约;传输层用它记录请求已发出、续租和供应商应答。
   *
   * @throws \Drupal\xinshi_ai\Exception\TaskExecutionException
   */
  protected function runImagePipeline(NodeInterface $job, array $genConfig, string $operationType, callable $invoke,
    ?ImageJobRun $run = NULL): void {
    $uuid = $job->uuid();
    if (!$this->lifecycle->markStarted($job)) {
      // Cancelled between the queue claim and the start: nothing is called.
      $this->logger->notice('image_job @job was @status before it started; the provider is not called', [
        '@job' => $uuid, '@status' => $job->get('field_status')->value,
      ]);
      return;
    }
    $this->eventStream->publish($uuid, 'status', ['status' => 'running']);

    $prompt = (string) $job->get('field_prompt')->value;
    $trace = new ProviderRequestTrace(
      $run === NULL ? NULL : $run->dispatched(...),
      $run === NULL ? NULL : $run->heartbeat(...),
    );
    $attempt = NULL;

    try {
      // The usage intent is durable before any provider I/O (UB2.4). In enforce
      // mode a failed write ends the job here, before anything can be charged.
      $attempt = $this->usageRecorder->begin([
        'operation_id' => $uuid,
        'kind' => (string) $job->get('field_job_kind')->value,
        'platform' => (string) $job->get('field_platform')->value,
        'model' => $this->effectiveModel($job),
        'actor_user_id' => (string) $job->getOwnerId(),
        'task_id' => $job->get('field_task_id')->value,
      ]);
      if ($attempt !== NULL) {
        $run?->usageAttempt($attempt->attemptId);
      }
      $provider = $this->getProvider($job, $genConfig, $operationType, $trace);
      $output = $invoke($provider, $this->effectiveModel($job));

      // The provider answered: the supplier cost exists whatever happens to
      // the images below, so the lease records it before anything else.
      if ($run !== NULL && !$run->responded()) {
        $this->logger->warning('Execution lease of image job @job was lost during the provider call; another worker may have closed the job', [
          '@job' => $uuid,
        ]);
      }
      $raw = $output->getRawOutput();
      // Observed from the raw body before any image is saved or dropped: the
      // supplier charged for this response even if persistence fails below.
      $attempt?->succeeded($raw, $trace);
      $this->captureProviderMeta($job, $raw, $trace->requestId());

      /** @var \Drupal\ai\OperationType\GenericType\ImageFile[] $images */
      $images = $output->getNormalized();
      $late = 0;
      foreach ($images as $i => $image) {
        $index = (int) $i;
        // A cancel may land at any moment while the call runs; the stored
        // status decides, before each image becomes visible.
        $open = $this->isOpen($job);
        $media = $this->mediaUpload->fromImageFile($image, (int) $job->getOwnerId(), $prompt, $open);
        $attempt?->persisted($index, $media->uuid());
        if (!$open) {
          $late++;
          continue;
        }
        try {
          $asset = $this->lifecycle->createAsset($job, $this->imageMeta($raw, $index), $media, $index);
        }
        catch (JobAlreadyTerminalException) {
          // Cancelled between the status check and the commit.
          $media->setUnpublished();
          $media->save();
          $late++;
          continue;
        }
        $attempt?->committed($index, $asset->uuid());
        $this->eventStream->publish($uuid, 'asset_ready', $this->serializeAsset($asset, $media));
      }
      if ($late > 0) {
        $this->logger->warning('image_job @job was @status when @count of its images arrived; they are kept as unpublished media and not delivered', [
          '@job' => $uuid, '@status' => $job->get('field_status')->value, '@count' => $late,
        ]);
      }

      if ($this->lifecycle->markCompleted($job)) {
        $this->eventStream->publish($uuid, 'completed', [
          'nSucceeded' => (int) $job->get('field_n_succeeded')->value,
          'completedAt' => (int) $job->get('field_completed_at')->value,
        ]);
      }
    }
    catch (\Throwable $e) {
      $code = $this->errorMapper->mapToCode($e);
      // A failure after the provider answered keeps the succeeded observation:
      // the cost exists whether or not the images were saved.
      if ($attempt !== NULL && !$attempt->isObserved()) {
        $attempt->failed($code, $trace);
      }
      // 不在此 markFailed:重试 vs 终态由执行器决定。携带归一码抛出。
      throw new TaskExecutionException($e->getMessage(), $code, $e);
    }
  }

  /**
   * 取消:软终态;上游不可中断,运行中的调用返回后按存储状态处理其结果。
   */
  public function cancel(NodeInterface $job): void {
    if ($this->lifecycle->markCancelled($job)) {
      $this->eventStream->publish($job->uuid(), 'status', ['status' => 'cancelled']);
    }
  }

  /**
   * 从 job 的 field_params 抽取生成参数白名单(透传给上游)。
   */
  protected function buildGenConfig(NodeInterface $job, array $keys): array {
    $params = json_decode((string) ($job->get('field_params')->value ?? ''), TRUE) ?: [];
    $n = (int) ($job->get('field_n_requested')->value ?: ($params['n'] ?? $this->pluginDefinition['defaultN']));
    $config = ['n' => $n, 'user' => (string) $job->getOwnerId()];
    foreach ($keys as $k) {
      if (isset($params[$k])) {
        $config[$k] = $params[$k];
      }
    }
    // 归一化 size 格式:前端可能传 1328*1328,但 OpenAI Images API 标准是 1024x1024(小写 x)。
    if (isset($config['size']) && is_string($config['size'])) {
      $config['size'] = str_replace('*', 'x', $config['size']);
    }
    return $config;
  }

  /**
   * 单张 asset 的元数据(OpenAI 图片响应通常只有 revised_prompt / seed)。
   */
  protected function imageMeta(mixed $raw, int $index): array {
    $meta = [];
    $data = is_array($raw) ? ($raw['data'][$index] ?? []) : [];
    if (!empty($data['revised_prompt'])) {
      $meta['meta']['revised_prompt'] = $data['revised_prompt'];
    }
    if (isset($data['seed'])) {
      $meta['seed'] = $data['seed'];
    }
    return $meta;
  }

  /**
   * 把 provider request id / revised prompt 回填到 job(观测字段)。
   *
   * Images API 响应通常没有顶层 id;没有时退回网关响应头里的 request id。
   */
  protected function captureProviderMeta(NodeInterface $job, mixed $raw, ?string $headerRequestId = NULL): void {
    if (!is_array($raw)) {
      return;
    }
    $requestId = !empty($raw['id']) ? (string) $raw['id'] : $headerRequestId;
    $revised = $raw['data'][0]['revised_prompt'] ?? NULL;
    $this->lifecycle->recordProviderMeta($job, $requestId, is_string($revised) ? $revised : NULL);
  }

  /**
   * SSE 推给前端的 asset 摘要。
   */
  protected function serializeAsset(NodeInterface $asset, MediaInterface $media): array {
    $url = NULL;
    $file = $media->get('field_media_image')->entity;
    if ($file) {
      // 根相对路径(TRUE),避免 CLI 下回退 http://default。
      $url = $file->createFileUrl(TRUE);
    }
    return [
      'assetUuid' => $asset->uuid(),
      'index' => (int) $asset->get('field_index')->value,
      'status' => $asset->get('field_status')->value,
      'mediaUuid' => $media->uuid(),
      'url' => $url,
    ];
  }

  /**
   * Whether the stored job may still receive assets.
   */
  private function isOpen(NodeInterface $job): bool {
    $status = $this->lifecycle->refreshStatus($job);
    return $status !== NULL && !in_array($status, self::TERMINAL, TRUE);
  }

}
