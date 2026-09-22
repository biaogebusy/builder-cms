<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;
use Drupal\xinshi_ai\Exception\JobAlreadyTerminalException;

/**
 * 状态机:
 *   queued → running(markStarted)
 *   running → partial(createAsset,未达 nRequested)
 *   running/partial → succeeded(markCompleted / finalizeInterrupted)
 *   running/partial → failed(markFailed / finalizeInterrupted)
 *   queued/running/partial → cancelled(markCancelled)
 *
 * 终态只进不出:每次变更都在任务级锁内对重新加载的节点判断并保存,worker 持有的
 * 过时对象不会把 Web 端刚写下的 cancelled 覆盖回去(UB2.5)。
 */
final class JobLifecycleService implements JobLifecycleServiceInterface {

  private const TERMINAL = ['succeeded', 'failed', 'cancelled'];
  private const LOCK_PREFIX = 'xinshi_ai:image_job:';
  private const LOCK_TIMEOUT = 10.0;
  private const LOCK_WAIT = 5;
  /** Fields a transition may change; copied back into the caller's node object. */
  private const SYNCED_FIELDS = ['field_status', 'field_status_reason', 'field_error_code',
    'field_started_at', 'field_completed_at', 'field_latency_ms', 'field_n_succeeded', 'field_assets',
    'field_provider_request_id', 'field_prompt_revised', 'field_prompt', 'field_params'];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly TimeInterface $time,
    private readonly EntityRepositoryInterface $entityRepository,
    private readonly LockBackendInterface $lock,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function createQueued(array $input, AccountInterface $owner): NodeInterface {
    $prompt = (string) ($input['prompt'] ?? '');
    $params = is_array($input['params'] ?? NULL) ? $input['params'] : [];
    // Connection secrets of custom-platform jobs live in the credential vault
    // (UB2.7); the entity, its revisions and every read path stay without them.
    foreach (ImageJobCredentialVault::SECRET_PARAM_KEYS as $key) {
      unset($params[$key]);
    }
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
  public function markStarted(NodeInterface $job): bool {
    return $this->transition($job, function (NodeInterface $fresh): void {
      $fresh->set('field_status', 'running');
      $fresh->set('field_started_at', $this->time->getRequestTime());
    });
  }

  /**
   * {@inheritdoc}
   */
  public function createAsset(NodeInterface $job, array $imageMeta, MediaInterface $media, int $index): NodeInterface {
    $asset = NULL;
    $applied = $this->transition($job, function (NodeInterface $fresh) use (&$asset, $imageMeta, $media, $index): void {
      $values = [
        'type' => 'image_asset',
        'uid' => $fresh->getOwnerId(),
        'title' => sprintf('%s #%d', $fresh->label(), $index + 1),
        'field_job' => ['target_id' => $fresh->id()],
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
      $fresh->get('field_assets')->appendItem(['target_id' => $asset->id()]);
      $fresh->set('field_n_succeeded', (int) ($fresh->get('field_n_succeeded')->value ?? 0) + 1);
      if ($fresh->get('field_status')->value !== 'partial') {
        $fresh->set('field_status', 'partial');
      }
    });
    if (!$applied) {
      throw new JobAlreadyTerminalException($job->uuid(), (string) $job->get('field_status')->value);
    }
    return $asset;
  }

  /**
   * {@inheritdoc}
   */
  public function markCompleted(NodeInterface $job): bool {
    return $this->transition($job, function (NodeInterface $fresh): void {
      $succeeded = (int) ($fresh->get('field_n_succeeded')->value ?? 0);
      $fresh->set('field_status', $succeeded > 0 ? 'succeeded' : 'failed');
      $this->stampCompletion($fresh);
    });
  }

  /**
   * {@inheritdoc}
   */
  public function markFailed(NodeInterface $job, string $reason, ?string $errorCode = NULL): bool {
    return $this->transition($job, function (NodeInterface $fresh) use ($reason, $errorCode): void {
      $fresh->set('field_status', 'failed');
      $fresh->set('field_status_reason', mb_substr($reason, 0, 512));
      if ($errorCode !== NULL) {
        $fresh->set('field_error_code', $errorCode);
      }
      $this->stampCompletion($fresh);
    });
  }

  /**
   * {@inheritdoc}
   */
  public function markCancelled(NodeInterface $job): bool {
    return $this->transition($job, function (NodeInterface $fresh): void {
      $fresh->set('field_status', 'cancelled');
      $this->stampCompletion($fresh);
    });
  }

  /**
   * {@inheritdoc}
   */
  public function finalizeInterrupted(NodeInterface $job, string $reason, string $errorCode): bool {
    return $this->transition($job, function (NodeInterface $fresh) use ($reason, $errorCode): void {
      $succeeded = (int) ($fresh->get('field_n_succeeded')->value ?? 0);
      $fresh->set('field_status', $succeeded > 0 ? 'succeeded' : 'failed');
      $fresh->set('field_status_reason', mb_substr($reason, 0, 512));
      $fresh->set('field_error_code', $errorCode);
      $this->stampCompletion($fresh);
    });
  }

  /**
   * {@inheritdoc}
   */
  public function recordProviderMeta(NodeInterface $job, ?string $requestId, ?string $revisedPrompt): void {
    if (($requestId === NULL || $requestId === '') && ($revisedPrompt === NULL || $revisedPrompt === '')) {
      return;
    }
    $this->locked($job, function () use ($job, $requestId, $revisedPrompt): void {
      $fresh = $this->reload($job);
      if ($fresh === NULL) {
        return;
      }
      if ($requestId !== NULL && $requestId !== '') {
        $fresh->set('field_provider_request_id', mb_substr($requestId, 0, 255));
      }
      if ($revisedPrompt !== NULL && $revisedPrompt !== '') {
        $fresh->set('field_prompt_revised', $revisedPrompt);
      }
      $fresh->save();
      $this->sync($job, $fresh);
    });
  }

  /**
   * {@inheritdoc}
   */
  public function recordPromptRewrite(NodeInterface $job, string $prompt, string $originalPrompt): bool {
    return $this->transition($job, function (NodeInterface $fresh) use ($prompt, $originalPrompt): void {
      $params = json_decode((string) ($fresh->get('field_params')->value ?? ''), TRUE) ?: [];
      $params['originalPrompt'] = $originalPrompt;
      $fresh->set('field_prompt', $prompt);
      $fresh->set('field_params', json_encode($params, JSON_UNESCAPED_UNICODE));
    });
  }

  /**
   * {@inheritdoc}
   */
  public function isTerminal(string $jobUuid): bool {
    $job = $this->loadJobByUuid($jobUuid);
    $fresh = $job === NULL ? NULL : $this->reload($job);
    return $fresh !== NULL && in_array($fresh->get('field_status')->value, self::TERMINAL, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function refreshStatus(NodeInterface $job): ?string {
    $fresh = $this->reload($job);
    if ($fresh === NULL) {
      return NULL;
    }
    $this->sync($job, $fresh);
    return (string) $fresh->get('field_status')->value;
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
   * Applies a state change to the stored job unless it already ended.
   *
   * @return bool
   *   TRUE when the change was saved; FALSE when the stored job is terminal
   *   (or gone), in which case nothing was written.
   */
  private function transition(NodeInterface $job, callable $change): bool {
    return $this->locked($job, function () use ($job, $change): bool {
      $fresh = $this->reload($job);
      if ($fresh === NULL) {
        return FALSE;
      }
      if (in_array($fresh->get('field_status')->value, self::TERMINAL, TRUE)) {
        $this->sync($job, $fresh);
        return FALSE;
      }
      $change($fresh);
      $fresh->save();
      $this->sync($job, $fresh);
      return TRUE;
    });
  }

  /**
   * Runs a callback under the per-job lock shared by workers and web requests.
   */
  private function locked(NodeInterface $job, callable $callback): mixed {
    $name = self::LOCK_PREFIX . $job->uuid();
    while (!$this->lock->acquire($name, self::LOCK_TIMEOUT)) {
      if ($this->lock->wait($name, self::LOCK_WAIT)) {
        throw new \RuntimeException(sprintf('Image job %s is locked by another status change.', $job->uuid()));
      }
    }
    try {
      return $callback();
    }
    finally {
      $this->lock->release($name);
    }
  }

  /**
   * Loads the job again from storage, bypassing the entity caches.
   */
  private function reload(NodeInterface $job): ?NodeInterface {
    $storage = $this->nodeStorage();
    $storage->resetCache([$job->id()]);
    $fresh = $storage->load($job->id());
    return $fresh instanceof NodeInterface ? $fresh : NULL;
  }

  /**
   * Copies the saved state into the node object the caller keeps using.
   */
  private function sync(NodeInterface $job, NodeInterface $fresh): void {
    foreach (self::SYNCED_FIELDS as $field) {
      $job->set($field, $fresh->get($field)->getValue());
    }
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
