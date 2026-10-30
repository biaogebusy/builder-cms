<?php

declare(strict_types=1);

namespace Drupal\Tests\xinshi_shooting_plan\Kernel;

use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\media\Entity\MediaType;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\xinshi_ai\AiTaskInterface;
use Drupal\xinshi_ai\AiTaskManagerInterface;
use Drupal\xinshi_ai\Service\ImageJobExecutor;
use Drupal\xinshi_ai\Service\ImageJobCredentialVault;
use Drupal\xinshi_ai\Service\PublicUrlPolicy;
use Drupal\xinshi_ai\Service\ImmediateJobRunner;
use Drupal\xinshi_ai\Service\InputImageAccess;
use Drupal\xinshi_ai\Service\JobLifecycleService;
use Drupal\xinshi_ai\Service\ModelRegistryServiceInterface;
use Drupal\xinshi_shooting_plan\Controller\ShootingSketchController;
use Drupal\xinshi_shooting_plan\Controller\ShootingDocumentController;
use Drupal\xinshi_shooting_plan\Service\ShootingDocumentAccess;
use Drupal\xinshi_shooting_plan\Service\ShootingSketches;
use Drupal\xinshi_shooting_plan\Service\ShootingSharing;
use Drupal\xinshi_shooting_plan\Controller\ShootingSharingController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Yaml\Yaml;

/** Real Drupal nodes, JSON:API aliases, field access, transactions and queue with no model calls. */
final class ShootingSketchesTest extends TestCase {

  private ShootingSketches $sketches;
  private ShootingDocumentAccess $documents;
  private JobLifecycleService $lifecycle;
  private Node $plan;
  private User $owner;
  private array $doc;
  private bool $queueFails = FALSE;
  private int $validations = 0;
  private int $failValidationAt = 0;
  private bool $customModel = FALSE;
  private ImageJobCredentialVault $credentials;
  private ImmediateJobRunner $runner;

  public static function setUpBeforeClass(): void {
    require dirname(__DIR__, 2) . '/install-fixture.php';
    foreach (['conversation', 'ai_session', 'image_job', 'image_asset'] as $type) {
      NodeType::create(['type' => $type, 'name' => $type])->save();
    }
    foreach (['job_kind', 'platform', 'model', 'status', 'error_code', 'task_id', 'provider_request_id'] as $field) {
      self::field('node', 'image_job', 'field_' . $field, 'string');
    }
    foreach (['prompt', 'params', 'status_reason', 'prompt_revised'] as $field) {
      self::field('node', 'image_job', 'field_' . $field, 'string_long');
    }
    foreach (['n_requested', 'n_succeeded', 'latency_ms'] as $field) {
      self::field('node', 'image_job', 'field_' . $field, 'integer');
    }
    foreach (['started_at', 'completed_at'] as $field) {
      self::field('node', 'image_job', 'field_' . $field, 'timestamp');
    }
    self::field('node', 'image_job', 'field_input_image', 'entity_reference', ['target_type' => 'media'], 8);
    self::field('node', 'image_job', 'field_assets', 'entity_reference', ['target_type' => 'node'], -1);
    self::field('node', 'image_asset', 'field_status', 'string');
    self::field('node', 'image_asset', 'field_asset_image', 'entity_reference', ['target_type' => 'media']);
    MediaType::create(['id' => 'image', 'label' => 'Image', 'source' => 'image',
      'source_configuration' => ['source_field' => 'field_media_image']])->save();
    self::field('media', 'image', 'field_media_image', 'image', ['target_type' => 'file']);
    Role::create(['id' => 'shooting_owner', 'label' => 'Shooting owner', 'permissions' => [
      'access content', 'view own unpublished content', 'view media', 'create xinshi_ai image job',
      'create shooting_plan content', 'edit own shooting_plan content',
    ]])->save();
  }

  protected function setUp(): void {
    \Drupal::state()->delete('shooting.denied');
    \Drupal::state()->delete('shooting.fail_save');
    $this->owner = User::create(['name' => 'owner-' . \Drupal::service('uuid')->generate(),
      'status' => 1, 'roles' => ['shooting_owner']]);
    $this->owner->save();
    \Drupal::currentUser()->setAccount($this->owner);
    $this->plan = Node::create(['type' => 'shooting_plan', 'title' => 'Synthetic plan',
      'uid' => $this->owner->id()]);
    $this->doc = ['kind' => 'xinshi-shooting-plan', 'version' => 1, 'id' => $this->plan->uuid(),
      'taskId' => \Drupal::service('uuid')->generate(), 'runId' => \Drupal::service('uuid')->generate(),
      'request' => ['topic' => 'Synthetic plan', 'style' => 'pencil'], 'original' => [], 'status' => 'ready', 'draft' => [
        'aspectRatio' => '9:16', 'styleGuide' => 'Same presenter in a quiet studio.',
        'shots' => [['id' => 'shot_1', 'framing' => 'wide', 'visual' => 'Presenter at a desk',
          'camera' => 'Phone at eye level, one metre away.', 'lighting' => 'Window to the left.',
          'sketchPrompt' => 'Camera at eye level'], ['id' => 'shot_2', 'framing' => 'detail',
          'camera' => 'Overhead phone on a fixed support.', 'lighting' => 'Window to the left.',
          'visual' => 'Two sample objects', 'sketchPrompt' => 'Overhead close view']],
      ]];
    $this->saveDoc();
    $db = \Drupal::database();
    $entities = \Drupal::entityTypeManager();
    $account = \Drupal::currentUser();
    $inputImages = new InputImageAccess($entities, \Drupal::service('entity.repository'),
      \Drupal::moduleHandler(), \Drupal::service('account_switcher'),
      \Drupal::service('stream_wrapper_manager'), new \Drupal\xinshi_ai\Service\ImageFileValidator());
    $this->documents = new ShootingDocumentAccess($db, $entities, $account,
      \Drupal::service('jsonapi.resource_type.repository'), $inputImages);
    $this->lifecycle = new JobLifecycleService($entities, \Drupal::time(),
      \Drupal::service('entity.repository'), \Drupal::lock(), $inputImages);
    $task = $this->createMock(AiTaskInterface::class);
    $task->expects($this->never())->method('execute');
    $task->method('validate')->willReturnCallback(function (array $input): array {
      $this->assertSame(1, $input['nRequested']);
      $this->assertSame($input['platform'] === 'custom' ? '1024x1536' : '768x1360', $input['params']['size']);
      $this->assertFalse($input['params']['promptExtend']);
      return ++$this->validations === $this->failValidationAt ? ['prompt' => 'Rejected'] : [];
    });
    $tasks = $this->createMock(AiTaskManagerInterface::class);
    $tasks->method('getByKind')->willReturn($task);
    $models = $this->createMock(ModelRegistryServiceInterface::class);
    $models->method('getModel')->willReturn(['id' => 'fixture', 'platform' => 'fixture',
      'capabilities' => ['image', 'image-edit'], 'sizes' => ['1024x1024', '768x1360']]);
    $models->method('getPlatforms')->willReturnCallback(fn() => [
      ['id' => 'fixture', 'user_supplied' => $this->customModel],
    ]);
    $realQueue = \Drupal::queue(ImageJobExecutor::QUEUE);
    $queue = $this->createMock(QueueInterface::class);
    $queue->method('createItem')->willReturnCallback(fn($item) => $this->queueFails ? FALSE : $realQueue->createItem($item));
    $queues = $this->createMock(QueueFactory::class);
    $queues->method('get')->willReturn($queue);
    $this->runner = new ImmediateJobRunner($queues, $this->createMock(QueueWorkerManagerInterface::class), new NullLogger());
    $this->credentials = new ImageJobCredentialVault(\Drupal::service('keyvalue.expirable'),
      \Drupal::service('private_key'));
    $urlPolicy = $this->createMock(PublicUrlPolicy::class);
    $urlPolicy->method('destination')->willReturnCallback(function (string $url): array {
      if ($url !== 'https://images.example.com/v1/images/generations') {
        throw new \Drupal\xinshi_ai\Exception\ImageSafetyException('Blocked destination');
      }
      return ['host' => 'images.example.com', 'port' => 443, 'address' => '93.184.216.34'];
    });
    $this->sketches = new ShootingSketches($db, $entities, $account, $this->documents, $this->lifecycle,
      $tasks, $models, $queues, \Drupal::service('uuid'), \Drupal::service('file_url_generator'),
      $this->runner, \Drupal::configFactory(), \Drupal::service('account_switcher'), $this->credentials, $urlPolicy, $inputImages);
  }

  public function testConfirmationGatesRemainingFramesAndPassesTheReferenceAndAnchor(): void {
    [$global, $global_file] = $this->referenceImage();
    $this->doc['request']['style'] = 'color';
    $this->doc['visuals'] = ['anchorShotId' => 'shot_1', 'reference' => [
      'mediaId' => $global->uuid(), 'fileId' => $global_file->uuid(), 'name' => 'Presenter',
    ]];
    $this->saveDoc();
    $first = $this->submit();
    $this->assertSame(['shot_1'], array_keys($first));
    $this->assertStringContainsString('colored storyboard', $first['shot_1']['prompt']);
    $this->assertStringContainsString('Reference image: Presenter', $first['shot_1']['prompt']);
    $this->expectHttp(fn() => $this->submit(['shotId' => 'shot_2']), 409);
    $anchor_job = $this->lifecycle->loadJobByUuid($first['shot_1']['jobId']);
    $this->assertSame('image_edit', $anchor_job->get('field_job_kind')->value);
    $this->assertCount(1, $anchor_job->get('field_input_image'));
    [$anchor] = $this->referenceImage();
    $asset = Node::create(['type' => 'image_asset', 'title' => 'Anchor', 'status' => 1,
      'uid' => $this->owner->id(), 'field_status' => 'succeeded',
      'field_asset_image' => ['target_id' => $anchor->id()]]);
    $asset->save();
    $anchor_job->set('field_status', 'succeeded')->set('field_assets', [['target_id' => $asset->id()]])->save();
    $this->assertCount(1, $this->submit());
    $this->doc['visuals']['confirmedJobId'] = $anchor_job->uuid();
    $this->saveDoc();
    $next = $this->submit();
    $this->assertCount(2, $next);
    $this->assertFalse($next['shot_2']['stale']);
    $job = $this->lifecycle->loadJobByUuid($next['shot_2']['jobId']);
    $this->assertSame('image_edit', $job->get('field_job_kind')->value);
    $inputs = [$global->id(), $anchor->id()];
    $this->assertSame($inputs,
      array_column($job->get('field_input_image')->getValue(), 'target_id'));
    $this->assertStringContainsString('Reference ' . count($inputs) . ' is the approved continuity frame', $job->get('field_prompt')->value);
    $this->assertSame($next, $this->submit());
    $this->assertTrue($this->sketches->admits($job));
    $job->set('field_status', 'succeeded')->set('field_assets', [['target_id' => $asset->id()]])->save();
    $this->assertArrayHasKey('src', $this->read()['shot_2']);
    $this->doc['request']['style'] = 'realistic';
    $this->saveDoc();
    $this->assertTrue($this->read()['shot_1']['stale']);
    $this->expectHttp(fn() => $this->submit(['shotId' => 'shot_2', 'previousJobId' => $job->uuid()]), 409);
    [, $replacement] = $this->referenceImage();
    $global->set('field_media_image', ['target_id' => $replacement->id(), 'alt' => 'Replacement'])->save();
    $projection = $this->read()['shot_2'];
    $this->assertTrue($projection['sourceUnavailable']);
    $this->assertArrayNotHasKey('src', $projection);
    $this->assertSame($job->uuid(), $projection['jobId']);
    $this->assertFalse($this->sketches->admits($job));
  }

  public function testReferenceVersionsAndCurrentSourceAccessAreRequired(): void {
    [$media, $file] = $this->referenceImage();
    $this->doc['visuals'] = ['reference' => [
      'mediaId' => $media->uuid(), 'fileId' => $file->uuid(), 'name' => 'Room',
    ]];
    $this->saveDoc();
    $first = $this->submit();
    $this->assertCount(1, $first);
    [, $replacement] = $this->referenceImage();
    $media->set('field_media_image', ['target_id' => $replacement->id(), 'alt' => 'Replacement'])->save();
    $this->expectHttp(fn() => $this->submit(['shotId' => 'shot_1', 'previousJobId' => $first['shot_1']['jobId']]), 409);
    $this->assertSame(1, $this->rows());
    $projection = $this->read()['shot_1'];
    $this->assertTrue($projection['sourceUnavailable']);
    $this->assertArrayNotHasKey('src', $projection);
    $this->assertSame($first['shot_1']['jobId'], $projection['jobId']);
  }

  /** Creates only synthetic local images, never a provider result. */
  private function referenceImage(): array {
    $uri = 'public://' . \Drupal::service('uuid')->generate() . '.png';
    file_put_contents($uri, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aKAAAAABJRU5ErkJggg=='));
    $file = File::create(['uri' => $uri, 'filemime' => 'image/png', 'uid' => $this->owner->id(), 'status' => 1]);
    $file->save();
    $media = Media::create(['bundle' => 'image', 'name' => 'Reference', 'status' => 1,
      'uid' => $this->owner->id(), 'field_media_image' => ['target_id' => $file->id(), 'alt' => 'Reference']]);
    $media->save();
    return [$media, $file];
  }

  public function testAuthorPollingWakesQueuedWorkWithoutReservingOrRedeliveringJobs(): void {
    $config = \Drupal::configFactory()->getEditable('xinshi_ai.settings');
    $config->set('queue.worker', 'daemon')->save();
    try {
      $first = $this->submit();
      $queue = \Drupal::queue(ImageJobExecutor::QUEUE);
      $items = $queue->numberOfItems();
      $controller = new ShootingSketchController($this->sketches);
      $controller->read($this->plan->uuid());
      $this->assertFalse($this->runner->isScheduled());
      $config->set('queue.worker', 'cron')->save();
      // Anonymous sharing uses the pure projection and cannot wake consumers.
      $this->assertSame($first, $this->sketches->read($this->plan->uuid()));
      $this->assertFalse($this->runner->isScheduled());
      $response = $controller->read($this->plan->uuid());
      $this->assertTrue($this->runner->isScheduled());
      $this->assertSame(200, $response->getStatusCode());
      $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
      $this->assertSame($first, json_decode($response->getContent(), TRUE)['sketches']);
      $controller->read($this->plan->uuid());
      $this->assertSame($items, $queue->numberOfItems());
      $this->assertSame(1, $this->rows());
      $this->assertSame(1, $this->validations);
    }
    finally {
      $config->set('queue.worker', 'daemon')->save();
    }
  }

  public function testPollingDoesNotWakeTerminalOrUnauthorizedPlans(): void {
    $config = \Drupal::configFactory()->getEditable('xinshi_ai.settings');
    $config->set('queue.worker', 'daemon')->save();
    try {
      foreach ($this->submit() as $sketch) {
        $job = $this->lifecycle->loadJobByUuid($sketch['jobId']);
        $this->lifecycle->markFailed($job, 'Unknown outcome', ImageJobExecutor::RESULT_UNKNOWN);
      }
      $config->set('queue.worker', 'cron')->save();
      $controller = new ShootingSketchController($this->sketches);
      $this->assertSame(200, $controller->read($this->plan->uuid())->getStatusCode());
      $this->assertFalse($this->runner->isScheduled());
      \Drupal::currentUser()->setAccount(new \Drupal\Core\Session\AnonymousUserSession());
      $this->assertSame(403, $controller->read($this->plan->uuid())->getStatusCode());
      $this->assertFalse($this->runner->isScheduled());
    }
    finally {
      $config->set('queue.worker', 'daemon')->save();
    }
  }

  public function testCreatesRealImageJobsAndDeduplicatesLostSubmissionAcknowledgements(): void {
    $first = $this->submit();
    $this->assertCount(1, $first);
    $this->assertSame($first, $this->submit());
    $this->assertSame(1, $this->validations);
    $this->assertSame(1, $this->rows());
    $job = $this->lifecycle->loadJobByUuid($first['shot_1']['jobId']);
    $this->assertSame((string) $this->owner->id(), (string) $job->getOwnerId());
    $this->assertSame($first['shot_1']['taskId'], $job->get('field_task_id')->value);
    $this->assertTrue($this->sketches->admits($job));
    $this->assertSame(0, (int) \Drupal::database()->select(ShootingSketches::TABLE, 's')
      ->condition('plan_uuid', $this->plan->uuid())->condition('queue_pending', 1)->countQuery()->execute()->fetchField());
  }

  public function testFailedSketchProjectsSafeCategoryWithoutRewritingOrExposingProviderDetails(): void {
    $first = $this->submit()['shot_1'];
    $job = $this->lifecycle->loadJobByUuid($first['jobId']);
    $message = 'The request was rejected by the content safety policy. Please revise the input and try again.';
    $this->lifecycle->markFailed($job, $message, 'validation');
    $projection = $this->read()['shot_1'];
    $this->assertSame('content_policy', $projection['errorCode']);
    $this->assertSame('failed', $projection['status']);
    $this->assertSame($first['jobId'], $projection['jobId']);
    $this->assertSame($first['taskId'], $projection['taskId']);
    $this->assertStringNotContainsString($message, json_encode($projection));
    $this->assertArrayNotHasKey('statusReason', $projection);
    $this->assertSame(1, $this->rows());
    $this->assertSame('validation', $this->lifecycle->loadJobByUuid($first['jobId'])->get('field_error_code')->value);

    \Drupal::state()->set('shooting.denied', 'field_status_reason:view');
    $this->assertSame('validation', $this->read()['shot_1']['errorCode']);
    \Drupal::state()->delete('shooting.denied');
    $job->set('field_error_code', 'content_policy')->save();
    $this->assertSame('content_policy', $this->read()['shot_1']['errorCode']);
    $job->set('field_error_code', 'validation')->set('field_status_reason', 'Invalid size.')->save();
    $this->assertSame('validation', $this->read()['shot_1']['errorCode']);
  }

  public function testCustomProviderReservesJobsWithEncryptedCredentialsAndExplicitSize(): void {
    $input = $this->customInput();
    $first = $this->submit($input);
    $this->assertSame($first, $this->submit($input));
    $this->assertSame(1, $this->validations);
    foreach ($first as $sketch) {
      $job = $this->lifecycle->loadJobByUuid($sketch['jobId']);
      $this->assertSame('custom', $job->get('field_platform')->value);
      $this->assertSame('my-image-model', $job->get('field_model')->value);
      $this->assertSame('1024x1536', json_decode($job->get('field_params')->value, TRUE)['size']);
      $this->assertSame(['endpoint' => $input['credentials']['endpoint'], 'api_key' => 'synthetic-key'],
        $this->credentials->get($job->uuid()));
      $public = json_encode([$job->toArray(), $first, $this->doc]);
      $this->assertStringNotContainsString('synthetic-key', $public);
      $this->assertStringNotContainsString('images.example.com', $public);
      $encrypted = \Drupal::service('keyvalue.expirable')->get(ImageJobCredentialVault::COLLECTION)->get($job->uuid());
      $this->assertStringNotContainsString('synthetic-key', $encrypted);
    }
    $this->assertSame($first, $this->read());
    $this->assertSame(1, $this->rows());
  }

  public function testCustomValidationAndBlockedDestinationsDoNotCreateJobs(): void {
    foreach ([['credentials' => []], ['size' => '0x1024'], ['size' => ['1024x1536']],
      ['model' => ' '], ['credentials' => ['endpoint' => 'http://127.0.0.1/v1', 'apiKey' => 'synthetic-key']]] as $invalid) {
      $this->expectHttp(fn() => $this->submit($invalid + $this->customInput()), 422);
    }
    $this->assertSame(0, $this->rows());
    $this->assertSame(0, $this->validations);
  }

  public function testCustomReservationRollsBackCredentialsAlongWithJobs(): void {
    $before = $this->jobs();
    $values = \Drupal::service('keyvalue.expirable')->get(ImageJobCredentialVault::COLLECTION);
    $keys = array_keys($values->getAll());
    $this->failValidationAt = 1;
    $this->expectHttp(fn() => $this->submit($this->customInput()), 422);
    $this->assertSame($before, $this->jobs());
    $this->assertSame(0, $this->rows());
    $this->assertSame($keys, array_keys($values->getAll()));
  }

  private function customInput(): array {
    return ['platform' => 'custom', 'model' => 'my-image-model', 'size' => '1024x1536',
      'credentials' => ['endpoint' => 'https://images.example.com/v1/images/generations',
        'apiKey' => 'synthetic-key']];
  }

  public function testValidationRollsBackBothJobsAndAssociations(): void {
    $before = $this->jobs();
    $this->failValidationAt = 1;
    $this->expectHttp(fn() => $this->submit(), 422);
    $this->assertSame(0, $this->rows());
    $this->assertSame($before, $this->jobs());
  }

  public function testQueueFailureRetainsAnOutboxAndReusesTheSameJobs(): void {
    $this->queueFails = TRUE;
    try {
      $this->submit();
      $this->fail('Queue failure must reach the caller.');
    }
    catch (\RuntimeException $error) {
      $this->assertStringContainsString('queue', $error->getMessage());
    }
    $before = $this->read();
    $this->assertCount(1, $before);
    $this->queueFails = FALSE;
    $this->assertSame($before, $this->submit());
    $this->assertSame(1, $this->validations);
  }

  public function testUnknownAttemptsCannotBeReplacedOrHiddenByVisualEdits(): void {
    $first = $this->submit();
    $job = $this->lifecycle->loadJobByUuid($first['shot_1']['jobId']);
    $this->lifecycle->markFailed($job, 'Request outcome unavailable', ImageJobExecutor::RESULT_UNKNOWN);
    $this->doc['draft']['shots'][0]['visual'] = 'A changed composition';
    $this->saveDoc();
    $this->assertSame('unknown', $this->read()['shot_1']['status']);
    $this->assertFalse($this->read()['shot_1']['retryable']);
    $this->expectHttp(fn() => $this->submit(['shotId' => 'shot_1', 'previousJobId' => $job->uuid()]), 409);
    $this->assertSame(1, $this->rows());
    $this->assertSame(1, $this->validations);
  }

  public function testRetriesNameTheirPredecessorAndRepeatedRetriesDoNotCreateNewJobs(): void {
    $first = $this->submit();
    $job = $this->lifecycle->loadJobByUuid($first['shot_1']['jobId']);
    $this->lifecycle->markFailed($job, 'Rejected before dispatch', 'invalid_prompt');
    $input = ['shotId' => 'shot_1', 'previousJobId' => $job->uuid()];
    $second = $this->submit($input);
    $this->assertNotSame($first['shot_1']['jobId'], $second['shot_1']['jobId']);
    $this->assertSame($second, $this->submit($input));
    $this->assertSame(2, $this->rows());
  }

  public function testAnEditedAndRestoredVisualStillCreatesAnExplicitNewAttempt(): void {
    $first = $this->submit();
    $original = $this->doc['draft']['shots'][0]['visual'];
    $job = $this->lifecycle->loadJobByUuid($first['shot_1']['jobId']);
    $job->set('field_status', 'succeeded')->save();
    $this->doc['draft']['shots'][0]['visual'] = 'Changed visual';
    $this->saveDoc();
    $continued = $this->submit();
    $this->assertSame($first['shot_1']['jobId'], $continued['shot_1']['jobId']);
    $this->assertSame($first['shot_1']['prompt'], $continued['shot_1']['prompt']);
    $second = $this->submit(['shotId' => 'shot_1', 'previousJobId' => $job->uuid()]);
    $job = $this->lifecycle->loadJobByUuid($second['shot_1']['jobId']);
    $job->set('field_status', 'succeeded')->save();
    $this->doc['draft']['shots'][0]['visual'] = $original;
    $this->saveDoc();
    $third = $this->submit(['shotId' => 'shot_1', 'previousJobId' => $job->uuid()]);
    $this->assertNotSame($first['shot_1']['jobId'], $third['shot_1']['jobId']);
    $this->assertSame(3, $this->rows());
  }

  #[DataProvider('denials')]
  public function testRequiresCurrentEntityFieldAndOwnerAccess(string $denial, bool $write): void {
    if ($denial === 'other-owner') {
      $this->plan->setOwnerId(1)->save();
    }
    elseif ($denial === 'anonymous') {
      \Drupal::currentUser()->setAccount(new AnonymousUserSession());
    }
    else {
      \Drupal::state()->set('shooting.denied', $denial);
    }
    $this->expectHttp(fn() => $write ? $this->submit() : $this->read(), 403);
    $this->assertSame(0, $this->rows());
  }

  public static function denials(): array {
    return [['other-owner', FALSE], ['anonymous', FALSE],
      ['field_shooting_document:view', FALSE], ['field_shooting_document:edit', TRUE]];
  }

  public function testRejectsStaleHashesAndUserSuppliedModelCredentialsBeforeReservation(): void {
    $this->expectHttp(fn() => $this->submit(['expectedHash' => str_repeat('0', 64)]), 409);
    $this->customModel = TRUE;
    $this->expectHttp(fn() => $this->submit(), 422);
    $this->assertSame(0, $this->rows());
  }

  public function testRechecksTheSourceAndAccountBeforeExecution(): void {
    $images = $this->submit();
    $job = $this->lifecycle->loadJobByUuid($images['shot_1']['jobId']);
    $this->assertTrue($this->sketches->admits($job));
    \Drupal::state()->set('shooting.denied', 'field_shooting_document:view');
    $this->assertFalse($this->sketches->admits($job));
    \Drupal::state()->delete('shooting.denied');
    \Drupal::state()->delete('shooting.fail_save');
    $this->owner->block()->save();
    $this->assertFalse($this->sketches->admits($job));
    $this->assertSame((string) $this->owner->id(), (string) \Drupal::currentUser()->id());
  }

  public function testImageProjectionChecksEveryReferencedFieldBeforeReturningMedia(): void {
    $images = $this->submit();
    $job = $this->lifecycle->loadJobByUuid($images['shot_1']['jobId']);
    $file = File::create(['uri' => 'public://synthetic-shot.png', 'uid' => $this->owner->id(), 'status' => 1]);
    $file->save();
    $media = Media::create(['bundle' => 'image', 'name' => 'Sketch', 'status' => 1,
      'uid' => $this->owner->id(), 'field_media_image' => ['target_id' => $file->id(), 'alt' => 'Sketch']]);
    $media->save();
    $asset = Node::create(['type' => 'image_asset', 'title' => 'Sketch', 'status' => 1,
      'uid' => $this->owner->id(), 'field_status' => 'succeeded',
      'field_asset_image' => ['target_id' => $media->id()]]);
    $asset->save();
    $job->set('field_status', 'succeeded')->set('field_assets', [['target_id' => $asset->id()]])->save();
    $this->assertStringContainsString('synthetic-shot.png', $this->read()['shot_1']['src']);
    foreach (['field_assets', 'field_asset_image', 'field_media_image', 'uri'] as $field) {
      \Drupal::state()->set('shooting.denied', $field . ':view');
      $this->expectHttp(fn() => $this->read(), 403);
    }
  }

  public function testControllerBoundsInputAndDisablesResponseCaching(): void {
    $controller = new ShootingSketchController($this->sketches);
    $request = Request::create('/', 'POST', content: str_repeat('x', 16385));
    $response = $controller->submit($this->plan->uuid(), $request);
    $this->assertSame(413, $response->getStatusCode());
    $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    $response = $controller->read($this->plan->uuid());
    $this->assertSame('{}', json_encode(json_decode($response->getContent())->sketches));
    $routes = Yaml::parseFile(dirname(__DIR__, 3) . '/xinshi_shooting_plan.routing.yml');
    $this->assertSame('TRUE', $routes['xinshi_shooting_plan.sketches.submit']['requirements']['_csrf_request_header_token']);
    $this->assertSame(['oauth2', 'cookie'], $routes['xinshi_shooting_plan.sketches.submit']['options']['_auth']);
  }

  public function testSketchStatusRefreshesWithoutClearingDrupalCaches(): void {
    $jobs = $this->submit();
    $controller = new ShootingSketchController($this->sketches);
    $path = '/api/v3/ai/shooting-plans/' . $this->plan->uuid() . '/sketches';
    $config = \Drupal::configFactory()->getEditable('system.performance');
    $previous = $config->get('cache.page.max_age');
    $config->set('cache.page.max_age', 3600)->save();
    try {
      $read = static function () use ($controller, $path) {
        $response = $controller->read(basename(dirname($path)));
        $event = new \Symfony\Component\HttpKernel\Event\ResponseEvent(
          \Drupal::service('http_kernel'), Request::create($path),
          HttpKernelInterface::MAIN_REQUEST, $response);
        \Drupal::service('finish_response_subscriber')->onRespond($event);
        return $response;
      };
      $queued = $read();
      $this->assertSame('queued', json_decode($queued->getContent(), TRUE)['sketches']['shot_1']['status']);
      $job = $this->lifecycle->loadJobByUuid($jobs['shot_1']['jobId']);
      $job->set('field_status', 'succeeded')->save();
      $finished = $read();
      $this->assertSame('succeeded', json_decode($finished->getContent(), TRUE)['sketches']['shot_1']['status']);
      foreach ([$queued, $finished] as $response) {
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertFalse($response->headers->hasCacheControlDirective('public'));
      }
    }
    finally {
      $config->set('cache.page.max_age', $previous)->save();
    }
  }

  public function testShippedContentTypeIsPrivateAndIndependentOfChat(): void {
    $this->assertFalse($this->plan->isPublished());
    $this->assertTrue($this->plan->access('view', $this->owner));
    $this->assertTrue($this->plan->access('update', $this->owner));
    $this->assertFalse($this->plan->hasField('field_sessions'));
    $this->assertFalse($this->plan->hasField('field_content'));
    $references = $this->plan->getFieldDefinition('field_shooting_references');
    $this->assertSame('media', $references->getSetting('target_type'));
    $this->assertSame(-1, $references->getFieldStorageDefinition()->getCardinality());
    $this->assertSame(0, (int) \Drupal::entityTypeManager()->getStorage('node')->getQuery()
      ->accessCheck(FALSE)->condition('type', ['conversation', 'ai_session'], 'IN')->count()->execute());
    $other = User::create(['name' => 'other-' . \Drupal::service('uuid')->generate(),
      'status' => 1, 'roles' => ['shooting_owner']]);
    $other->save();
    // Even an administrator publishing a plan does not expose its body or title to other users.
    $this->plan->setPublished()->save();
    $this->assertFalse($this->plan->access('view', $other));
    $this->assertFalse($this->plan->access('view label', $other));
    $this->assertFalse($this->plan->access('update', $other));
    $this->assertFalse($this->plan->access('view', new AnonymousUserSession()));
    $ordinary = Node::create(['type' => 'conversation', 'title' => 'Ordinary chat', 'status' => 1,
      'uid' => $this->owner->id()]);
    $ordinary->save();
    $this->assertTrue($ordinary->access('view', $other));
    $this->expectHttp(fn() => $this->documents->read($ordinary->uuid()), 404);
    $ordinary->delete();
  }

  public function testContentAndHistoryTitleCommitTogetherAndConflictsPreserveBoth(): void {
    $before = $this->plan->get('field_shooting_document')->value;
    $changed = $this->doc;
    $changed['request']['topic'] = 'Revised topic';
    $content = json_encode($changed);
    $controller = new ShootingDocumentController($this->documents);
    $request = Request::create('/', 'PATCH', content: json_encode([
      'expectedHash' => hash('sha256', $before), 'content' => $content,
    ]));
    $response = $controller->update($this->plan->uuid(), $request);
    $this->assertSame(200, $response->getStatusCode());
    $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    $saved = Node::load($this->plan->id());
    $this->assertSame('Revised topic', $saved->getTitle());
    $this->assertSame($content, $saved->get('field_shooting_document')->value);
    // Lost acknowledgements return the complete committed bytes; a later edit cannot be overwritten.
    $retry = $controller->update($this->plan->uuid(), $request);
    $this->assertSame(409, $retry->getStatusCode());
    $this->assertSame($content, json_decode($retry->getContent(), TRUE)['content']);
    $changed['request']['topic'] = 'Stale overwrite';
    $conflict = $this->documents->update($this->plan->uuid(), hash('sha256', $before), json_encode($changed));
    $this->assertFalse($conflict['updated']);
    $this->assertSame('Revised topic', Node::load($this->plan->id())->getTitle());
  }

  public function testWriteFailureAndTitleAccessDenialPreserveTheWholeDocument(): void {
    $before = $this->plan->get('field_shooting_document')->value;
    $changed = $this->doc;
    $changed['request']['topic'] = 'Must not save';
    \Drupal::state()->set('shooting.denied', 'title:edit');
    $this->expectHttp(fn() => $this->documents->update($this->plan->uuid(),
      hash('sha256', $before), json_encode($changed)), 403);
    \Drupal::state()->delete('shooting.denied');
    \Drupal::state()->set('shooting.fail_save', TRUE);
    $controller = new ShootingDocumentController($this->documents);
    $response = $controller->update($this->plan->uuid(), Request::create('/', 'PATCH',
      content: json_encode(['expectedHash' => hash('sha256', $before), 'content' => json_encode($changed)])));
    $this->assertSame(503, $response->getStatusCode());
    $saved = Node::load($this->plan->id());
    $this->assertSame('Synthetic plan', $saved->getTitle());
    $this->assertSame($before, $saved->get('field_shooting_document')->value);
  }

  public function testAdministratorCanReadAndEditAnotherAuthorsPlanWithoutChangingOwnership(): void {
    $original = $this->submit();
    $before = $this->plan->get('field_shooting_document')->value;
    $role = Role::create(['id' => 'shooting_admin', 'label' => 'Shooting administrator',
      'permissions' => ['bypass node access', 'create xinshi_ai image job', 'view media']]);
    $role->save();
    $admin = User::create(['name' => 'admin-' . \Drupal::service('uuid')->generate(),
      'status' => 1, 'roles' => [$role->id()]]);
    $admin->save();
    \Drupal::currentUser()->setAccount($admin);
    $this->assertTrue($this->plan->access('view', $admin));
    $this->assertSame($original['shot_1']['jobId'], $this->read()['shot_1']['jobId']);
    $this->expectHttp(fn() => $this->submit(['shotId' => 'shot_1',
      'previousJobId' => $original['shot_1']['jobId']]), 409);
    $changed = $this->doc;
    $changed['request']['topic'] = 'Administrator revision';
    $saved = $this->documents->update($this->plan->uuid(), hash('sha256', $before), json_encode($changed));
    $this->assertTrue($saved['updated']);
    $node = Node::load($this->plan->id());
    $this->assertSame((string) $this->owner->id(), (string) $node->getOwnerId());
    $this->assertSame($this->doc['taskId'], json_decode($saved['content'], TRUE)['taskId']);
    $this->assertFalse($this->documents->update($this->plan->uuid(),
      hash('sha256', $before), $before)['updated']);
    \Drupal::state()->set('shooting.denied', 'field_shooting_document:edit');
    $this->expectHttp(fn() => $this->documents->update($this->plan->uuid(),
      hash('sha256', $saved['content']), $saved['content']), 403);
    \Drupal::state()->delete('shooting.denied');
    $role->revokePermission('bypass node access')->save();
    $admin = User::load($admin->id());
    \Drupal::currentUser()->setAccount($admin);
    $this->expectHttp(fn() => $this->read(), 403);
    $role->delete();
  }

  public function testNonReadyDocumentsCanSaveButCannotSubmitImages(): void {
    foreach (['draft', 'generating', 'failed', 'cancelled'] as $status) {
      $before = Node::load($this->plan->id())->get('field_shooting_document')->value;
      $changed = $this->doc;
      $changed['status'] = $status;
      unset($changed['original'], $changed['draft']);
      $saved = $this->documents->update($this->plan->uuid(), hash('sha256', $before), json_encode($changed));
      $this->assertTrue($saved['updated']);
      $this->expectHttp(fn() => $this->sketches->submit($this->plan->uuid(), [
        'expectedHash' => hash('sha256', $saved['content']), 'model' => 'fixture',
      ]), 422);
    }
    $this->assertSame(0, $this->rows());
  }

  public function testSharingOmitsReviewTaskAndWorkflowMetadata(): void {
    [$sharing, $editor, $token] = $this->sharedFixture();
    $this->doc['workflow'] = 'plan';
    $this->doc['reviewTask'] = [
      'taskId' => $this->doc['taskId'], 'runId' => $this->doc['runId'],
      'request' => ['topic' => 'Private review brief', 'duration' => 60, 'platform' => 'douyin',
        'format' => 'comparison', 'tone' => 'natural', 'style' => 'pencil', 'locale' => 'zh-hans'],
    ];
    $this->saveDoc();
    \Drupal::currentUser()->setAccount(new AnonymousUserSession());
    $projection = $sharing->read($token);
    $this->assertArrayNotHasKey('workflow', $projection);
    $this->assertArrayNotHasKey('reviewTask', $projection);
    $this->assertStringNotContainsString('Private review brief', json_encode($projection));
    $this->assertStringNotContainsString($this->doc['reviewTask']['runId'], json_encode($projection));
  }

  public function testRevisionReservationsUseCasAndStayOutOfSharedResults(): void {
    [$sharing, $editor, $token] = $this->sharedFixture();
    $before = Node::load($this->plan->id())->get('field_shooting_document')->value;
    $changed = $this->doc;
    $task = ['version' => 1, 'taskId' => \Drupal::service('uuid')->generate(),
      'inputHash' => str_repeat('a', 64), 'planHash' => str_repeat('b', 64),
      'spec' => ['version' => 1, 'documentId' => $this->plan->uuid(), 'operation' => 'rewrite',
        'request' => ['topic' => 'Private revision instructions', 'duration' => 60,
          'platform' => 'douyin', 'format' => 'comparison', 'tone' => 'natural',
          'style' => 'pencil', 'locale' => 'zh-hans'],
        'shotId' => 'shot_1', 'mode' => 'dialogue', 'instruction' => 'Shorten the dialogue.']];
    $changed['revisionTask'] = $task;
    $saved = $this->documents->update($this->plan->uuid(), hash('sha256', $before), json_encode($changed));
    $this->assertTrue($saved['updated']);
    $this->assertSame($changed, $this->documents->read($this->plan->uuid()));
    $this->assertFalse($this->documents->update($this->plan->uuid(), hash('sha256', $before), $before)['updated']);
    foreach (['version' => 2, 'taskId' => 'bad', 'runId' => NULL, 'inputHash' => 'bad',
      'planHash' => 2, 'credentials' => 'forbidden', 'plan' => $changed['draft']] as $key => $value) {
      $invalid = $changed;
      $invalid['revisionTask'][$key] = $value;
      $this->expectHttp(fn() => ShootingDocumentAccess::document(json_encode($invalid), $this->plan->uuid()), 422);
    }
    foreach (['documentId' => \Drupal::service('uuid')->generate(), 'version' => 2,
      'operation' => 'delete', 'mode' => 'duration', 'shotId' => '',
      'instruction' => str_repeat('a', 2001), 'apiKey' => 'forbidden'] as $key => $value) {
      $invalid = $changed;
      $invalid['revisionTask']['spec'][$key] = $value;
      $this->expectHttp(fn() => ShootingDocumentAccess::document(json_encode($invalid), $this->plan->uuid()), 422);
    }
    $changed['revisionTask']['runId'] = \Drupal::service('uuid')->generate();
    $changed['revisionTask']['spec']['operation'] = 'check';
    unset($changed['revisionTask']['spec']['shotId'], $changed['revisionTask']['spec']['mode'],
      $changed['revisionTask']['spec']['instruction']);
    $this->assertSame($changed, ShootingDocumentAccess::document(json_encode($changed), $this->plan->uuid()));
    \Drupal::currentUser()->setAccount(new AnonymousUserSession());
    $projection = $sharing->read($token);
    $this->assertArrayNotHasKey('revisionTask', $projection);
    $this->assertStringNotContainsString('Private revision instructions', json_encode($projection));
    $this->assertSame(0, $this->rows());
  }

  public function testReviewedModeAndFrozenReplayLocatorUseTheDocumentCas(): void {
    $before = $this->plan->get('field_shooting_document')->value;
    $changed = $this->doc;
    $changed['workflow'] = 'plan';
    $changed['reviewTask'] = [
      'taskId' => $changed['taskId'], 'runId' => $changed['runId'],
      'request' => ['topic' => 'Original topic', 'duration' => 60, 'platform' => 'douyin',
        'format' => 'comparison', 'tone' => 'natural', 'style' => 'pencil', 'locale' => 'zh-hans'],
    ];
    $saved = $this->documents->update($this->plan->uuid(), hash('sha256', $before), json_encode($changed));
    $this->assertTrue($saved['updated']);
    $this->assertSame($changed, $this->documents->read($this->plan->uuid()));
    $this->assertFalse($this->documents->update($this->plan->uuid(), hash('sha256', $before), $before)['updated']);
    $changed['previousGeneration'] = array_intersect_key($changed,
      array_flip(['taskId', 'runId', 'request', 'status', 'workflow', 'reviewTask']));
    $changed['status'] = 'generating';
    $changed['taskId'] = \Drupal::service('uuid')->generate();
    unset($changed['runId'], $changed['reviewTask']);
    $this->assertSame($changed, ShootingDocumentAccess::document(json_encode($changed), $this->plan->uuid()));
    foreach (['page-design', NULL, 1] as $mode) {
      $invalid = $changed;
      $invalid['workflow'] = $mode;
      $this->expectHttp(fn() => ShootingDocumentAccess::document(json_encode($invalid), $this->plan->uuid()), 422);
      $invalid = $changed;
      $invalid['previousGeneration']['workflow'] = $mode;
      $this->expectHttp(fn() => ShootingDocumentAccess::document(json_encode($invalid), $this->plan->uuid()), 422);
    }
    foreach (['runId', 'taskId', 'request', 'credentials'] as $field) {
      $invalid = $changed;
      $invalid['reviewTask'] = $changed['previousGeneration']['reviewTask'];
      $invalid['reviewTask'][$field] = 'invalid';
      $this->expectHttp(fn() => ShootingDocumentAccess::document(json_encode($invalid), $this->plan->uuid()), 422);
    }
    $invalid = $changed;
    $invalid['reviewTask'] = $changed['previousGeneration']['reviewTask'];
    $invalid['reviewTask']['request']['apiKey'] = 'forbidden';
    $this->expectHttp(fn() => ShootingDocumentAccess::document(json_encode($invalid), $this->plan->uuid()), 422);
    $this->assertSame(0, $this->rows());
  }

  public function testRegenerationUpdatesOnePlanAndRetainsRecoveryMetadata(): void {
    $before = $this->plan->get('field_shooting_document')->value;
    $changed = $this->doc;
    $changed['previousGeneration'] = array_intersect_key($this->doc,
      array_flip(['taskId', 'runId', 'request', 'status', 'visuals']));
    $changed['taskId'] = \Drupal::service('uuid')->generate();
    unset($changed['runId']);
    $changed['request']['topic'] = 'Updated creative brief';
    $changed['status'] = 'generating';
    $saved = $this->documents->update($this->plan->uuid(), hash('sha256', $before), json_encode($changed));
    $this->assertTrue($saved['updated']);
    $this->assertSame($changed, json_decode($saved['content'], TRUE));
    $this->assertSame('Updated creative brief', Node::load($this->plan->id())->getTitle());
    $this->expectHttp(fn() => $this->sketches->submit($this->plan->uuid(), [
      'expectedHash' => hash('sha256', $saved['content']), 'model' => 'fixture',
    ]), 422);
    $restored = $this->documents->update($this->plan->uuid(), hash('sha256', $saved['content']), $before);
    $this->assertTrue($restored['updated']);
    $this->assertSame($before, $restored['content']);
    foreach (['id', 'previousGeneration', 'credentials'] as $forbidden) {
      $invalid = $changed;
      $invalid['previousGeneration'][$forbidden] = 'unexpected';
      $this->expectHttp(fn() => $this->documents->update($this->plan->uuid(),
        hash('sha256', $before), json_encode($invalid)), 422);
    }
    $changed['previousGeneration']['runId'] = 'invalid';
    $this->expectHttp(fn() => $this->documents->update($this->plan->uuid(),
      hash('sha256', $before), json_encode($changed)), 422);
  }

  public function testContentUpdateRequiresOwnerAndCurrentFieldAccess(): void {
    $content = $this->plan->get('field_shooting_document')->value;
    foreach (['field_shooting_document:view', 'field_shooting_document:edit'] as $denial) {
      \Drupal::state()->set('shooting.denied', $denial);
      $this->expectHttp(fn() => $this->documents->update($this->plan->uuid(), hash('sha256', $content), $content), 403);
    }
    \Drupal::state()->delete('shooting.denied');
    $this->owner->block()->save();
    $this->expectHttp(fn() => $this->documents->update($this->plan->uuid(), hash('sha256', $content), $content), 403);
    $this->owner->activate()->save();
    \Drupal::currentUser()->setAccount(new AnonymousUserSession());
    $this->expectHttp(fn() => $this->documents->update($this->plan->uuid(), hash('sha256', $content), $content), 403);
  }

  public function testJsonApiCreatesAndListsOnlyIndependentOwnedPlans(): void {
    $this->assertFalse(\Drupal::config('jsonapi.settings')->get('read_only'));
    \Drupal::service('router.builder')->rebuild();
    $kernel = \Drupal::service('http_kernel');
    $principal = $this->owner;
    $send = static function (string $path, string $method = 'GET', ?array $body = NULL) use ($kernel, &$principal) {
      \Drupal::currentUser()->setAccount($principal);
      $request = Request::create($path, $method, server: [
        'HTTP_ACCEPT' => 'application/vnd.api+json', 'CONTENT_TYPE' => 'application/vnd.api+json',
      ], content: $body === NULL ? NULL : json_encode($body));
      return $kernel->handle($request, HttpKernelInterface::SUB_REQUEST);
    };
    $uuid = \Drupal::service('uuid')->generate();
    $doc = $this->doc;
    $doc['id'] = $uuid;
    $doc['status'] = 'draft';
    unset($doc['original'], $doc['draft'], $doc['runId']);
    $payload = ['data' => ['type' => 'node--shooting_plan', 'id' => $uuid, 'attributes' => [
      'title' => 'Synthetic plan', 'field_shooting_document' => json_encode($doc),
    ]]];
    $response = $send('/jsonapi/node/shooting_plan', 'POST', $payload);
    $this->assertSame(201, $response->getStatusCode(), $response->getContent());
    $created = json_decode($response->getContent(), TRUE)['data'];
    $this->assertSame($uuid, $created['id']);
    $this->assertSame($this->owner->uuid(), $created['relationships']['uid']['data']['id']);
    $this->assertSame(json_encode($doc), $created['attributes']['field_shooting_document']);
    $this->assertFalse($created['attributes']['status']);
    $retry = $send('/jsonapi/node/shooting_plan', 'POST', $payload);
    $this->assertSame(409, $retry->getStatusCode(), $retry->getContent());
    $response = $send('/jsonapi/node/shooting_plan?filter[uid.drupal_internal__uid]=' . $this->owner->id() .
      '&page[limit]=20&sort=-created');
    $this->assertSame(200, $response->getStatusCode(), $response->getContent());
    $ids = array_column(json_decode($response->getContent(), TRUE)['data'], 'id');
    $this->assertContains($uuid, $ids);
    $this->assertContains($this->plan->uuid(), $ids, $response->getContent());
    $this->assertSame([], json_decode($send('/jsonapi/node/conversation')->getContent(), TRUE)['data']);
    $other = User::create(['name' => 'reader-' . \Drupal::service('uuid')->generate(),
      'status' => 1, 'roles' => ['shooting_owner']]);
    $other->save();
    $principal = $other;
    $response = $send('/jsonapi/node/shooting_plan');
    $this->assertSame(200, $response->getStatusCode(), $response->getContent());
    $this->assertSame([], json_decode($response->getContent(), TRUE)['data']);
    $this->assertSame(403, $send('/jsonapi/node/shooting_plan/' . $uuid)->getStatusCode());
  }

  public function testSharingProjectsOnlyTheDraftAndGrantsOnlyTheSelectedAccount(): void {
    [$sharing, $editor, $token] = $this->sharedFixture();
    $before = $this->doc;
    \Drupal::currentUser()->setAccount(new AnonymousUserSession());
    $public = $sharing->read($token);
    $this->assertSame(['id', 'request', 'plan', 'canEdit', 'contentHash', 'sketches'], array_keys($public));
    $this->assertSame($this->doc['draft'], $public['plan']);
    $this->assertFalse($public['canEdit']);
    $this->assertNull($public['contentHash']);
    $this->assertFalse($this->plan->access('view', \Drupal::currentUser()));
    $input = ['expectedHash' => str_repeat('0', 64), 'plan' => $public['plan']];
    $this->expectHttp(fn() => $sharing->update($token, $input), 403);
    $stranger = User::create(['name' => 'stranger-' . $token, 'status' => 1, 'roles' => ['shooting_owner']]);
    $stranger->save();
    \Drupal::currentUser()->setAccount($stranger);
    $this->assertFalse($sharing->read($token)['canEdit']);
    $this->expectHttp(fn() => $sharing->update($token, $input), 403);
    $this->expectHttp(fn() => $sharing->settings($this->plan->uuid()), 403);
    \Drupal::currentUser()->setAccount($editor);
    $shared = $sharing->read($token);
    $this->assertTrue($shared['canEdit']);
    $input['expectedHash'] = $shared['contentHash'];
    $input['plan']['title'] = 'Collaborative title';
    $input['plan']['shots'] = array_reverse($input['plan']['shots']);
    $saved = $sharing->update($token, $input);
    $this->assertSame($saved, $sharing->update($token, $input));
    $stored = json_decode(Node::load($this->plan->id())->get('field_shooting_document')->value, TRUE);
    $this->assertSame($input['plan'], $stored['draft']);
    unset($stored['draft'], $before['draft']);
    $this->assertSame($before, $stored);
    $this->assertSame((string) $this->owner->id(), (string) Node::load($this->plan->id())->getOwnerId());
    $this->assertFalse($this->plan->access('update', $editor));
    $this->expectHttp(fn() => $sharing->configure($this->plan->uuid(), ['enabled' => TRUE, 'editorId' => 0]), 403);
    $this->expectHttp(fn() => $this->documents->read($this->plan->uuid()), 403);
  }

  public function testSharedEditsConflictWithOwnerSavesAndRevocationTakesEffectImmediately(): void {
    [$sharing, $editor, $token] = $this->sharedFixture();
    $original = $sharing->read($token);
    $content = Node::load($this->plan->id())->get('field_shooting_document')->value;
    $this->doc['draft']['concept'] = 'Owner updated concept';
    $this->documents->update($this->plan->uuid(), hash('sha256', $content), json_encode($this->doc));
    \Drupal::currentUser()->setAccount($editor);
    $input = ['expectedHash' => $original['contentHash'], 'plan' => $original['plan']];
    $input['plan']['title'] = 'Stale collaborator title';
    $this->expectHttp(fn() => $sharing->update($token, $input), 409);
    $fresh = $sharing->read($token);
    $this->assertSame('Owner updated concept', $fresh['plan']['concept']);
    \Drupal::currentUser()->setAccount($this->owner);
    $sharing->configure($this->plan->uuid(), ['enabled' => TRUE, 'editorId' => 0]);
    \Drupal::currentUser()->setAccount($editor);
    $this->assertFalse($sharing->read($token)['canEdit']);
    $this->expectHttp(fn() => $sharing->update($token, $input), 403);
    \Drupal::currentUser()->setAccount($this->owner);
    $sharing->configure($this->plan->uuid(), ['enabled' => FALSE, 'editorId' => 0]);
    $this->expectHttp(fn() => $sharing->read($token), 404);
    $next = $sharing->configure($this->plan->uuid(), ['enabled' => TRUE, 'editorId' => 0]);
    $this->assertNotSame($token, $next['token']);
    $this->expectHttp(fn() => $sharing->read($token), 404);
    $this->plan->delete();
    $this->expectHttp(fn() => $sharing->read($next['token']), 404);
  }

  public function testSharingRechecksBlockedAccountsAndFieldGrantsAndRejectsInvalidInput(): void {
    [$sharing, $editor, $token] = $this->sharedFixture();
    $shared = $sharing->read($token);
    $input = ['expectedHash' => $shared['contentHash'], 'plan' => $shared['plan']];
    $this->expectHttp(fn() => $sharing->update($token, $input + ['runId' => $this->doc['runId']]), 422);
    $invalid = $input;
    $invalid['plan']['shots'][0]['duration'] = -1;
    $this->expectHttp(fn() => $sharing->update($token, $invalid), 422);
    \Drupal::state()->set('shooting.denied', 'field_shooting_document:view');
    $this->expectHttp(fn() => $sharing->read($token), 403);
    \Drupal::state()->set('shooting.denied', 'field_shooting_document:edit');
    \Drupal::currentUser()->setAccount($editor);
    $this->assertFalse($sharing->read($token)['canEdit']);
    $this->expectHttp(fn() => $sharing->update($token, $input), 403);
    \Drupal::state()->delete('shooting.denied');
    $editor->block()->save();
    $this->assertFalse($sharing->read($token)['canEdit']);
    $this->expectHttp(fn() => $sharing->update($token, $input), 403);
    $this->owner->block()->save();
    $this->expectHttp(fn() => $sharing->read($token), 404);
  }

  public function testSharedImagesUseRevocableFilesWithoutLeakingPrivateUrlsOrTaskIds(): void {
    [$sharing, $editor, $token] = $this->sharedFixture();
    [$reference, $reference_file] = $this->referenceImage();
    $this->doc['request']['style'] = 'color';
    $this->doc['visuals'] = ['reference' => ['mediaId' => $reference->uuid(),
      'fileId' => $reference_file->uuid(), 'name' => 'Private client reference']];
    $this->saveDoc();
    $images = $this->submit();
    $job = $this->lifecycle->loadJobByUuid($images['shot_1']['jobId']);
    $image = imagecreatetruecolor(1200, 800);
    imagepng($image, 'private://shared-synthetic.png');
    imagedestroy($image);
    $file = File::create(['uri' => 'private://shared-synthetic.png', 'filemime' => 'image/png',
      'uid' => $this->owner->id(), 'status' => 1]);
    $file->save();
    $media = Media::create(['bundle' => 'image', 'name' => 'Shared sketch', 'status' => 1,
      'uid' => $this->owner->id(), 'field_media_image' => ['target_id' => $file->id(), 'alt' => 'Sketch']]);
    $media->save();
    $asset = Node::create(['type' => 'image_asset', 'title' => 'Shared sketch', 'status' => 1,
      'uid' => $this->owner->id(), 'field_status' => 'succeeded', 'field_asset_image' => ['target_id' => $media->id()]]);
    $asset->save();
    $job->set('field_status', 'succeeded')->set('field_assets', [['target_id' => $asset->id()]])->save();
    $owned = $this->sketches->read($this->plan->uuid())['shot_1'];
    $style = \Drupal::entityTypeManager()->getStorage('image_style')->load('large');
    $this->assertSame($style->buildUrl($file->getFileUri()), $owned['thumbnailSrc']);
    $this->assertNotSame($owned['src'], $owned['thumbnailSrc']);
    \Drupal::currentUser()->setAccount(new AnonymousUserSession());
    $projection = (array) $sharing->read($token)['sketches'];
    $this->assertSame('/api/v3/ai/shooting-plans/shared/' . $token . '/sketches/shot_1', $projection['shot_1']['src']);
    $this->assertSame($projection['shot_1']['src'] . '?size=thumbnail', $projection['shot_1']['thumbnailSrc']);
    $this->assertStringNotContainsString('private', json_encode($projection));
    $this->assertArrayNotHasKey('jobId', $projection['shot_1']);
    $this->assertArrayNotHasKey('taskId', $projection['shot_1']);
    $this->assertStringContainsString('colored storyboard', $projection['shot_1']['prompt']);
    $this->assertStringNotContainsString('Private client reference', json_encode($projection));
    $this->assertStringNotContainsString($reference->uuid(), json_encode($projection));
    $this->assertFalse($projection['shot_1']['stale']);
    $controller = new ShootingSharingController($sharing, \Drupal::service('file_system'), \Drupal::entityTypeManager());
    $response = $controller->image($token, 'shot_1', Request::create('/'));
    $this->assertSame(200, $response->getStatusCode());
    $this->assertInstanceOf(\Symfony\Component\HttpFoundation\BinaryFileResponse::class, $response);
    $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    $this->assertSame('image/png', $response->headers->get('Content-Type'));
    $thumbnail_request = Request::create('/?size=thumbnail');
    $thumbnail = $controller->image($token, 'shot_1', $thumbnail_request);
    $this->assertSame(200, $thumbnail->getStatusCode());
    $dimensions = getimagesize($thumbnail->getFile()->getPathname());
    $this->assertSame([480, 320], array_slice($dimensions, 0, 2));
    $this->assertSame($dimensions['mime'], $thumbnail->headers->get('Content-Type'));
    $this->assertStringContainsString('no-store', $thumbnail->headers->get('Cache-Control'));
    $this->assertSame('nosniff', $thumbnail->headers->get('X-Content-Type-Options'));
    $this->assertNotSame($response->getFile()->getPathname(), $thumbnail->getFile()->getPathname());
    $this->assertSame($thumbnail->getFile()->getPathname(),
      $controller->image($token, 'shot_1', $thumbnail_request)->getFile()->getPathname());
    $this->assertSame(400, $controller->image($token, 'shot_1', Request::create('/?size=unknown'))->getStatusCode());
    $this->assertSame(400, $controller->image($token, 'shot_1', Request::create('/?size[]=thumbnail'))->getStatusCode());
    $this->assertSame(404, $controller->image($token, 'nonexistent', Request::create('/'))->getStatusCode());
    $this->assertSame(404, $controller->image($token, 'nonexistent', $thumbnail_request)->getStatusCode());
    \Drupal::state()->set('shooting.denied', 'uri:view');
    $this->assertSame(403, $controller->image($token, 'shot_1', Request::create('/'))->getStatusCode());
    $this->assertSame(403, $controller->image($token, 'shot_1', $thumbnail_request)->getStatusCode());
    \Drupal::state()->delete('shooting.denied');
    \Drupal::currentUser()->setAccount($this->owner);
    $sharing->configure($this->plan->uuid(), ['enabled' => FALSE, 'editorId' => (int) $editor->id()]);
    \Drupal::currentUser()->setAccount(new AnonymousUserSession());
    $this->assertSame(404, $controller->image($token, 'shot_1', Request::create('/'))->getStatusCode());
    $this->assertSame(404, $controller->image($token, 'shot_1', $thumbnail_request)->getStatusCode());
  }

  public function testSharingAccountPickerIsBoundedAndRoutesSeparatePublicReadsFromCsrfWrites(): void {
    [$sharing, $editor, $token] = $this->sharedFixture();
    $this->assertSame([], $sharing->users($this->plan->uuid(), 'x'));
    $this->assertSame([['id' => (int) $editor->id(), 'name' => $editor->getAccountName()]],
      $sharing->users($this->plan->uuid(), $editor->getAccountName()));
    $routes = Yaml::parseFile(dirname(__DIR__, 3) . '/xinshi_shooting_plan.routing.yml');
    foreach (['sharing.update', 'shared.update'] as $route) {
      $this->assertSame('TRUE', $routes['xinshi_shooting_plan.' . $route]['requirements']['_csrf_request_header_token']);
      $this->assertSame('TRUE', $routes['xinshi_shooting_plan.' . $route]['requirements']['_user_is_logged_in']);
    }
    foreach (['shared.read', 'shared.image'] as $route) {
      $this->assertSame('TRUE', $routes['xinshi_shooting_plan.' . $route]['requirements']['_access']);
    }
    $controller = new ShootingSharingController($sharing, \Drupal::service('file_system'), \Drupal::entityTypeManager());
    \Drupal::currentUser()->setAccount(new AnonymousUserSession());
    $response = $controller->read($token);
    $this->assertSame(200, $response->getStatusCode());
    $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    $this->assertSame('no-referrer', $response->headers->get('Referrer-Policy'));
    $this->assertSame(404, $controller->read(str_repeat('0', 64))->getStatusCode());
  }

  public function testPublicHttpRoutesAndCookieCsrfProtectCollaborativeWrites(): void {
    [$sharing, $editor, $token] = $this->sharedFixture();
    \Drupal::getContainer()->set('xinshi_shooting_plan.sharing', $sharing);
    \Drupal::service('router.builder')->rebuild();
    $path = '/api/v3/ai/shooting-plans/shared/' . $token;
    $principal = new AnonymousUserSession();
    $send = static function (string $method, ?array $body = NULL, bool $cookie = FALSE, ?string $csrf = NULL)
      use ($path, &$principal) {
      \Drupal::currentUser()->setAccount($principal);
      $request = Request::create($path, $method, server: ['HTTP_ACCEPT' => 'application/json',
        'CONTENT_TYPE' => 'application/json'], content: $body === NULL ? NULL : json_encode($body));
      if ($cookie) {
        $name = \Drupal::service('session_configuration')->getOptions($request)['name'];
        $request->cookies->set($name, 'synthetic-session');
      }
      if ($csrf !== NULL) {
        $request->headers->set('X-CSRF-Token', $csrf);
      }
      // Error-page subrequests can replace the matcher context in this long-lived test kernel.
      $context = \Drupal::service('router.request_context');
      $context->fromRequest($request);
      \Drupal::service('router')->setContext($context);
      return \Drupal::service('http_kernel')->handle($request, HttpKernelInterface::SUB_REQUEST);
    };
    $public = $send('GET');
    $this->assertSame(200, $public->getStatusCode(), $public->getContent());
    $this->assertFalse(json_decode($public->getContent(), TRUE)['canEdit']);
    $this->assertStringContainsString('no-store', $public->headers->get('Cache-Control'));
    $denied = $send('PATCH', []);
    $this->assertSame(403, $denied->getStatusCode(), $denied->getContent());
    $principal = $editor;
    $view = $send('GET');
    $this->assertSame(200, $view->getStatusCode(), $view->getContent());
    $shared = json_decode($view->getContent(), TRUE);
    $this->assertTrue($shared['canEdit']);
    $input = ['expectedHash' => $shared['contentHash'], 'plan' => $shared['plan']];
    $input['plan']['title'] = 'Saved through HTTP';
    $denied = $send('PATCH', $input, TRUE);
    $this->assertSame(403, $denied->getStatusCode(), $denied->getContent());
    $denied = $send('PATCH', $input, TRUE, 'invalid');
    $this->assertSame(403, $denied->getStatusCode(), $denied->getContent());
    $csrf = \Drupal::service('csrf_token')->get('X-CSRF-Token request header');
    $saved = $send('PATCH', $input, TRUE, $csrf);
    $this->assertSame(200, $saved->getStatusCode(), $saved->getContent());
    $this->assertStringContainsString('no-store', $saved->headers->get('Cache-Control'));
    $principal = new AnonymousUserSession();
    $this->assertSame('Saved through HTTP', json_decode($send('GET')->getContent(), TRUE)['plan']['title']);
  }

  private function sharedFixture(): array {
    $this->doc['draft'] += ['title' => 'Shared plan', 'concept' => 'A clear comparison',
      'audience' => 'Beginners', 'assumptions' => ['One presenter'], 'preparation' => ['Charge the phone'],
      'targetDuration' => 60, 'structure' => [['id' => 'intro', 'title' => 'Introduction']]];
    foreach ($this->doc['draft']['shots'] as &$shot) {
      $shot += ['sectionId' => 'intro', 'duration' => 30, 'audio' => 'Quiet room',
        'actions' => ['Start recording'], 'acceptance' => 'Clear speech', 'dialogue' => [],
        'tip' => 'Keep the camera steady', 'sounds' => []];
    }
    $this->saveDoc();
    $sharing = new ShootingSharing(\Drupal::database(), \Drupal::entityTypeManager(),
      \Drupal::currentUser(), \Drupal::service('account_switcher'), $this->documents, $this->sketches);
    $this->assertNull($sharing->settings($this->plan->uuid())['token']);
    $editor = User::create(['name' => 'collaborator-' . \Drupal::service('uuid')->generate(), 'status' => 1]);
    $editor->save();
    $settings = $sharing->configure($this->plan->uuid(), ['enabled' => TRUE, 'editorId' => (int) $editor->id()]);
    $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $settings['token']);
    return [$sharing, $editor, $settings['token']];
  }

  private function saveDoc(): void {
    $this->plan->set('field_shooting_document', json_encode($this->doc, JSON_UNESCAPED_UNICODE))->save();
  }

  private function submit(array $input = []): array {
    return $this->sketches->submit($this->plan->uuid(), $input + [
      'expectedHash' => hash('sha256', $this->plan->get('field_shooting_document')->value), 'model' => 'fixture',
    ]);
  }

  private function read(): array {
    return $this->sketches->read($this->plan->uuid());
  }

  private function rows(): int {
    return (int) \Drupal::database()->select(ShootingSketches::TABLE, 's')
      ->condition('plan_uuid', $this->plan->uuid())->countQuery()->execute()->fetchField();
  }

  private function jobs(): int {
    return (int) \Drupal::entityTypeManager()->getStorage('node')->getQuery()->accessCheck(FALSE)
      ->condition('type', 'image_job')->count()->execute();
  }

  private function expectHttp(callable $action, int $status): void {
    try {
      $action();
      $this->fail('Expected HTTP ' . $status);
    }
    catch (HttpException $error) {
      $this->assertSame($status, $error->getStatusCode(), $error->getMessage());
    }
  }

  private static function field(string $entity, string $bundle, string $name, string $type,
    array $settings = [], int $cardinality = 1): void {
    if (!FieldStorageConfig::loadByName($entity, $name)) {
      FieldStorageConfig::create(['entity_type' => $entity, 'field_name' => $name, 'type' => $type,
        'settings' => $settings, 'cardinality' => $cardinality])->save();
    }
    FieldConfig::create(['entity_type' => $entity, 'bundle' => $bundle, 'field_name' => $name])->save();
  }

}
