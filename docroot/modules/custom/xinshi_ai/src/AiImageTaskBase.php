<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai;

use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;
use Drupal\xinshi_ai\Exception\TaskExecutionException;
use Drupal\xinshi_ai\Service\ProviderRequestTrace;

/**
 * 图片类任务基类(文生图 / 图生图共享同一条落地管线)。
 *
 * 管线职责:markStarted → 调 Provider → 落 media + asset → markCompleted,
 * 并在每个阶段推 SSE 事件。失败时统一经 ProviderErrorMapper 归一错误码,
 * markFailed 后抛 TaskExecutionException(携带错误码),由 worker 决定重试 / 终态。
 */
abstract class AiImageTaskBase extends AiTaskBase {

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
   *
   * @throws \Drupal\xinshi_ai\Exception\TaskExecutionException
   */
  protected function runImagePipeline(NodeInterface $job, array $genConfig, string $operationType, callable $invoke): void {
    $uuid = $job->uuid();
    $this->lifecycle->markStarted($job);
    $this->eventStream->publish($uuid, 'status', ['status' => 'running']);

    $prompt = (string) $job->get('field_prompt')->value;
    $trace = new ProviderRequestTrace();
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
      $provider = $this->getProvider($job, $genConfig, $operationType, $trace);
      $output = $invoke($provider, $this->effectiveModel($job));

      $raw = $output->getRawOutput();
      // Observed from the raw body before any image is saved or dropped: the
      // supplier charged for this response even if persistence fails below.
      $attempt?->succeeded($raw, $trace);
      $this->captureProviderMeta($job, $raw, $trace->requestId());

      /** @var \Drupal\ai\OperationType\GenericType\ImageFile[] $images */
      $images = $output->getNormalized();
      foreach ($images as $i => $image) {
        $media = $this->mediaUpload->fromImageFile($image, (int) $job->getOwnerId(), $prompt);
        $attempt?->persisted((int) $i, $media->uuid());
        $asset = $this->lifecycle->createAsset($job, $this->imageMeta($raw, (int) $i), $media);
        $attempt?->committed((int) $i, $asset->uuid());
        $this->eventStream->publish($uuid, 'asset_ready', $this->serializeAsset($asset, $media));
      }

      $this->lifecycle->markCompleted($job);
      $this->eventStream->publish($uuid, 'completed', [
        'nSucceeded' => (int) $job->get('field_n_succeeded')->value,
        'completedAt' => (int) $job->get('field_completed_at')->value,
      ]);
    }
    catch (\Throwable $e) {
      $code = $this->errorMapper->mapToCode($e);
      // A failure after the provider answered keeps the succeeded observation:
      // the cost exists whether or not the images were saved.
      if ($attempt !== NULL && !$attempt->isObserved()) {
        $attempt->failed($code, $trace);
      }
      // 不在此 markFailed:重试 vs 终态由 worker 决定。携带归一码抛出。
      throw new TaskExecutionException($e->getMessage(), $code, $e);
    }
  }

  /**
   * 取消:软终态;上游不可中断,后续 asset_ready 在 worker 处因 job 终态被丢弃。
   */
  public function cancel(NodeInterface $job): void {
    $this->lifecycle->markCancelled($job);
    $this->eventStream->publish($job->uuid(), 'status', ['status' => 'cancelled']);
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
    if ($requestId !== NULL && $requestId !== '') {
      $job->set('field_provider_request_id', mb_substr($requestId, 0, 255));
    }
    $revised = $raw['data'][0]['revised_prompt'] ?? NULL;
    if ($revised) {
      $job->set('field_prompt_revised', $revised);
    }
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

}
