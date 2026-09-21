<?php

declare(strict_types=1);

namespace Drupal\Tests\xinshi_ai_usage\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\KeyValueStore\KeyValueMemoryFactory;
use Drupal\Core\Lock\NullLockBackend;
use Drupal\Core\PrivateKey;
use Drupal\Core\Site\Settings;
use Drupal\xinshi_ai_usage\Contract\ImageUsageNormalizer;
use Drupal\xinshi_ai_usage\Service\AttemptReconcileService;
use Drupal\xinshi_ai_usage\Service\CostRatingService;
use Drupal\xinshi_ai_usage\Service\LocalUsageException;
use Drupal\xinshi_ai_usage\Service\LocalUsageProducer;
use Drupal\xinshi_ai_usage\Service\PriceBookService;
use Drupal\xinshi_ai_usage\Service\ProducerVault;
use Drupal\xinshi_ai_usage\Service\UsageIngestService;
use Drupal\xinshi_ai_usage\Service\UsageProjectionService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Yaml\Yaml;

/**
 * Operator verdicts on unknown image attempts become observation revisions (UB2.5).
 */
final class AttemptReconcileTest extends TestCase {

  private const SITE = 'site-a';

  private Connection $database;
  private UsageIngestService $ingest;
  private LocalUsageProducer $producer;
  private UsageProjectionService $projection;
  private AttemptReconcileService $reconcile;
  private LoggerInterface $logger;
  private int $now = 1_700_000_000;
  private int $sequence = 0;
  /** @var list<string> */
  private array $notices = [];

  protected function setUp(): void {
    parent::setUp();
    Database::addConnectionInfo('reconcile_test', 'default', [
      'driver' => 'sqlite', 'database' => ':memory:',
      'namespace' => 'Drupal\\sqlite\\Driver\\Database\\sqlite',
    ]);
    $this->database = Database::getConnection('default', 'reconcile_test');
    foreach (xinshi_ai_usage_schema() as $table => $spec) {
      $this->database->schema()->createTable($table, $spec);
    }
    $time = $this->createMock(TimeInterface::class);
    $time->method('getCurrentMicroTime')->willReturnCallback(fn() => $this->now + 0.25);
    $uuid = $this->createMock(UuidInterface::class);
    $uuid->method('generate')->willReturnCallback(fn() => sprintf('00000000-0000-4000-8000-%012d', ++$this->sequence));
    // The Settings singleton must exist before the vault encrypts a secret.
    $settings = new Settings(['hash_salt' => 'isolated-hash-salt']);
    $privateKey = $this->createMock(PrivateKey::class);
    $privateKey->method('get')->willReturn('isolated-site-private-key');
    $vault = new ProducerVault(new KeyValueMemoryFactory(), $privateKey);
    $vault->set('chat-node', 'node-secret', self::SITE, $this->now);
    $this->ingest = new UsageIngestService($this->database, $time, new NullLogger());
    $this->producer = new LocalUsageProducer($this->database, $this->ingest, $vault, $settings, $time, $uuid,
      new NullLogger());
    $priceBook = new PriceBookService($this->database, $time);
    $this->projection = new UsageProjectionService($this->database,
      new CostRatingService($this->database, $priceBook, $time), new NullLockBackend(), $time, new NullLogger());
    $this->logger = $this->createMock(LoggerInterface::class);
    $this->logger->method('notice')->willReturnCallback(function (string $message, array $context): void {
      $this->notices[] = strtr($message, $context);
    });
    $this->reconcile = new AttemptReconcileService($this->database, $this->producer, $this->projection, $this->logger);
  }

  protected function tearDown(): void {
    Database::removeConnection('reconcile_test');
    parent::tearDown();
  }

  public function testServiceAndCommandsAreWired(): void {
    $module = dirname(__DIR__, 3);
    $services = Yaml::parseFile($module . '/xinshi_ai_usage.services.yml')['services'];
    $this->assertSame(AttemptReconcileService::class, $services['xinshi_ai_usage.reconcile']['class']);
    $this->assertFileExists($module . '/src/Drush/Commands/UsageReconcileCommands.php');
  }

  public function testUnknownAttemptsAreListedNewestFirstWithTheirClues(): void {
    $first = $this->unknownAttempt('job-1', 'req-1');
    $this->now += 10;
    $second = $this->unknownAttempt('job-2', NULL);
    $done = $this->producer->prepare($this->intent(['operation_id' => 'job-3']))['attempt_id'];
    $this->producer->observe($done, ['outcome' => 'succeeded', 'dispatch_state' => 'sent',
      'usage' => ImageUsageNormalizer::normalize(['data' => [[]]]), 'error_code' => NULL]);
    $this->projection->process(self::SITE);

    $rows = $this->reconcile->attempts(self::SITE);
    $this->assertSame([$second, $first], array_column($rows, 'attempt_id'));
    $this->assertSame('req-1', $rows[1]['gateway_request_id']);
    $this->assertSame('unknown', $rows[1]['dispatch_state']);
    $this->assertSame(LocalUsageProducer::INTERRUPTED, $rows[1]['error_code']);
    $this->assertSame(LocalUsageProducer::PRODUCER_ID, $rows[1]['producer_id']);

    $this->assertSame([$done], array_column($this->reconcile->attempts(self::SITE, 'succeeded'), 'attempt_id'));
    $this->assertSame([], $this->reconcile->attempts('other-site'));
    $this->assertSame([], $this->reconcile->attempts(self::SITE, 'unknown', 50, 'chat-node'));
    $this->assertCount(1, $this->reconcile->attempts(NULL, 'unknown', 1));
  }

  public function testASucceededVerdictWithTheBilledImageCountUpdatesTheProjection(): void {
    $id = $this->unknownAttempt('job-1', NULL);
    $this->projection->process(self::SITE);
    $this->assertSame('unknown', $this->attempt($id)['state']);

    $result = $this->reconcile->reconcile($id, ['outcome' => 'succeeded', 'images' => 4,
      'gateway_request_id' => 'req-9', 'resolved_model' => 'qwen-image-2', 'note' => 'gateway log']);

    $this->assertSame(2, $result['observation_revision']);
    $event = $this->event($id, 2);
    $this->assertSame('succeeded', $event['outcome']);
    $this->assertSame('sent', $event['dispatch_state']);
    $this->assertNull($event['error_code']);
    $this->assertSame('reported', $event['usage']['quality']);
    $this->assertSame('4', $event['usage']['images_generated']);
    $this->assertSame(ImageUsageNormalizer::VERSION, $event['usage']['normalizer_version']);
    $this->assertSame('req-9', $event['provider']['gateway_request_id']);
    $this->assertSame('qwen-image-2', $event['provider']['resolved_model']);
    $this->assertArrayNotHasKey('note', $event);

    // The consumer already ran: the projection reflects the verdict.
    $attempt = $this->attempt($id);
    $this->assertSame('succeeded', $attempt['state']);
    $this->assertSame(2, (int) $attempt['usage_revision']);
    $this->assertSame(4, (int) $attempt['images_generated']);
    $this->assertSame(CostRatingService::STATE_UNPRICED, $attempt['cost_valuation_state']);
    $this->assertSame('succeeded', $result['projection']['state']);
    $this->assertSame(4, (int) $result['projection']['images_generated']);
    $this->assertSame([], $this->reconcile->attempts(self::SITE));
    $this->assertStringContainsString('reconciled as succeeded', $this->notices[0]);
    $this->assertStringContainsString('gateway log', $this->notices[0]);
  }

  public function testASucceededVerdictWithTheRawSupplierResponseIsNormalized(): void {
    $id = $this->unknownAttempt('job-1', 'req-1');
    $this->reconcile->reconcile($id, ['outcome' => 'succeeded',
      'usage' => ['data' => [['url' => 'a'], ['url' => 'b']], 'usage' => ['total_tokens' => 30, 'input_tokens' => 10,
        'output_tokens' => 20]]]);
    $event = $this->event($id, 2);
    $this->assertSame('2', $event['usage']['images_generated']);
    $this->assertSame('10', $event['usage']['input_tokens_total']);
    // The recorded request id is kept when the verdict brings none.
    $this->assertSame('req-1', $event['provider']['gateway_request_id']);

    // A count overrides the data list of a pasted response.
    $other = $this->unknownAttempt('job-2', NULL);
    $this->reconcile->reconcile($other, ['outcome' => 'succeeded', 'images' => 1,
      'usage' => ['data' => [[], [], []]]]);
    $this->assertSame('1', $this->event($other, 2)['usage']['images_generated']);
  }

  public function testFailedAndNotSentVerdictsCarryTheReconciliationCode(): void {
    $failed = $this->unknownAttempt('job-1', 'req-1');
    $notSent = $this->unknownAttempt('job-2', NULL);

    $this->reconcile->reconcile($failed, ['outcome' => 'failed']);
    $this->reconcile->reconcile($notSent, ['outcome' => 'not_sent']);

    $event = $this->event($failed, 2);
    $this->assertSame(['failed', 'sent', AttemptReconcileService::ERROR_CODE, NULL],
      [$event['outcome'], $event['dispatch_state'], $event['error_code'], $event['usage']]);
    $event = $this->event($notSent, 2);
    $this->assertSame(['not_sent', 'not_sent', AttemptReconcileService::ERROR_CODE],
      [$event['outcome'], $event['dispatch_state'], $event['error_code']]);
    $this->assertSame('failed', $this->attempt($failed)['state']);
    $this->assertSame('not_sent', $this->attempt($notSent)['state']);
    $this->assertNull($this->attempt($notSent)['cost_valuation_state']);
  }

  public function testOnlyUnknownLocalAttemptsCanBeReconciled(): void {
    $succeeded = $this->producer->prepare($this->intent())['attempt_id'];
    $this->producer->observe($succeeded, ['outcome' => 'succeeded', 'dispatch_state' => 'sent',
      'usage' => ImageUsageNormalizer::normalize(['data' => [[]]]), 'error_code' => NULL]);
    $open = $this->producer->prepare($this->intent(['operation_id' => 'job-2']))['attempt_id'];
    $this->ingestNodeAttempt('att_node');

    $this->assertRejected($succeeded, ['outcome' => 'failed'], 'not_unknown');
    $this->assertRejected($open, ['outcome' => 'failed'], 'not_unknown');
    $this->assertRejected('att_node', ['outcome' => 'failed'], 'unknown_attempt');
    $this->assertRejected('att_missing', ['outcome' => 'failed'], 'unknown_attempt');

    // A verdict is final: the attempt is no longer unknown afterwards.
    $unknown = $this->unknownAttempt('job-3', NULL);
    $this->reconcile->reconcile($unknown, ['outcome' => 'failed']);
    $this->assertRejected($unknown, ['outcome' => 'succeeded'], 'not_unknown');
    $this->assertSame(2, $this->countObservations($unknown));
  }

  public function testUnusableVerdictsWriteNothing(): void {
    $id = $this->unknownAttempt('job-1', NULL);
    $this->assertRejected($id, [], 'invalid_verdict');
    $this->assertRejected($id, ['outcome' => 'aborted'], 'invalid_verdict');
    $this->assertRejected($id, ['outcome' => 'failed', 'images' => 2], 'invalid_verdict');
    $this->assertRejected($id, ['outcome' => 'not_sent', 'usage' => ['data' => []]], 'invalid_verdict');
    $this->assertRejected($id, ['outcome' => 'succeeded', 'images' => -1], 'invalid_verdict');
    $this->assertRejected($id, ['outcome' => 'succeeded', 'images' => '2'], 'invalid_verdict');
    $this->assertRejected($id, ['outcome' => 'succeeded', 'usage' => 'raw'], 'invalid_verdict');
    $this->assertSame(1, $this->countObservations($id));
  }

  public function testAVerdictWhoseUsageIsContradictoryIsRecordedAsInvalidNotAsZero(): void {
    $id = $this->unknownAttempt('job-1', NULL);
    $this->reconcile->reconcile($id, ['outcome' => 'succeeded',
      'usage' => ['data' => [[]], 'usage' => ['total_tokens' => 5, 'input_tokens' => 10, 'output_tokens' => 20]]]);
    $event = $this->event($id, 2);
    $this->assertSame('invalid', $event['usage']['quality']);
    $this->assertSame('total_below_parts', $event['usage']['invalid_reason']);
    $this->assertNull($event['usage']['images_generated']);
    $attempt = $this->attempt($id);
    $this->assertSame('succeeded', $attempt['state']);
    $this->assertSame('invalid', $attempt['usage_quality']);
    $this->assertNull($attempt['images_generated']);
  }

  private function unknownAttempt(string $operationId, ?string $requestId): string {
    $id = $this->producer->prepare($this->intent(['operation_id' => $operationId]))['attempt_id'];
    $this->producer->observe($id, ['outcome' => 'unknown', 'dispatch_state' => 'unknown', 'usage' => NULL,
      'error_code' => LocalUsageProducer::INTERRUPTED, 'gateway_request_id' => $requestId]);
    return $id;
  }

  private function ingestNodeAttempt(string $attemptId): void {
    $event = $this->producer->prepared($this->producer->prepare($this->intent(['operation_id' => 'seed']))['attempt_id']);
    $event['producer_id'] = 'chat-node';
    $event['attempt_id'] = $attemptId;
    $event['operation_id'] = 'run-1';
    $event['event_id'] = "$attemptId:prepared:0";
    $receipt = $this->ingest->ingest([$event], 'chat-node', self::SITE)[0];
    $this->assertSame('accepted', $receipt['status']);
  }

  private function assertRejected(string $attemptId, array $verdict, string $code): void {
    try {
      $this->reconcile->reconcile($attemptId, $verdict);
      $this->fail("expected $code for $attemptId");
    }
    catch (LocalUsageException $e) {
      $this->assertSame($code, $e->usageCode);
    }
  }

  private function intent(array $overrides = []): array {
    return $overrides + [
      'operation_id' => 'job-1', 'logical_call_id' => 'image', 'feature' => 'text_to_image', 'stage' => 'image',
      'payer' => 'platform', 'billing_role' => 'primary', 'provider_account_ref' => 'xinshi',
      'requested_model' => 'qwen-image', 'actor_user_id' => '7', 'task_id' => NULL,
    ];
  }

  private function event(string $attemptId, int $revision): array {
    $json = $this->database->select(UsageIngestService::TABLE, 'e')
      ->fields('e', ['payload_json'])
      ->condition('attempt_id', $attemptId)
      ->condition('event_type', 'attempt.observed')
      ->condition('observation_revision', $revision)
      ->execute()
      ->fetchField();
    $this->assertNotFalse($json, "revision $revision of $attemptId");
    return json_decode((string) $json, TRUE);
  }

  private function countObservations(string $attemptId): int {
    return (int) $this->database->select(UsageIngestService::TABLE, 'e')
      ->condition('attempt_id', $attemptId)
      ->condition('event_type', 'attempt.observed')
      ->countQuery()->execute()->fetchField();
  }

  private function attempt(string $attemptId): array {
    $row = $this->database->select(UsageProjectionService::ATTEMPT_TABLE, 'a')
      ->fields('a')->condition('attempt_id', $attemptId)->execute()->fetchAssoc();
    $this->assertNotFalse($row, "projection row for $attemptId");
    return $row;
  }

}
