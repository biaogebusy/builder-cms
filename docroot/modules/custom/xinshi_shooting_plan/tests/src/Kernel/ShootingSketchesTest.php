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
      'request' => ['topic' => 'Synthetic plan'], 'original' => [], 'status' => 'ready', 'draft' => [
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
    $this->documents = new ShootingDocumentAccess($db, $entities, $account,
      \Drupal::service('jsonapi.resource_type.repository'));
    $inputImages = $this->createMock(InputImageAccess::class);
    $inputImages->expects($this->never())->method('forSubmission');
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
    $tasks->method('getByKind')->with('text_to_image')->willReturn($task);
    $models = $this->createMock(ModelRegistryServiceInterface::class);
    $models->method('getModel')->willReturn(['id' => 'fixture', 'platform' => 'fixture',
      'capabilities' => ['image'], 'sizes' => ['1024x1024', '768x1360']]);
    $models->method('getPlatforms')->willReturnCallback(fn() => [
      ['id' => 'fixture', 'user_supplied' => $this->customModel],
    ]);
    $realQueue = \Drupal::queue(ImageJobExecutor::QUEUE);
    $queue = $this->createMock(QueueInterface::class);
    $queue->method('createItem')->willReturnCallback(fn($item) => $this->queueFails ? FALSE : $realQueue->createItem($item));
    $queues = $this->createMock(QueueFactory::class);
    $queues->method('get')->willReturn($queue);
    $runner = new ImmediateJobRunner($queues, $this->createMock(QueueWorkerManagerInterface::class), new NullLogger());
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
      $runner, \Drupal::configFactory(), \Drupal::service('account_switcher'), $this->credentials, $urlPolicy);
  }

  public function testCreatesRealImageJobsAndDeduplicatesLostSubmissionAcknowledgements(): void {
    $first = $this->submit();
    $this->assertCount(2, $first);
    $this->assertSame($first, $this->submit());
    $this->assertSame(2, $this->validations);
    $this->assertSame(2, $this->rows());
    $job = $this->lifecycle->loadJobByUuid($first['shot_1']['jobId']);
    $this->assertSame((string) $this->owner->id(), (string) $job->getOwnerId());
    $this->assertSame($first['shot_1']['taskId'], $job->get('field_task_id')->value);
    $this->assertTrue($this->sketches->admits($job));
    $this->assertSame(0, (int) \Drupal::database()->select(ShootingSketches::TABLE, 's')
      ->condition('plan_uuid', $this->plan->uuid())->condition('queue_pending', 1)->countQuery()->execute()->fetchField());
  }

  public function testCustomProviderReservesJobsWithEncryptedCredentialsAndExplicitSize(): void {
    $input = $this->customInput();
    $first = $this->submit($input);
    $this->assertSame($first, $this->submit($input));
    $this->assertSame(2, $this->validations);
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
    $this->assertSame(2, $this->rows());
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
    $this->failValidationAt = 2;
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
    $this->failValidationAt = 2;
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
    $this->assertCount(2, $before);
    $this->queueFails = FALSE;
    $this->assertSame($before, $this->submit());
    $this->assertSame(2, $this->validations);
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
    $this->assertSame(2, $this->rows());
    $this->assertSame(2, $this->validations);
  }

  public function testRetriesNameTheirPredecessorAndRepeatedRetriesDoNotCreateNewJobs(): void {
    $first = $this->submit();
    $job = $this->lifecycle->loadJobByUuid($first['shot_1']['jobId']);
    $this->lifecycle->markFailed($job, 'Rejected before dispatch', 'invalid_prompt');
    $input = ['shotId' => 'shot_1', 'previousJobId' => $job->uuid()];
    $second = $this->submit($input);
    $this->assertNotSame($first['shot_1']['jobId'], $second['shot_1']['jobId']);
    $this->assertSame($second, $this->submit($input));
    $this->assertSame(3, $this->rows());
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
    $this->assertSame(4, $this->rows());
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
