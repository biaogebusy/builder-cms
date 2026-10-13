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
use Drupal\jsonapi_extras\Entity\JsonapiResourceConfig;
use Drupal\media\Entity\Media;
use Drupal\media\Entity\MediaType;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\xinshi_ai\AiTaskInterface;
use Drupal\xinshi_ai\AiTaskManagerInterface;
use Drupal\xinshi_ai\Service\ImageJobExecutor;
use Drupal\xinshi_ai\Service\ImmediateJobRunner;
use Drupal\xinshi_ai\Service\InputImageAccess;
use Drupal\xinshi_ai\Service\JobLifecycleService;
use Drupal\xinshi_ai\Service\ModelRegistryServiceInterface;
use Drupal\xinshi_shooting_plan\Controller\ShootingSketchController;
use Drupal\xinshi_shooting_plan\Service\ShootingDocumentAccess;
use Drupal\xinshi_shooting_plan\Service\ShootingSketches;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Yaml\Yaml;

/** Real Drupal nodes, JSON:API aliases, field access, transactions and queue with no model calls. */
final class ShootingSketchesTest extends TestCase {

  private ShootingSketches $sketches;
  private ShootingDocumentAccess $documents;
  private JobLifecycleService $lifecycle;
  private Node $chat;
  private Node $session;
  private User $owner;
  private array $doc;
  private bool $queueFails = FALSE;
  private int $validations = 0;
  private int $failValidationAt = 0;
  private bool $customModel = FALSE;

  public static function setUpBeforeClass(): void {
    require dirname(__DIR__, 2) . '/install-fixture.php';
    foreach (['conversation', 'ai_session', 'image_job', 'image_asset'] as $type) {
      NodeType::create(['type' => $type, 'name' => $type])->save();
    }
    self::field('node', 'conversation', 'field_sessions', 'entity_reference', ['target_type' => 'node'], -1);
    self::field('node', 'conversation', 'field_chat_type', 'string');
    self::field('node', 'ai_session', 'field_content', 'string_long');
    foreach (['conversation' => ['sessions', 'chat_type'], 'ai_session' => ['content']] as $bundle => $fields) {
      $aliases = [];
      foreach ($fields as $field) {
        $aliases['field_' . $field] = ['fieldName' => 'field_' . $field, 'publicName' => $field, 'disabled' => FALSE];
      }
      JsonapiResourceConfig::create(['id' => 'node--' . $bundle, 'resourceType' => 'node--' . $bundle,
        'path' => 'node/' . $bundle, 'disabled' => FALSE, 'resourceFields' => $aliases])->save();
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
      'access content', 'view media', 'create xinshi_ai image job',
      'edit own conversation content', 'edit own ai_session content',
    ]])->save();
  }

  protected function setUp(): void {
    \Drupal::state()->delete('shooting.denied');
    $this->owner = User::create(['name' => 'owner-' . \Drupal::service('uuid')->generate(),
      'status' => 1, 'roles' => ['shooting_owner']]);
    $this->owner->save();
    \Drupal::currentUser()->setAccount($this->owner);
    $this->chat = Node::create(['type' => 'conversation', 'title' => 'Synthetic plan',
      'uid' => $this->owner->id(), 'status' => 1, 'field_chat_type' => 'shooting-plan']);
    $this->chat->save();
    $this->session = Node::create(['type' => 'ai_session', 'title' => 'Synthetic script',
      'uid' => $this->owner->id(), 'status' => 1]);
    $this->session->save();
    $this->doc = ['kind' => 'xinshi-shooting-plan', 'version' => 1, 'id' => $this->chat->uuid(),
      'sessionId' => $this->session->uuid(), 'status' => 'ready', 'draft' => [
        'aspectRatio' => '9:16', 'styleGuide' => 'Same presenter in a quiet studio.',
        'shots' => [['id' => 'shot_1', 'framing' => 'wide', 'visual' => 'Presenter at a desk',
          'sketchPrompt' => 'Camera at eye level'], ['id' => 'shot_2', 'framing' => 'detail',
          'visual' => 'Two sample objects', 'sketchPrompt' => 'Overhead close view']],
      ]];
    $this->saveDoc();
    $this->chat->set('field_sessions', [['target_id' => $this->session->id()]])->save();
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
      $this->assertSame('768x1360', $input['params']['size']);
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
    $this->sketches = new ShootingSketches($db, $entities, $account, $this->documents, $this->lifecycle,
      $tasks, $models, $queues, \Drupal::service('uuid'), \Drupal::service('file_url_generator'),
      $runner, \Drupal::configFactory(), \Drupal::service('account_switcher'));
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
      ->condition('session_uuid', $this->session->uuid())->condition('queue_pending', 1)->countQuery()->execute()->fetchField());
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
      $this->session->setOwnerId(1)->save();
    }
    elseif ($denial === 'anonymous') {
      \Drupal::currentUser()->setAccount(new AnonymousUserSession());
    }
    elseif ($denial === 'membership') {
      $this->chat->set('field_sessions', [])->save();
    }
    else {
      \Drupal::state()->set('shooting.denied', $denial);
    }
    $this->expectHttp(fn() => $write ? $this->submit() : $this->read(), 403);
    $this->assertSame(0, $this->rows());
  }

  public static function denials(): array {
    return [['other-owner', FALSE], ['anonymous', FALSE], ['membership', FALSE],
      ['field_content:view', FALSE], ['field_content:edit', TRUE], ['field_sessions:view', TRUE],
      ['field_chat_type:view', FALSE]];
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
    \Drupal::state()->set('shooting.denied', 'field_content:view');
    $this->assertFalse($this->sketches->admits($job));
    \Drupal::state()->delete('shooting.denied');
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
    $request = Request::create('/', 'POST', content: str_repeat('x', 4097));
    $response = $controller->submit($this->chat->uuid(), $this->session->uuid(), $request);
    $this->assertSame(413, $response->getStatusCode());
    $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    $response = $controller->read($this->chat->uuid(), $this->session->uuid());
    $this->assertSame('{}', json_encode(json_decode($response->getContent())->sketches));
    $routes = Yaml::parseFile(dirname(__DIR__, 3) . '/xinshi_shooting_plan.routing.yml');
    $this->assertSame('TRUE', $routes['xinshi_shooting_plan.sketches.submit']['requirements']['_csrf_request_header_token']);
    $this->assertSame(['oauth2', 'cookie'], $routes['xinshi_shooting_plan.sketches.submit']['options']['_auth']);
  }

  private function saveDoc(): void {
    $this->session->set('field_content', json_encode($this->doc, JSON_UNESCAPED_UNICODE))->save();
  }

  private function submit(array $input = []): array {
    return $this->sketches->submit($this->chat->uuid(), $this->session->uuid(), $input + [
      'expectedHash' => hash('sha256', $this->session->get('field_content')->value), 'model' => 'fixture',
    ]);
  }

  private function read(): array {
    return $this->sketches->read($this->chat->uuid(), $this->session->uuid());
  }

  private function rows(): int {
    return (int) \Drupal::database()->select(ShootingSketches::TABLE, 's')
      ->condition('session_uuid', $this->session->uuid())->countQuery()->execute()->fetchField();
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
