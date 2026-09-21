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
use Drupal\xinshi_ai_usage\Service\CostRatingService;
use Drupal\xinshi_ai_usage\Service\LocalUsageException;
use Drupal\xinshi_ai_usage\Service\LocalUsageProducer;
use Drupal\xinshi_ai_usage\Service\PriceBookService;
use Drupal\xinshi_ai_usage\Service\ProducerVault;
use Drupal\xinshi_ai_usage\Service\UsageIngestService;
use Drupal\xinshi_ai_usage\Service\UsageProjectionService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Yaml\Yaml;

/**
 * Verifies the local image producer against the real ingest and projection (UB2.4).
 *
 * Every fact the producer records goes through UsageIngestService, so it is
 * subject to the same contract, deduplication and consumer as Node events.
 */
final class LocalUsageProducerTest extends TestCase {

  private const SITE = 'site-a';

  private Connection $database;
  private UsageIngestService $ingest;
  private ProducerVault $vault;
  private PriceBookService $priceBook;
  private UsageProjectionService $projection;
  private LocalUsageProducer $producer;
  private TimeInterface $time;
  private UuidInterface $uuid;
  private int $now = 1_700_000_000;
  private int $sequence = 0;

  protected function setUp(): void {
    parent::setUp();
    Database::addConnectionInfo('local_producer_test', 'default', [
      'driver' => 'sqlite', 'database' => ':memory:',
      'namespace' => 'Drupal\\sqlite\\Driver\\Database\\sqlite',
    ]);
    $this->database = Database::getConnection('default', 'local_producer_test');
    foreach (xinshi_ai_usage_schema() as $table => $spec) {
      $this->database->schema()->createTable($table, $spec);
    }
    $time = $this->createMock(TimeInterface::class);
    $time->method('getCurrentMicroTime')->willReturnCallback(fn() => $this->now + 0.25);
    $this->time = $time;
    $uuid = $this->createMock(UuidInterface::class);
    $uuid->method('generate')->willReturnCallback(fn() => sprintf('00000000-0000-4000-8000-%012d', ++$this->sequence));
    $this->uuid = $uuid;
    $settings = new Settings(['hash_salt' => 'isolated-hash-salt']);
    $privateKey = $this->createMock(PrivateKey::class);
    $privateKey->method('get')->willReturn('isolated-site-private-key');
    $this->vault = new ProducerVault(new KeyValueMemoryFactory(), $privateKey);
    // The Node producer registered in the admin UI fixes the site id.
    $this->vault->set('chat-node', 'node-secret', self::SITE, $this->now);
    $this->ingest = new UsageIngestService($this->database, $time, new NullLogger());
    $this->priceBook = new PriceBookService($this->database, $time);
    $costRating = new CostRatingService($this->database, $this->priceBook, $time);
    $this->projection = new UsageProjectionService($this->database, $costRating, new NullLockBackend(), $time,
      new NullLogger());
    $this->producer = $this->producerWith($settings);
  }

  protected function tearDown(): void {
    Database::removeConnection('local_producer_test');
    parent::tearDown();
  }

  public function testServiceIsWired(): void {
    $services = Yaml::parseFile(dirname(__DIR__, 3) . '/xinshi_ai_usage.services.yml')['services'];
    $this->assertSame(LocalUsageProducer::class, $services['xinshi_ai_usage.local_producer']['class']);
    $this->assertContains('@xinshi_ai_usage.ingest', $services['xinshi_ai_usage.local_producer']['arguments']);
  }

  public function testPrepareWritesTheIntentAndNumbersAttemptsPerLogicalCall(): void {
    $first = $this->producer->prepare($this->intent());
    $this->assertSame(self::SITE, $first['site_id']);
    $this->assertSame(1, $first['attempt_no']);
    $this->assertStringStartsWith('att_', $first['attempt_id']);
    $this->assertSame(1_700_000_000_250, $first['started_at']);

    $event = $this->event($first['attempt_id'], 'attempt.prepared', 0);
    $this->assertSame(LocalUsageProducer::PRODUCER_ID, $event['producer_id']);
    $this->assertSame($first['attempt_id'] . ':prepared:0', $event['event_id']);
    $this->assertSame('job-1', $event['operation_id']);
    $this->assertSame('image', $event['logical_call_id']);
    $this->assertSame('not_sent', $event['dispatch_state']);
    $this->assertNull($event['outcome']);
    $this->assertNull($event['usage']);
    $this->assertSame('2023-11-14T22:13:20.250Z', $event['occurred_at']);
    $this->assertSame('2023-11-14T22:13:20.250Z', $event['timing']['started_at']);
    $this->assertNull($event['timing']['finished_at']);
    $this->assertSame([
      'billing_account_id' => NULL, 'actor_user_id' => '7', 'feature' => 'text_to_image', 'stage' => 'image',
      'attempt_no' => 1, 'payer' => 'platform', 'billing_role' => 'primary', 'chat_run_id' => NULL,
      'task_id' => 'task-1', 'chat_id' => NULL,
    ], $event['context']);
    $this->assertSame([
      'account_ref' => 'xinshi', 'requested_model' => 'qwen-image', 'resolved_model' => NULL,
      'gateway_request_id' => NULL, 'provider_request_id' => NULL,
    ], $event['provider']);

    // The same job run again continues the sequence of the same logical call.
    $second = $this->producer->prepare($this->intent());
    $this->assertSame(2, $second['attempt_no']);
    $this->assertNotSame($first['attempt_id'], $second['attempt_id']);
    // Another logical call of the same job and another job both start at 1.
    $this->assertSame(1, $this->producer->prepare($this->intent(['logical_call_id' => 'moderation']))['attempt_no']);
    $this->assertSame(1, $this->producer->prepare($this->intent(['operation_id' => 'job-2']))['attempt_no']);
  }

  public function testObservationsAreMonotonicRevisionsOfThePreparedFact(): void {
    $id = $this->producer->prepare($this->intent())['attempt_id'];
    $usage = ImageUsageNormalizer::normalize(['data' => [['url' => 'a'], ['url' => 'b']]]);

    $first = $this->producer->observe($id, [
      'outcome' => 'succeeded', 'dispatch_state' => 'sent', 'usage' => $usage, 'error_code' => NULL,
      'gateway_request_id' => 'req-1', 'resolved_model' => 'qwen-image-2', 'finished_at' => 1_700_000_004_000,
    ]);
    $this->assertSame(['attempt_id' => $id, 'observation_revision' => 1], $first);
    $event = $this->event($id, 'attempt.observed', 1);
    $this->assertSame("$id:observed:1", $event['event_id']);
    $this->assertSame('succeeded', $event['outcome']);
    $this->assertSame('sent', $event['dispatch_state']);
    $this->assertNull($event['error_code']);
    $this->assertSame('2', $event['usage']['images_generated']);
    $this->assertSame(ImageUsageNormalizer::VERSION, $event['usage']['normalizer_version']);
    $this->assertSame('req-1', $event['provider']['gateway_request_id']);
    $this->assertSame('qwen-image-2', $event['provider']['resolved_model']);
    $this->assertSame('2023-11-14T22:13:24.000Z', $event['occurred_at']);
    $this->assertSame('2023-11-14T22:13:24.000Z', $event['timing']['finished_at']);
    // Context and intent timing are carried over, not re-supplied by the caller.
    $this->assertSame('2023-11-14T22:13:20.250Z', $event['timing']['started_at']);
    $this->assertSame('task-1', $event['context']['task_id']);
    $this->assertSame(1, $event['context']['attempt_no']);

    $second = $this->producer->observe($id, ['outcome' => 'failed', 'dispatch_state' => 'sent', 'usage' => NULL,
      'error_code' => 'manual_reconciliation']);
    $this->assertSame(2, $second['observation_revision']);
    $this->assertSame(2, $this->countEvents($id, 'attempt.observed'));
    $this->assertSame('failed', $this->event($id, 'attempt.observed', 2)['outcome']);
  }

  public function testObservingAnUnknownAttemptFails(): void {
    try {
      $this->producer->observe('att_missing', ['outcome' => 'failed', 'dispatch_state' => 'unknown',
        'usage' => NULL, 'error_code' => 'timeout']);
      $this->fail('expected LocalUsageException');
    }
    catch (LocalUsageException $e) {
      $this->assertSame('unknown_attempt', $e->usageCode);
    }
    $this->assertSame(0, $this->countAllEvents());
  }

  public function testContractRejectionsSurfaceTheMachineCodeAndWriteNothing(): void {
    try {
      $this->producer->prepare($this->intent(['feature' => '图片']));
      $this->fail('expected LocalUsageException');
    }
    catch (LocalUsageException $e) {
      $this->assertSame('invalid_field', $e->usageCode);
    }
    $this->assertSame(0, $this->countAllEvents());
  }

  public function testInterruptedIntentsBecomeUnknownNotFreeAndNotRetried(): void {
    $open = $this->producer->prepare($this->intent());
    $done = $this->producer->prepare($this->intent(['operation_id' => 'job-2']));
    $this->producer->observe($done['attempt_id'], ['outcome' => 'succeeded', 'dispatch_state' => 'sent',
      'usage' => NULL, 'error_code' => NULL]);
    $other = $this->producer->prepare($this->intent(['operation_id' => 'job-3']));

    $this->assertSame([$open['attempt_id']], $this->producer->interruptOpenAttempts('job-1'));

    $event = $this->event($open['attempt_id'], 'attempt.observed', 1);
    $this->assertSame('unknown', $event['outcome']);
    $this->assertSame('unknown', $event['dispatch_state']);
    $this->assertSame(LocalUsageProducer::INTERRUPTED, $event['error_code']);
    $this->assertNull($event['usage']);
    // Observed attempts and other jobs are untouched; a second pass finds nothing.
    $this->assertSame(1, $this->countEvents($done['attempt_id'], 'attempt.observed'));
    $this->assertSame(0, $this->countEvents($other['attempt_id'], 'attempt.observed'));
    $this->assertSame([], $this->producer->interruptOpenAttempts('job-1'));
    // The next run of the job is attempt 2 of the same logical call.
    $this->assertSame(2, $this->producer->prepare($this->intent())['attempt_no']);

    // The consumer sees the interrupted attempt as unknown, never as free.
    $this->projection->process(self::SITE);
    $this->assertSame('unknown', $this->attempt($open['attempt_id'])['state']);
    $this->assertSame('unknown', $this->attempt($open['attempt_id'])['dispatch_state']);
  }

  public function testDeliveryFactsAreAppendOnlyAndReplaySafe(): void {
    $id = $this->producer->prepare($this->intent())['attempt_id'];
    $this->producer->recordDelivery(self::SITE, 'job-1', $id, 0, 'media', 'media-0', NULL,
      LocalUsageProducer::STATE_PERSISTED);
    $this->producer->recordDelivery(self::SITE, 'job-1', $id, 0, 'image_asset', 'asset-0', NULL,
      LocalUsageProducer::STATE_COMMITTED);
    $this->producer->recordDelivery(self::SITE, 'job-1', $id, 1, 'media', 'media-1', NULL,
      LocalUsageProducer::STATE_PERSISTED);
    // A replayed queue item records the same artifact again: no second row.
    $this->producer->recordDelivery(self::SITE, 'job-1', $id, 0, 'image_asset', 'asset-0', NULL,
      LocalUsageProducer::STATE_COMMITTED);

    $rows = $this->producer->deliveries('job-1');
    $this->assertSame([[0, 'media', 'persisted'], [0, 'image_asset', 'committed'], [1, 'media', 'persisted']],
      array_map(fn(array $row) => [(int) $row['output_index'], $row['artifact_kind'], $row['state']], $rows));
    $this->assertSame([$id], array_values(array_unique(array_column($rows, 'attempt_id'))));
    $this->assertSame([], $this->producer->deliveries('job-9'));
  }

  public function testSiteIdComesFromTheSettingOrTheRegisteredProducers(): void {
    $this->assertSame(self::SITE, $this->producer->siteId());

    $explicit = $this->producerWith(new Settings(['hash_salt' => 'isolated-hash-salt',
      'xinshi_ai_usage.site_id' => 'explicit-site']));
    $this->assertSame('explicit-site', $explicit->siteId());

    $fromSettingsFile = $this->producerWith(new Settings(['hash_salt' => 'isolated-hash-salt',
      'xinshi_ai_usage.producers' => ['deploy-node' => ['secret' => 's', 'site_id' => 'deploy-site']]]));
    // settings.php entries come before the admin registry.
    $this->assertSame('deploy-site', $fromSettingsFile->siteId());

    $this->vault->delete('chat-node');
    $none = $this->producerWith(new Settings(['hash_salt' => 'isolated-hash-salt']));
    $this->assertNull($none->siteId());
    try {
      $none->prepare($this->intent());
      $this->fail('expected LocalUsageException');
    }
    catch (LocalUsageException $e) {
      $this->assertSame('site_unresolved', $e->usageCode);
    }
    $this->assertSame([], $none->interruptOpenAttempts('job-1'));
    $this->assertSame([], $none->deliveries('job-1'));
    $this->assertSame(0, $this->countAllEvents());
  }

  public function testModeFallsBackToObserveOnInvalidValues(): void {
    $this->assertSame(LocalUsageProducer::MODE_OBSERVE, $this->producer->mode());
    $enforce = $this->producerWith(new Settings(['hash_salt' => 'isolated-hash-salt',
      'xinshi_ai_usage.mode' => 'enforce']));
    $this->assertSame(LocalUsageProducer::MODE_ENFORCE, $enforce->mode());
    $invalid = $this->producerWith(new Settings(['hash_salt' => 'isolated-hash-salt',
      'xinshi_ai_usage.mode' => 'strict']));
    $this->assertSame(LocalUsageProducer::MODE_OBSERVE, $invalid->mode());
  }

  public function testImageAttemptsProjectImageCountsSeparatelyFromDeliveries(): void {
    $id = $this->producer->prepare($this->intent())['attempt_id'];
    $this->producer->observe($id, ['outcome' => 'succeeded', 'dispatch_state' => 'sent',
      'usage' => ImageUsageNormalizer::normalize(['data' => [['url' => 'a'], ['url' => 'b'], ['url' => 'c']]]),
      'error_code' => NULL, 'gateway_request_id' => 'req-1']);
    // Three images were billed upstream; only two reached the user.
    foreach ([0, 1, 2] as $index) {
      $this->producer->recordDelivery(self::SITE, 'job-1', $id, $index, 'media', "media-$index", NULL,
        LocalUsageProducer::STATE_PERSISTED);
    }
    foreach ([0, 1] as $index) {
      $this->producer->recordDelivery(self::SITE, 'job-1', $id, $index, 'image_asset', "asset-$index", NULL,
        LocalUsageProducer::STATE_COMMITTED);
    }

    $stats = $this->projection->process(self::SITE);
    $this->assertSame(2, $stats['applied']);
    $attempt = $this->attempt($id);
    $this->assertSame('succeeded', $attempt['state']);
    $this->assertSame(LocalUsageProducer::PRODUCER_ID, $attempt['producer_id']);
    $this->assertSame('image', $attempt['stage']);
    $this->assertSame('text_to_image', $attempt['feature']);
    $this->assertSame('primary', $attempt['billing_role']);
    $this->assertSame('reported', $attempt['usage_quality']);
    $this->assertSame(3, (int) $attempt['images_generated']);
    $this->assertNull($attempt['input_tokens_total']);
    $this->assertSame('req-1', $attempt['gateway_request_id']);
    // Without a price book the cost is unpriced, never zero.
    $this->assertSame(CostRatingService::STATE_UNPRICED, $attempt['cost_valuation_state']);
    $this->assertSame('no_price_book', $attempt['cost_unpriced_reason']);
    $this->assertNull($attempt['cost_total_micros']);

    $cells = $this->rollup();
    $this->assertCount(1, $cells);
    $this->assertSame(3, (int) $cells[0]['images_generated']);
    $this->assertSame(1, (int) $cells[0]['usage_reported_count']);
    $this->assertSame(1, (int) $cells[0]['cost_unpriced_count']);
    $this->assertSame(0, (int) $cells[0]['input_tokens_total']);

    $committed = array_filter($this->producer->deliveries('job-1'),
      fn(array $row) => $row['state'] === LocalUsageProducer::STATE_COMMITTED);
    $this->assertCount(2, $committed);
  }

  public function testTheChatPriceBookNeverRatesImages(): void {
    $this->activateChatPriceBook();
    $id = $this->producer->prepare($this->intent())['attempt_id'];
    $this->producer->observe($id, ['outcome' => 'succeeded', 'dispatch_state' => 'sent',
      'usage' => ImageUsageNormalizer::normalize(['data' => [['url' => 'a']],
        'usage' => ['total_tokens' => 1100, 'input_tokens' => 100, 'output_tokens' => 1000]]),
      'error_code' => NULL, 'resolved_model' => 'qwen-image']);
    $this->projection->process(self::SITE);

    $attempt = $this->attempt($id);
    // A chat rate exists for this account and model and the token figures are
    // reported; images are priced per image, so the attempt stays unpriced.
    $this->assertSame(CostRatingService::STATE_UNPRICED, $attempt['cost_valuation_state']);
    $this->assertSame('image_rate_pending', $attempt['cost_unpriced_reason']);
    $this->assertNull($attempt['cost_total_micros']);
    $this->assertSame(100, (int) $attempt['input_tokens_total']);
    $this->assertSame(1, (int) $attempt['images_generated']);
    $cell = $this->rollup()[0];
    $this->assertSame(0, (int) $cell['cost_micros']);
    $this->assertSame(1, (int) $cell['cost_unpriced_count']);
    $this->assertSame(0, (int) $cell['cost_rated_count']);
  }

  private function producerWith(Settings $settings): LocalUsageProducer {
    return new LocalUsageProducer($this->database, $this->ingest, $this->vault, $settings, $this->time,
      $this->uuid, new NullLogger());
  }

  private function activateChatPriceBook(): void {
    $rates = json_encode(['accounts' => ['xinshi' => ['models' => ['qwen-image' => [
      'per_million_input' => 2_000_000, 'per_million_cache_read' => 200_000,
      'per_million_cache_write' => 1_000_000, 'per_million_output' => 8_000_000,
    ]]]]]);
    // Effective before the sample events (2023-11-14) so a chat attempt would be rated.
    $version = $this->priceBook->createDraft(self::SITE, PriceBookService::KIND_SUPPLIER_CHAT, 'v1', 'CNY',
      $rates, 1_600_000_000_000);
    $this->now = 1_600_000_000;
    $this->priceBook->activate($version, self::SITE, PriceBookService::KIND_SUPPLIER_CHAT);
    $this->now = 1_700_000_000;
  }

  private function intent(array $overrides = []): array {
    return $overrides + [
      'operation_id' => 'job-1', 'logical_call_id' => 'image', 'feature' => 'text_to_image', 'stage' => 'image',
      'payer' => 'platform', 'billing_role' => 'primary', 'provider_account_ref' => 'xinshi',
      'requested_model' => 'qwen-image', 'actor_user_id' => '7', 'task_id' => 'task-1',
    ];
  }

  private function event(string $attemptId, string $type, int $revision): array {
    $json = $this->database->select(UsageIngestService::TABLE, 'e')
      ->fields('e', ['payload_json'])
      ->condition('attempt_id', $attemptId)
      ->condition('event_type', $type)
      ->condition('observation_revision', $revision)
      ->execute()
      ->fetchField();
    $this->assertNotFalse($json, "$type revision $revision of $attemptId");
    return json_decode((string) $json, TRUE);
  }

  private function countEvents(string $attemptId, string $type): int {
    return (int) $this->database->select(UsageIngestService::TABLE, 'e')
      ->condition('attempt_id', $attemptId)
      ->condition('event_type', $type)
      ->countQuery()->execute()->fetchField();
  }

  private function countAllEvents(): int {
    return (int) $this->database->select(UsageIngestService::TABLE)->countQuery()->execute()->fetchField();
  }

  private function attempt(string $attemptId): array {
    $row = $this->database->select(UsageProjectionService::ATTEMPT_TABLE, 'a')
      ->fields('a')->condition('attempt_id', $attemptId)->execute()->fetchAssoc();
    $this->assertNotFalse($row, "projection row for $attemptId");
    return $row;
  }

  private function rollup(): array {
    return $this->database->select(UsageProjectionService::ROLLUP_TABLE, 'r')
      ->fields('r')->orderBy('id')->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

}
