<?php

declare(strict_types=1);

namespace Drupal\xinshi_shooting_plan\Service;

use Drupal\Component\Uuid\Uuid;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\node\NodeInterface;
use Drupal\xinshi_ai\AiTaskManagerInterface;
use Drupal\xinshi_ai\Service\ImageJobExecutor;
use Drupal\xinshi_ai\Service\ImmediateJobRunner;
use Drupal\xinshi_ai\Service\JobLifecycleServiceInterface;
use Drupal\xinshi_ai\Service\ModelRegistryServiceInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/** Atomic shot reservation and queue delivery; model calls stay in the existing image executor. */
final class ShootingSketches {

  public const TABLE = 'xinshi_shooting_sketch';

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entities,
    private readonly AccountProxyInterface $account,
    private readonly ShootingDocumentAccess $documents,
    private readonly JobLifecycleServiceInterface $lifecycle,
    private readonly AiTaskManagerInterface $tasks,
    private readonly ModelRegistryServiceInterface $models,
    private readonly QueueFactory $queues,
    private readonly UuidInterface $uuid,
    private readonly FileUrlGeneratorInterface $urls,
    private readonly ImmediateJobRunner $runner,
    private readonly ConfigFactoryInterface $config,
    private readonly AccountSwitcherInterface $switcher,
  ) {}

  /** Returns at most one latest attempt per current shot under current source authorization. */
  public function read(string $plan_uuid): array {
    $doc = $this->documents->read($plan_uuid);
    return $this->project($this->latest($plan_uuid, array_keys(ShootingDocumentAccess::prompts($doc['draft']))));
  }

  /**
   * Reserves each attempt once, even when the response or queue acknowledgement is lost.
   *
   * A retry names its predecessor. Repeating that request finds the same successor;
   * an unresolved predecessor cannot be turned into a new billable attempt.
   */
  public function submit(string $plan_uuid, array $input): array {
    if (!$this->account->hasPermission('create xinshi_ai image job')) {
      throw new AccessDeniedHttpException();
    }
    if (array_diff(array_keys($input), ['expectedHash', 'model', 'shotId', 'previousJobId']) ||
      !is_string($input['expectedHash'] ?? NULL) || !preg_match('/^[a-f0-9]{64}$/D', $input['expectedHash']) ||
      !is_string($input['model'] ?? NULL) || strlen($input['model']) > 128 ||
      (isset($input['shotId']) && (!is_string($input['shotId']) || !preg_match('/^[a-zA-Z0-9_-]{1,64}$/D', $input['shotId']))) ||
      (isset($input['previousJobId']) && (!isset($input['shotId']) || !is_string($input['previousJobId']) || !Uuid::isValid($input['previousJobId'])))) {
      throw new UnprocessableEntityHttpException('shooting_submission_invalid');
    }
    $transaction = $this->database->startTransaction();
    try {
      // This is also the CAS writer's lock, so input cannot change during reservation.
      $doc = $this->documents->read($plan_uuid, $input['expectedHash']);
      $prompts = ShootingDocumentAccess::prompts($doc['draft']);
      $latest = $this->latest($plan_uuid, array_keys($prompts));
      if (isset($input['shotId']) && !isset($prompts[$input['shotId']])) {
        throw new UnprocessableEntityHttpException('shooting_shot_invalid');
      }
      $model = $this->models->getModel($input['model']);
      $platforms = $this->models->getPlatforms();
      $platform = $model['platform'] ?? '';
      $platform_row = array_values(array_filter($platforms, static fn(array $row): bool => ($row['id'] ?? '') === $platform));
      if (!$model || !in_array('image', $model['capabilities'] ?? [], TRUE) ||
        !$platform_row || !empty($platform_row[0]['user_supplied'])) {
        throw new UnprocessableEntityHttpException('shooting_image_model_unavailable');
      }
      $task = $this->tasks->getByKind('text_to_image');
      $size = self::size($model['sizes'] ?? [], $doc['draft']['aspectRatio']);
      foreach ($prompts as $shot_id => $prompt) {
        $shot_id = (string) $shot_id;
        if (isset($input['shotId']) && $input['shotId'] !== $shot_id) {
          continue;
        }
        // Automatic continuation fills missing shots only; visual edits require explicit action.
        if (!isset($input['shotId']) && isset($latest[$shot_id])) {
          continue;
        }
        $predecessor = $input['previousJobId'] ?? '';
        $key = hash('sha256', json_encode([$this->account->id(), $plan_uuid, $shot_id,
          $prompt, $model['id'], $predecessor]));
        $existing = $this->database->select(self::TABLE, 's')->fields('s', ['id'])
          ->condition('request_key', $key)->execute()->fetchField();
        if ($existing) {
          continue;
        }
        if ($predecessor !== '') {
          $previous = $this->database->select(self::TABLE, 's')->fields('s')
            ->condition('job_uuid', $predecessor)->condition('uid', $this->account->id())
            ->condition('plan_uuid', $plan_uuid)->condition('shot_id', $shot_id)->execute()->fetchAssoc();
          $job = $previous ? $this->lifecycle->loadJobByUuid($predecessor) : NULL;
          if (!$previous || !$job ||
            ($latest[$shot_id]['job_uuid'] ?? NULL) !== $predecessor) {
            throw new ConflictHttpException('shooting_retry_not_allowed');
          }
          $this->requireFields($job, ['field_status', 'field_error_code']);
          if (!in_array($job->get('field_status')->value, ['succeeded', 'failed', 'cancelled'], TRUE) ||
            $job->get('field_error_code')->value === ImageJobExecutor::RESULT_UNKNOWN) {
            throw new ConflictHttpException('shooting_attempt_unresolved');
          }
        }
        elseif (isset($latest[$shot_id])) {
          throw new ConflictHttpException('shooting_predecessor_required');
        }
        $task_id = $this->uuid->generate();
        $job_input = ['jobKind' => 'text_to_image', 'platform' => $platform, 'model' => $model['id'],
          'prompt' => $prompt, 'taskId' => $task_id, 'nRequested' => 1,
          'params' => ['n' => 1, 'promptExtend' => FALSE] + ($size ? ['size' => $size] : [])];
        if ($task->validate($job_input, $this->account)) {
          throw new UnprocessableEntityHttpException('shooting_image_input_invalid');
        }
        $job = $this->lifecycle->createQueued($job_input, $this->account);
        $this->database->insert(self::TABLE)->fields([
          'request_key' => $key, 'uid' => (int) $this->account->id(), 'plan_uuid' => $plan_uuid,
          'shot_id' => $shot_id, 'job_uuid' => $job->uuid(),
          'task_id' => $task_id, 'prompt' => $prompt, 'model' => $model['id'], 'queue_pending' => 1,
        ])->execute();
      }
      unset($transaction);
    }
    catch (\Throwable $error) {
      if (isset($transaction)) {
        $transaction->rollBack();
        unset($transaction);
      }
      $this->entities->getStorage('node')->resetCache();
      throw $error;
    }
    $this->dispatch($plan_uuid);
    return $this->read($plan_uuid);
  }

  /**
   * Delivers a bounded outbox batch; a crash after enqueue can only redeliver the same job.
   */
  public function dispatch(?string $plan_uuid = NULL): void {
    $query = $this->database->select(self::TABLE, 's')->fields('s', ['id', 'job_uuid'])
      ->condition('queue_pending', 1)->orderBy('id')->range(0, 100);
    if ($plan_uuid !== NULL) {
      $query->condition('plan_uuid', $plan_uuid)->condition('uid', $this->account->id());
    }
    foreach ($query->execute()->fetchAll() as $row) {
      $item = $this->queues->get(ImageJobExecutor::QUEUE)->createItem([
        'jobUuid' => $row->job_uuid, 'taskId' => 'image_generation',
      ]);
      if ($item === FALSE) {
        throw new \RuntimeException('Shooting sketch queue is unavailable.');
      }
      $this->database->update(self::TABLE)->fields(['queue_pending' => 0])
        ->condition('id', $row->id)->execute();
    }
    $worker = (string) ($this->config->get('xinshi_ai.settings')->get('queue.worker') ?? 'cron');
    if ($worker === 'cron' || $worker === 'immediate') {
      $this->runner->schedule();
    }
  }

  /**
   * Rechecks a queued source under its stored owner; unrelated image jobs are unaffected.
   *
   * This only narrows an earlier authorized submission. No browser or model supplies the owner.
   */
  public function admits(NodeInterface $job): bool {
    $row = $this->database->select(self::TABLE, 's')->fields('s')
      ->condition('job_uuid', $job->uuid())->execute()->fetchAssoc();
    if (!$row) {
      return TRUE;
    }
    $users = $this->entities->getStorage('user');
    $users->resetCache([$row['uid']]);
    $owner = $users->load($row['uid']);
    if (!$owner || $owner->isBlocked() || (string) $job->getOwnerId() !== (string) $row['uid'] ||
      !$owner->hasPermission('create xinshi_ai image job')) {
      return FALSE;
    }
    $this->switcher->switchTo($owner);
    try {
      $this->documents->read($row['plan_uuid']);
      return $job->access('view', $owner);
    }
    catch (\Throwable) {
      return FALSE;
    }
    finally {
      $this->switcher->switchBack();
    }
  }

  /** Selects a supported size closest to the storyboard ratio, without inventing model values. */
  public static function size(array $sizes, string $ratio): ?string {
    [$width, $height] = array_map('intval', explode(':', $ratio));
    $target = $width / $height;
    $best = NULL;
    $distance = INF;
    foreach ($sizes as $size) {
      if (is_string($size) && preg_match('/^(\d+)x(\d+)$/D', $size, $match) && (int) $match[2] > 0) {
        $difference = abs((int) $match[1] / (int) $match[2] - $target);
        if ($difference < $distance) {
          $best = $size;
          $distance = $difference;
        }
      }
    }
    return $best ?? (is_string($sizes[0] ?? NULL) ? $sizes[0] : NULL);
  }

  private function latest(string $plan_uuid, array $shots): array {
    $query = $this->database->select(self::TABLE, 's')->condition('uid', $this->account->id())
      ->condition('plan_uuid', $plan_uuid)->condition('shot_id', $shots, 'IN')->groupBy('shot_id');
    $query->addExpression('MAX(id)', 'latest');
    $ids = $query->execute()->fetchCol();
    if (!$ids) {
      return [];
    }
    $rows = $this->database->select(self::TABLE, 's')->fields('s')->condition('id', $ids, 'IN')->execute()->fetchAll();
    $result = [];
    foreach ($rows as $row) {
      $result[$row->shot_id] = (array) $row;
    }
    return $result;
  }

  private function retryable(NodeInterface $job): bool {
    return $job->get('field_status')->value === 'failed' &&
      $job->get('field_error_code')->value !== ImageJobExecutor::RESULT_UNKNOWN;
  }

  private function requireFields(EntityInterface $entity, array $fields): void {
    if (!$entity->access('view', $this->account)) {
      throw new AccessDeniedHttpException();
    }
    foreach ($fields as $field) {
      if (!$entity->hasField($field) || !$entity->get($field)->access('view', $this->account)) {
        throw new AccessDeniedHttpException();
      }
    }
  }

  private function project(array $rows): array {
    if (!$rows) {
      return [];
    }
    $storage = $this->entities->getStorage('node');
    // Internal association lookup is followed by current entity and field access on every result.
    $ids = $storage->getQuery()->accessCheck(FALSE)->condition('type', 'image_job')
      ->condition('uuid', array_column($rows, 'job_uuid'), 'IN')->range(0, 24)->execute();
    $storage->resetCache($ids);
    $jobs = [];
    $asset_ids = [];
    foreach ($storage->loadMultiple($ids) as $job) {
      if ((string) $job->getOwnerId() !== (string) $this->account->id()) {
        throw new AccessDeniedHttpException();
      }
      $this->requireFields($job, ['field_status', 'field_error_code', 'field_assets']);
      $jobs[$job->uuid()] = $job;
      foreach ($job->get('field_assets')->getValue() as $ref) {
        $asset_ids[] = $ref['target_id'];
      }
    }
    $assets = $storage->loadMultiple(array_unique($asset_ids));
    $media_ids = [];
    foreach ($assets as $asset) {
      $this->requireFields($asset, ['field_asset_image', 'field_status']);
      if ($asset->bundle() === 'image_asset' && $asset->get('field_status')->value === 'succeeded' &&
        !$asset->get('field_asset_image')->isEmpty()) {
        $media_ids[] = $asset->get('field_asset_image')->target_id;
      }
    }
    $media = $this->entities->getStorage('media')->loadMultiple(array_unique($media_ids));
    $file_ids = [];
    foreach ($media as $item) {
      $this->requireFields($item, ['field_media_image']);
      if (!$item->get('field_media_image')->isEmpty()) {
        $file_ids[] = $item->get('field_media_image')->target_id;
      }
    }
    $files = $this->entities->getStorage('file')->loadMultiple(array_unique($file_ids));
    $result = [];
    foreach ($rows as $shot_id => $row) {
      $job = $jobs[$row['job_uuid']] ?? NULL;
      $status = $job ? (string) $job->get('field_status')->value : 'unknown';
      if ($job && $job->get('field_error_code')->value === ImageJobExecutor::RESULT_UNKNOWN) {
        $status = 'unknown';
      }
      $value = ['taskId' => $row['task_id'], 'jobId' => $row['job_uuid'], 'prompt' => $row['prompt'],
        'model' => $row['model'], 'status' => $status, 'retryable' => $job && $this->retryable($job)];
      foreach ($job?->get('field_assets')->getValue() ?? [] as $ref) {
        $asset = $assets[$ref['target_id']] ?? NULL;
        $item = $asset ? ($media[$asset->get('field_asset_image')->target_id] ?? NULL) : NULL;
        $file = $item ? ($files[$item->get('field_media_image')->target_id] ?? NULL) : NULL;
        if ($file && $asset->get('field_status')->value === 'succeeded') {
          $this->requireFields($file, ['uri']);
          $value['src'] = $this->urls->generateAbsoluteString($file->getFileUri());
          break;
        }
      }
      $result[$shot_id] = $value;
    }
    return $result;
  }

}
