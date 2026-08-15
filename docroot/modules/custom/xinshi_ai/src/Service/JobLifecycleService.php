<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;

/**
 * 状态机:
 *   queued → running(markStarted)
 *   running → partial(createAsset,未达 nRequested)
 *   running/partial → succeeded(markCompleted)
 *   running/partial → failed(markFailed)
 *   queued/running/partial → cancelled(markCancelled)
 */
final class JobLifecycleService implements JobLifecycleServiceInterface {

  private const TERMINAL = ['succeeded', 'failed', 'cancelled'];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly TimeInterface $time,
    private readonly EntityRepositoryInterface $entityRepository,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function createQueued(array $input, AccountInterface $owner): NodeInterface {
    $prompt = (string) ($input['prompt'] ?? '');
    $params = $input['params'] ?? [];
    $nRequested = (int) ($input['nRequested'] ?? ($params['n'] ?? 4));

    $values = [
      'type' => 'image_job',
      'uid' => $owner->id(),
      'title' => $this->buildTitle($prompt),
      'field_job_kind' => $input['jobKind'] ?? 'text_to_image',
      'field_platform' => $input['platform'] ?? NULL,
      'field_model' => $input['model'] ?? NULL,
      'field_prompt' => $prompt,
      'field_params' => json_encode($params, JSON_UNESCAPED_UNICODE),
      'field_n_requested' => $nRequested,
      'field_n_succeeded' => 0,
      'field_status' => 'queued',
    ];
    if (!empty($input['negativePrompt'])) {
      $values['field_negative_prompt'] = $input['negativePrompt'];
    }
    if (!empty($input['inputImage'])) {
      // 前端只持有 media uuid(JSON:API 规范);entity_reference 的 target_id 需要 numeric
      // entity id,这里做 uuid → id 解析。无效 uuid 直接跳过写入,后续 worker 会因
      // field_input_image 为空抛 image_edit job 缺少 field_input_image,行为可观察。
      $media = $this->entityRepository->loadEntityByUuid('media', (string) $input['inputImage']);
      if ($media) {
        $values['field_input_image'] = ['target_id' => $media->id()];
      }
    }
    if (!empty($input['parentJob'])) {
      $parent = $this->entityRepository->loadEntityByUuid('node', (string) $input['parentJob']);
      if ($parent) {
        $values['field_parent_job'] = ['target_id' => $parent->id()];
      }
    }
    if (!empty($input['taskId'])) {
      $values['field_task_id'] = $input['taskId'];
    }

    $job = $this->nodeStorage()->create($values);
    $job->save();
    assert($job instanceof NodeInterface);
    return $job;
  }

  /**
   * {@inheritdoc}
   */
  public function markStarted(NodeInterface $job): void {
    $job->set('field_status', 'running');
    $job->set('field_started_at', $this->time->getRequestTime());
    $job->save();
  }

  /**
   * {@inheritdoc}
   */
  public function createAsset(NodeInterface $job, array $imageMeta, MediaInterface $media): NodeInterface {
    $index = (int) ($job->get('field_n_succeeded')->value ?? 0);

    $values = [
      'type' => 'image_asset',
      'uid' => $job->getOwnerId(),
      'title' => sprintf('%s #%d', $job->label(), $index + 1),
      'field_job' => ['target_id' => $job->id()],
      'field_index' => $index,
      'field_asset_image' => ['target_id' => $media->id()],
      'field_status' => 'succeeded',
      'field_starred' => FALSE,
    ];
    if (isset($imageMeta['seed'])) {
      $values['field_seed'] = (string) $imageMeta['seed'];
    }
    if (isset($imageMeta['width'])) {
      $values['field_width'] = (int) $imageMeta['width'];
    }
    if (isset($imageMeta['height'])) {
      $values['field_height'] = (int) $imageMeta['height'];
    }
    if (!empty($imageMeta['meta'])) {
      $values['field_meta'] = json_encode($imageMeta['meta'], JSON_UNESCAPED_UNICODE);
    }

    $asset = $this->nodeStorage()->create($values);
    $asset->save();
    assert($asset instanceof NodeInterface);

    // 回填 job:追加 asset 引用、+1 成功数、推进 partial。
    $job->get('field_assets')->appendItem(['target_id' => $asset->id()]);
    $job->set('field_n_succeeded', $index + 1);
    if ($job->get('field_status')->value !== 'partial') {
      $job->set('field_status', 'partial');
    }
    $job->save();

    return $asset;
  }

  /**
   * {@inheritdoc}
   */
  public function markCompleted(NodeInterface $job): void {
    $succeeded = (int) ($job->get('field_n_succeeded')->value ?? 0);
    $job->set('field_status', $succeeded > 0 ? 'succeeded' : 'failed');
    $this->stampCompletion($job);
    $job->save();
  }

  /**
   * {@inheritdoc}
   */
  public function markFailed(NodeInterface $job, string $reason, ?string $errorCode = NULL): void {
    $job->set('field_status', 'failed');
    $job->set('field_status_reason', mb_substr($reason, 0, 512));
    if ($errorCode !== NULL) {
      $job->set('field_error_code', $errorCode);
    }
    $this->stampCompletion($job);
    $job->save();
  }

  /**
   * {@inheritdoc}
   */
  public function markCancelled(NodeInterface $job): void {
    $job->set('field_status', 'cancelled');
    $this->stampCompletion($job);
    $job->save();
  }

  /**
   * {@inheritdoc}
   */
  public function isTerminal(string $jobUuid): bool {
    $job = $this->loadJobByUuid($jobUuid);
    return $job !== NULL && in_array($job->get('field_status')->value, self::TERMINAL, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function loadJobByUuid(string $jobUuid): ?NodeInterface {
    $nodes = $this->nodeStorage()->loadByProperties([
      'type' => 'image_job',
      'uuid' => $jobUuid,
    ]);
    $node = $nodes ? reset($nodes) : NULL;
    return $node instanceof NodeInterface ? $node : NULL;
  }

  /**
   * 写 completed_at + latency_ms。
   */
  private function stampCompletion(NodeInterface $job): void {
    $now = $this->time->getRequestTime();
    $job->set('field_completed_at', $now);
    $started = (int) ($job->get('field_started_at')->value ?? 0);
    if ($started > 0) {
      $job->set('field_latency_ms', ($now - $started) * 1000);
    }
  }

  private function buildTitle(string $prompt): string {
    $title = trim(mb_substr($prompt, 0, 80));
    return $title !== '' ? $title : 'Image job';
  }

  private function nodeStorage() {
    return $this->entityTypeManager->getStorage('node');
  }

}
