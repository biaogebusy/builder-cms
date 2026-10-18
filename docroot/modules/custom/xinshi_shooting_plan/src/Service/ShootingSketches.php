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
use Drupal\xinshi_ai\Service\ImageJobCredentialVault;
use Drupal\xinshi_ai\Service\PublicUrlPolicy;
use Drupal\xinshi_ai\Exception\ImageSafetyException;
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
    private readonly ImageJobCredentialVault $credentials,
    private readonly PublicUrlPolicy $urlPolicy,
    private readonly \Drupal\xinshi_ai\Service\InputImageAccess $inputImages,
  ) {}

  /** Returns at most one latest attempt per current shot under current source authorization. */
  public function read(string $plan_uuid): array {
    $doc = $this->documents->read($plan_uuid);
    $prompts = ShootingDocumentAccess::prompts($doc);
    $result = $this->project($this->latest($plan_uuid, array_keys($prompts)));
    foreach ($result as $id => &$sketch) {
      $sketch['stale'] = ($sketch['inputKey'] ?? '') !== ShootingVisuals::key($prompts[$id], $doc, (string) $id);
    }
    return $result;
  }

  /** Author polling wakes existing queued work after the previous claim window elapsed. */
  public function poll(string $plan_uuid): array {
    $sketches = $this->read($plan_uuid);
    if (in_array('queued', array_column($sketches, 'status'), TRUE)) {
      $worker = (string) ($this->config->get('xinshi_ai.settings')->get('queue.worker') ?? 'cron');
      if ($worker === 'cron' || $worker === 'immediate') {
        // No reservation or redelivery: the existing consumer still claims each queued item.
        $this->runner->schedule();
      }
    }
    return $sketches;
  }

  /** Resolves an authorized local file for the revocable share route, never a remote URL. */
  public function image(string $plan_uuid, string $shot_id): ?\Drupal\file\FileInterface {
    $doc = $this->documents->read($plan_uuid);
    if (!isset(ShootingDocumentAccess::prompts($doc)[$shot_id])) {
      return NULL;
    }
    return $this->project($this->latest($plan_uuid, [$shot_id]), TRUE)[$shot_id] ?? NULL;
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
    if (array_diff(array_keys($input), ['expectedHash', 'model', 'shotId', 'previousJobId', 'platform', 'size', 'credentials']) ||
      !is_string($input['expectedHash'] ?? NULL) || !preg_match('/^[a-f0-9]{64}$/D', $input['expectedHash']) ||
      !is_string($input['model'] ?? NULL) || trim($input['model']) === '' || strlen($input['model']) > 128 ||
      (isset($input['shotId']) && (!is_string($input['shotId']) || !preg_match('/^[a-zA-Z0-9_-]{1,64}$/D', $input['shotId']))) ||
      (isset($input['previousJobId']) && (!isset($input['shotId']) || !is_string($input['previousJobId']) || !Uuid::isValid($input['previousJobId'])))) {
      throw new UnprocessableEntityHttpException('shooting_submission_invalid');
    }
    $custom = ($input['platform'] ?? NULL) === 'custom';
    $secrets = NULL;
    if ($custom) {
      if (!is_array($input['credentials'] ?? NULL) ||
        array_diff(array_keys($input['credentials']), ['endpoint', 'apiKey']) ||
        !is_string($input['size'] ?? NULL) || !preg_match('/^(auto|[1-9]\d{0,4}x[1-9]\d{0,4})$/D', $input['size'])) {
        throw new UnprocessableEntityHttpException('shooting_provider_invalid');
      }
      $params = [];
      $secrets = ImageJobCredentialVault::fromPayload($input, $params);
      if (ImageJobCredentialVault::validate($secrets, 'custom') !== NULL) {
        throw new UnprocessableEntityHttpException('shooting_provider_invalid');
      }
      try {
        $this->urlPolicy->destination($secrets['endpoint']);
      }
      catch (ImageSafetyException) {
        throw new UnprocessableEntityHttpException('shooting_provider_invalid');
      }
    }
    elseif (array_intersect(array_keys($input), ['platform', 'size', 'credentials'])) {
      throw new UnprocessableEntityHttpException('shooting_provider_invalid');
    }
    $credential_jobs = [];
    $transaction = $this->database->startTransaction();
    try {
      // This is also the CAS writer's lock, so input cannot change during reservation.
      $doc = $this->documents->read($plan_uuid, $input['expectedHash']);
      $prompts = ShootingDocumentAccess::prompts($doc);
      $latest = $this->latest($plan_uuid, array_keys($prompts));
      if (isset($input['shotId']) && !isset($prompts[$input['shotId']])) {
        throw new UnprocessableEntityHttpException('shooting_shot_invalid');
      }
      $model = $custom ? ['id' => trim($input['model']), 'platform' => 'custom']
        : $this->models->getModel($input['model']);
      $platforms = $this->models->getPlatforms();
      $platform = $model['platform'] ?? '';
      $platform_row = array_values(array_filter($platforms, static fn(array $row): bool => ($row['id'] ?? '') === $platform));
      if (!$custom && (!$model || !in_array('image', $model['capabilities'] ?? [], TRUE) ||
        !$platform_row || !empty($platform_row[0]['user_supplied']))) {
        throw new UnprocessableEntityHttpException('shooting_image_model_unavailable');
      }
      $anchor = ShootingVisuals::anchor($doc);
      if (!isset($prompts[$anchor])) {
        throw new UnprocessableEntityHttpException('shooting_anchor_invalid');
      }
      $confirmed = NULL;
      if (isset($doc['visuals']['confirmedJobId'])) {
        $candidate = $this->project(isset($latest[$anchor]) ? [$anchor => $latest[$anchor]] : [])[$anchor] ?? NULL;
        if ($candidate && $candidate['jobId'] === $doc['visuals']['confirmedJobId'] &&
          $candidate['status'] === 'succeeded' && isset($candidate['mediaId']) &&
          ($candidate['inputKey'] ?? '') === ShootingVisuals::key($prompts[$anchor], $doc, $anchor)) {
          $confirmed = $candidate;
        }
      }
      if (!$confirmed && isset($input['shotId']) && $input['shotId'] !== $anchor) {
        throw new ConflictHttpException('shooting_anchor_required');
      }
      $anchor_sources = [];
      if ($confirmed) {
        $anchor_job = $this->lifecycle->loadJobByUuid($confirmed['jobId']);
        $anchor_params = json_decode((string) $anchor_job->get('field_params')->value, TRUE) ?: [];
        foreach ($anchor_job->get('field_input_image')->referencedEntities() as $index => $media) {
          $anchor_sources[] = [$media->uuid(), $anchor_params['inputVersions'][$index]];
        }
      }
      $size = $custom ? $input['size'] : self::size($model['sizes'] ?? [], $doc['draft']['aspectRatio']);
      foreach ($prompts as $shot_id => $prompt) {
        $shot_id = (string) $shot_id;
        if (isset($input['shotId']) && $input['shotId'] !== $shot_id) {
          continue;
        }
        if (!$confirmed && $shot_id !== $anchor) {
          continue;
        }
        // Automatic continuation fills missing shots only; visual edits require explicit action.
        if (!isset($input['shotId']) && isset($latest[$shot_id])) {
          continue;
        }
        $input_key = ShootingVisuals::key($prompt, $doc, $shot_id);
        $references = ShootingVisuals::references($doc['visuals'] ?? []);
        foreach ($references as $reference) {
          $media = $this->inputImages->forSubmission($reference['mediaId'], $this->account);
          if ($media->get('field_media_image')->entity->uuid() !== $reference['fileId']) {
            throw new ConflictHttpException('shooting_reference_changed');
          }
        }
        $images = array_column($references, 'mediaId');
        if ($confirmed && $shot_id !== $anchor) {
          $number = array_search($confirmed['mediaId'], $images, TRUE);
          if ($number === FALSE) {
            $images[] = $confirmed['mediaId'];
            $number = count($images) - 1;
          }
          $number++;
          $prompt .= "\nReference {$number} is the approved continuity frame. Preserve its identities, " .
            "clothing, product geometry and location. Compose this shot from its own camera and action directions.";
        }
        $images = array_values(array_unique($images));
        $kind = $images ? 'image_edit' : 'text_to_image';
        if (!$custom && $images && !in_array('image-edit', $model['capabilities'] ?? [], TRUE)) {
          throw new UnprocessableEntityHttpException('shooting_reference_model_required');
        }
        $task = $this->tasks->getByKind($kind);
        $predecessor = $input['previousJobId'] ?? '';
        $identity = [$this->account->id(), $plan_uuid, $shot_id, $input_key, $model['id'], $predecessor];
        if ($custom) {
          $identity[] = ['custom', $secrets['endpoint'], $size];
        }
        $key = hash('sha256', json_encode($identity));
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
        $job_input = ['jobKind' => $kind, 'platform' => $platform, 'model' => $model['id'],
          'prompt' => $prompt, 'taskId' => $task_id, 'nRequested' => 1,
          'params' => ['n' => 1, 'promptExtend' => FALSE, 'shootingInput' => $input_key] + ($size ? ['size' => $size] : [])];
        if ($confirmed && $shot_id !== $anchor) {
          $job_input['params']['shootingAnchorSources'] = $anchor_sources;
        }
        if ($images) {
          $job_input['inputImage'] = $images;
        }
        if ($task->validate($job_input, $this->account)) {
          throw new UnprocessableEntityHttpException('shooting_image_input_invalid');
        }
        $job = $this->lifecycle->createQueued($job_input, $this->account);
        if ($custom) {
          $credential_jobs[] = $job->uuid();
          $this->credentials->store($job->uuid(), $secrets['endpoint'], $secrets['api_key']);
        }
        $this->database->insert(self::TABLE)->fields([
          'request_key' => $key, 'uid' => (int) $this->account->id(), 'plan_uuid' => $plan_uuid,
          'shot_id' => $shot_id, 'job_uuid' => $job->uuid(),
          'task_id' => $task_id, 'prompt' => $prompts[$shot_id], 'model' => $model['id'], 'queue_pending' => 1,
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
      foreach ($credential_jobs as $job_uuid) {
        $this->credentials->delete($job_uuid);
      }
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
      $this->requireAnchorSources($job);
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

  /** Anchor provenance remains an authorization dependency, not an extra model image. */
  private function requireAnchorSources(NodeInterface $job): void {
    $this->requireFields($job, ['field_params']);
    $params = json_decode((string) $job->get('field_params')->value, TRUE) ?: [];
    foreach ($params['shootingAnchorSources'] ?? [] as [$uuid, $version]) {
      $media = $this->inputImages->forSubmission($uuid, $this->account);
      if ($media->get('field_media_image')->entity->uuid() !== $version) {
        throw new AccessDeniedHttpException('shooting_reference_changed');
      }
    }
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

  private function project(array $rows, bool $files_only = FALSE): array {
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
    $unavailable = [];
    foreach ($storage->loadMultiple($ids) as $job) {
      if ((string) $job->getOwnerId() !== (string) $this->account->id()) {
        throw new AccessDeniedHttpException();
      }
      $this->requireFields($job, ['field_status', 'field_error_code', 'field_assets', 'field_params']);
      if ($job->hasField('field_input_image') && !$job->get('field_input_image')->isEmpty()) {
        try {
          $this->requireAnchorSources($job);
          $this->requireFields($job, ['field_input_image']);
          $params = json_decode((string) $job->get('field_params')->value, TRUE) ?: [];
          foreach ($job->get('field_input_image')->getValue() as $index => $ref) {
            $storage_media = $this->entities->getStorage('media');
            $storage_media->resetCache([$ref['target_id']]);
            $reference = $storage_media->load($ref['target_id']);
            $source = $this->inputImages->forSubmission($reference?->uuid(), $this->account);
            if (isset($params['inputVersions']) &&
              $source->get('field_media_image')->entity->uuid() !== ($params['inputVersions'][$index] ?? NULL)) {
              throw new AccessDeniedHttpException('shooting_reference_changed');
            }
          }
        }
        catch (\Drupal\xinshi_ai\Exception\InputImageAccessException | AccessDeniedHttpException) {
          // Keep the owned attempt recoverable while withholding results from unavailable sources.
          $unavailable[$job->uuid()] = TRUE;
        }
      }
      $jobs[$job->uuid()] = $job;
      foreach (isset($unavailable[$job->uuid()]) ? [] : $job->get('field_assets')->getValue() as $ref) {
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
        'inputKey' => $job ? (json_decode((string) $job->get('field_params')->value, TRUE)['shootingInput'] ?? '') : '',
        'model' => $row['model'], 'status' => $status, 'retryable' => $job && $this->retryable($job)];
      $value['sourceUnavailable'] = isset($unavailable[$row['job_uuid']]);
      foreach ($value['sourceUnavailable'] ? [] : ($job?->get('field_assets')->getValue() ?? []) as $ref) {
        $asset = $assets[$ref['target_id']] ?? NULL;
        $item = $asset ? ($media[$asset->get('field_asset_image')->target_id] ?? NULL) : NULL;
        $file = $item ? ($files[$item->get('field_media_image')->target_id] ?? NULL) : NULL;
        if ($file && $asset->get('field_status')->value === 'succeeded') {
          $this->requireFields($file, ['uri']);
          if ($files_only && $status === 'succeeded') {
            $result[$shot_id] = $file;
          }
          $value['mediaId'] = $item->uuid();
          $value['src'] = $this->urls->generateAbsoluteString($file->getFileUri());
          break;
        }
      }
      if (!$files_only) {
        $result[$shot_id] = $value;
      }
    }
    return $result;
  }

}
