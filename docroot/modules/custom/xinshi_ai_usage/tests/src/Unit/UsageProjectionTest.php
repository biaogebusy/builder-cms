<?php

declare(strict_types=1);

namespace Drupal\Tests\xinshi_ai_usage\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Lock\NullLockBackend;
use Drupal\xinshi_ai_usage\Service\CostRatingService;
use Drupal\xinshi_ai_usage\Service\PriceBookService;
use Drupal\xinshi_ai_usage\Service\UsageIngestService;
use Drupal\xinshi_ai_usage\Service\UsageProjectionService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Yaml\Yaml;

/**
 * Verifies the projection consumer against a real database (UB1.6).
 *
 * Covers the acceptance criteria of the ticket: acknowledgement and rollup in
 * one transaction, replay / out-of-order / late revisions never double count,
 * and a rebuild from facts yields the same result.
 */
final class UsageProjectionTest extends TestCase {

  private const SITE = 'site-a';

  private Connection $database;
  private UsageIngestService $ingest;
  private PriceBookService $priceBook;
  private CostRatingService $costRating;
  private UsageProjectionService $projection;
  private TimeInterface $time;
  private int $now = 1_700_000_000;

  protected function setUp(): void {
    parent::setUp();
    Database::addConnectionInfo('projection_test', 'default', [
      'driver' => 'sqlite', 'database' => ':memory:',
      'namespace' => 'Drupal\\sqlite\\Driver\\Database\\sqlite',
    ]);
    $this->database = Database::getConnection('default', 'projection_test');
    foreach (xinshi_ai_usage_schema() as $table => $spec) {
      $this->database->schema()->createTable($table, $spec);
    }
    $time = $this->createMock(TimeInterface::class);
    $time->method('getCurrentMicroTime')->willReturnCallback(fn() => $this->now + 0.25);
    $this->time = $time;
    $this->ingest = new UsageIngestService($this->database, $time, new NullLogger());
    $this->priceBook = new PriceBookService($this->database, $time);
    $this->costRating = new CostRatingService($this->database, $this->priceBook, $time);
    $this->projection = $this->projectionWith(new NullLockBackend());
  }

  protected function tearDown(): void {
    Database::removeConnection('projection_test');
    parent::tearDown();
  }

  public function testServiceAndCommandAreWired(): void {
    $module = dirname(__DIR__, 3);
    $services = Yaml::parseFile($module . '/xinshi_ai_usage.services.yml')['services'];
    $this->assertSame(UsageProjectionService::class, $services['xinshi_ai_usage.projection']['class']);
    $this->assertFileExists($module . '/src/Drush/Commands/UsageProjectionCommands.php');
    $this->assertStringContainsString('xinshi_ai_usage_cron', file_get_contents($module . '/xinshi_ai_usage.module'));
  }

  public function testPreparedThenObservedBuildsProjectionCostRollupAndWatermark(): void {
    $this->activatePriceBook();
    $this->ingestAll([$this->prepared('att-1'), $this->observed('att-1', 1)]);

    $stats = $this->projection->process(self::SITE);
    $this->assertSame(['locked' => FALSE, 'claimed' => 2, 'applied' => 2, 'audited' => 0,
      'skipped' => 0, 'failed' => 0, 'quarantined' => 0, 'remaining' => 0], $stats);

    $attempt = $this->attempt('att-1');
    $this->assertSame('succeeded', $attempt['state']);
    $this->assertSame(1, (int) $attempt['usage_revision']);
    $this->assertSame(120, (int) $attempt['input_tokens_total']);
    $this->assertSame(64, (int) $attempt['input_tokens_cache_read']);
    $this->assertSame(30, (int) $attempt['output_tokens_total']);
    $this->assertSame('model-a', $attempt['model_id']);
    $this->assertSame(CostRatingService::STATE_RATED_ESTIMATE, $attempt['cost_valuation_state']);
    $this->assertSame('CNY', $attempt['cost_currency']);
    // (120 - 64) * 2 + 64 * 0.2 + 30 * 8 = 112 + 12.8 + 240 = 364.8 -> 365 micros.
    $this->assertSame(365, (int) $attempt['cost_total_micros']);
    $this->assertSame(1_700_000_000_000 - (1_700_000_000_000 % 3_600_000), (int) $attempt['bucket_start']);

    $cells = $this->rollup();
    $this->assertCount(1, $cells);
    $cell = $cells[0];
    $this->assertSame(1, (int) $cell['attempt_count']);
    $this->assertSame(1, (int) $cell['succeeded_count']);
    $this->assertSame(0, (int) $cell['open_count']);
    $this->assertSame(1, (int) $cell['usage_reported_count']);
    $this->assertSame(0, (int) $cell['usage_missing_count']);
    $this->assertSame(120, (int) $cell['input_tokens_total']);
    $this->assertSame(365, (int) $cell['cost_micros']);
    $this->assertSame(1, (int) $cell['cost_rated_count']);
    $this->assertSame('7', $cell['actor_user_id']);
    $this->assertSame('CNY', $cell['currency']);

    $watermark = $this->projection->watermark(self::SITE);
    $this->assertSame(2, $watermark['last_event_id']);
    $this->assertSame(2, $watermark['processed_count']);
    $this->assertSame(1_700_000_004_120, $watermark['data_as_of']);
    $this->assertSame(0, $this->projection->pendingCount());
    $this->assertSame(1, (int) $this->database->select(CostRatingService::TABLE)->countQuery()->execute()->fetchField());
  }

  public function testReplayingAcknowledgedEventsDoesNotDoubleCount(): void {
    $this->activatePriceBook();
    $this->ingestAll([$this->prepared('att-1'), $this->observed('att-1', 1)]);
    $this->projection->process(self::SITE);
    $before = $this->snapshot();

    // Nothing pending: a second run is a no-op.
    $this->assertSame(0, $this->projection->process(self::SITE)['claimed']);
    // Simulate a lost acknowledgement (restore from backup): the same events are
    // claimed again but the projection and rollup stay identical.
    $this->database->update(UsageIngestService::TABLE)->fields(['processed_at' => NULL])->execute();
    $stats = $this->projection->process(self::SITE);
    $this->assertSame(2, $stats['claimed']);
    $this->assertSame(2, $stats['audited']);
    $this->assertSame(0, $stats['applied']);
    $this->assertSame($before, $this->snapshot());
    $this->assertSame(1, (int) $this->database->select(CostRatingService::TABLE)->countQuery()->execute()->fetchField());
  }

  public function testOutOfOrderAndLateRevisionsKeepTheLatestProjection(): void {
    $this->activatePriceBook();
    // Revision 2 arrives first.
    $this->ingestAll([$this->observed('att-1', 2, ['input_tokens_total' => '150', 'provider_total_tokens' => '180'])]);
    $this->projection->process(self::SITE);
    $this->assertSame(150, (int) $this->attempt('att-1')['input_tokens_total']);

    // Then the older revision and the prepared event arrive late.
    $this->ingestAll([$this->observed('att-1', 1), $this->prepared('att-1')]);
    $stats = $this->projection->process(self::SITE);
    $this->assertSame(2, $stats['claimed']);
    $this->assertSame(2, $stats['audited']);
    $attempt = $this->attempt('att-1');
    $this->assertSame(2, (int) $attempt['usage_revision']);
    $this->assertSame('succeeded', $attempt['state']);
    $this->assertSame(150, (int) $attempt['input_tokens_total']);

    $cells = $this->rollup();
    $this->assertCount(1, $cells);
    $this->assertSame(1, (int) $cells[0]['attempt_count']);
    $this->assertSame(150, (int) $cells[0]['input_tokens_total']);
    // Both revisions keep their audit trail as immutable cost entries.
    $revisions = $this->database->select(CostRatingService::TABLE, 'c')
      ->fields('c', ['observation_revision'])->orderBy('observation_revision')->execute()->fetchCol();
    $this->assertSame(['1', '2'], array_map('strval', $revisions));
    $this->assertSame(3, $this->projection->watermark(self::SITE)['processed_count']);
  }

  public function testHigherRevisionReplacesUsageInsteadOfAdding(): void {
    $this->activatePriceBook();
    $this->ingestAll([$this->prepared('att-1'), $this->observed('att-1', 1)]);
    $this->projection->process(self::SITE);
    $this->ingestAll([$this->observed('att-1', 2, ['input_tokens_total' => '140', 'provider_total_tokens' => '170'])]);
    $this->projection->process(self::SITE);

    $cell = $this->rollup()[0];
    $this->assertSame(1, (int) $cell['attempt_count']);
    $this->assertSame(140, (int) $cell['input_tokens_total']);
    // (140 - 64) * 2 + 12.8 + 240 = 404.8 -> 405.
    $this->assertSame(405, (int) $cell['cost_micros']);
    $this->assertSame(405, (int) $this->attempt('att-1')['cost_total_micros']);
  }

  public function testRebuildFromFactsYieldsTheSameResult(): void {
    $this->activatePriceBook();
    $this->ingestAll([
      $this->prepared('att-1'), $this->observed('att-1', 1),
      $this->prepared('att-2', ['logical_call_id' => 'lc-2', 'context' => ['actor_user_id' => NULL, 'stage' => 'executor']]),
      $this->observed('att-2', 1, [], ['logical_call_id' => 'lc-2', 'outcome' => 'failed',
        'context' => ['actor_user_id' => NULL, 'stage' => 'executor']]),
      $this->observed('att-3', 1, NULL, ['logical_call_id' => 'lc-3', 'outcome' => 'not_sent',
        'dispatch_state' => 'not_sent', 'error_code' => 'budget_exceeded']),
    ]);
    $this->projection->process(self::SITE);
    $before = $this->snapshot();
    $this->assertCount(3, $this->rollup());

    $stats = $this->projection->rebuild(self::SITE);
    $this->assertSame(5, $stats['claimed']);
    $this->assertSame(5, $stats['applied']);
    $this->assertSame(0, $stats['remaining']);
    $this->assertSame($before, $this->snapshot());
    $this->assertSame(5, $this->projection->watermark(self::SITE)['processed_count']);
  }

  public function testMissingUsageAndNotSentAreCountedNotZeroed(): void {
    $this->activatePriceBook();
    $this->ingestAll([
      $this->observed('att-1', 1, NULL, ['outcome' => 'failed', 'error_code' => 'http_500']),
      $this->observed('att-2', 1, ['quality' => 'invalid', 'invalid_reason' => 'cache exceeds input'],
        ['logical_call_id' => 'lc-2']),
      $this->observed('att-3', 1, NULL, ['logical_call_id' => 'lc-3', 'outcome' => 'not_sent',
        'dispatch_state' => 'not_sent']),
      $this->observed('att-4', 1, NULL, ['logical_call_id' => 'lc-4', 'outcome' => 'aborted',
        'dispatch_state' => 'unknown']),
    ]);
    $this->projection->process(self::SITE);

    $this->assertSame('unknown', $this->attempt('att-4')['state']);
    $this->assertNull($this->attempt('att-3')['cost_valuation_state']);
    $this->assertSame('missing_usage', $this->attempt('att-1')['cost_unpriced_reason']);
    $this->assertSame('invalid_usage', $this->attempt('att-2')['cost_unpriced_reason']);

    $totals = ['attempt_count' => 0, 'failed_count' => 0, 'unknown_count' => 0, 'not_sent_count' => 0,
      'usage_missing_count' => 0, 'cost_unpriced_count' => 0, 'cost_rated_count' => 0,
      'input_tokens_total' => 0, 'cost_micros' => 0];
    foreach ($this->rollup() as $cell) {
      foreach ($totals as $key => $value) {
        $totals[$key] = $value + (int) $cell[$key];
      }
    }
    $this->assertSame(['attempt_count' => 4, 'failed_count' => 1, 'unknown_count' => 1,
      'not_sent_count' => 1, 'usage_missing_count' => 3, 'cost_unpriced_count' => 3,
      'cost_rated_count' => 0, 'input_tokens_total' => 0, 'cost_micros' => 0], $totals);
  }

  public function testWithoutPriceBookCostIsUnpricedNotFree(): void {
    $this->ingestAll([$this->prepared('att-1'), $this->observed('att-1', 1)]);
    $this->projection->process(self::SITE);
    $attempt = $this->attempt('att-1');
    $this->assertSame(CostRatingService::STATE_UNPRICED, $attempt['cost_valuation_state']);
    $this->assertSame('no_price_book', $attempt['cost_unpriced_reason']);
    $this->assertNull($attempt['cost_total_micros']);
    $cell = $this->rollup()[0];
    $this->assertSame(UsageProjectionService::NONE, $cell['currency']);
    $this->assertSame(1, (int) $cell['cost_unpriced_count']);
    $this->assertSame(120, (int) $cell['input_tokens_total']);
  }

  public function testOnlyOneConsumerRunsAtATime(): void {
    $this->ingestAll([$this->prepared('att-1')]);
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->method('acquire')->willReturn(FALSE);
    $lock->expects($this->never())->method('release');
    $stats = $this->projectionWith($lock)->process(self::SITE);
    $this->assertTrue($stats['locked']);
    $this->assertSame(0, $stats['claimed']);
    $this->assertSame(1, $this->projection->pendingCount(self::SITE));
  }

  public function testProcessingIsScopedBySiteAndWatermarkPerSite(): void {
    $this->ingestAll([$this->prepared('att-1')]);
    $other = ['site_id' => 'site-b', 'event_id' => 'att-9:prepared:0', 'attempt_id' => 'att-9'] + $this->prepared('att-9');
    $this->assertSame('accepted', $this->ingest->ingest([$other], 'chat-node', 'site-b')[0]['status']);

    $this->assertSame(1, $this->projection->process('site-b')['applied']);
    $this->assertSame(1, $this->projection->pendingCount(self::SITE));
    $this->assertNull($this->projection->watermark(self::SITE));
    $this->assertSame(1, $this->projection->watermark('site-b')['processed_count']);
    $this->assertSame(1, $this->projection->process()['applied']);
    $this->assertSame(0, $this->projection->pendingCount());
  }

  public function testFailingEventIsQuarantinedAfterRepeatedFailuresAndDoesNotBlockOthers(): void {
    $this->activatePriceBook();
    $this->ingestAll([$this->observed('att-1', 1)]);
    // A conflicting immutable cost entry for this revision makes rating throw.
    $this->costRating->rateAttempt(self::SITE, 'att-1', 1,
      ['account_ref' => 'xinshi', 'requested_model' => 'model-a', 'resolved_model' => 'model-a'],
      NULL, 1_700_000_004_120);
    $this->ingestAll([$this->prepared('att-2', ['logical_call_id' => 'lc-2'])]);

    for ($run = 1; $run < UsageProjectionService::MAX_FAILURES; $run++) {
      $stats = $this->projection->process(self::SITE);
      $this->assertSame(1, $stats['failed'], "run $run");
      $this->assertSame(0, $stats['quarantined'], "run $run");
      $this->assertSame(1, $stats['remaining'], "run $run");
    }
    // The healthy event behind it was applied on the first run.
    $this->assertSame('prepared', $this->attempt('att-2')['state']);
    $this->assertSame(1, $this->projection->watermark(self::SITE)['processed_count']);

    $stats = $this->projection->process(self::SITE);
    $this->assertSame(1, $stats['failed']);
    $this->assertSame(1, $stats['quarantined']);
    $this->assertSame(0, $stats['remaining']);
    $event = $this->database->select(UsageIngestService::TABLE, 'e')->fields('e')
      ->condition('attempt_id', 'att-1')->execute()->fetchAssoc();
    $this->assertSame(UsageProjectionService::MAX_FAILURES, (int) $event['failure_count']);
    $this->assertNotNull($event['quarantined_at']);
    $this->assertStringContainsString('cost_conflict', $event['last_error']);
    $this->assertNull($event['processed_at']);
    // Quarantined events are no longer claimed.
    $this->assertSame(0, $this->projection->process(self::SITE)['claimed']);

    // After the operator fixes the cause, release returns them to the queue.
    $this->database->delete(CostRatingService::TABLE)->execute();
    $this->assertSame(1, $this->projection->releaseQuarantined(self::SITE));
    $stats = $this->projection->process(self::SITE);
    $this->assertSame(1, $stats['applied']);
    $this->assertSame(0, $stats['quarantined']);
    $this->assertSame('succeeded', $this->attempt('att-1')['state']);
  }

  public function testRebuildRetriesQuarantinedEvents(): void {
    $this->ingestAll([$this->prepared('att-1')]);
    $this->database->update(UsageIngestService::TABLE)
      ->fields(['failure_count' => 5, 'last_error' => 'x', 'quarantined_at' => 1])->execute();
    $stats = $this->projection->rebuild(self::SITE);
    $this->assertSame(1, $stats['applied']);
    $this->assertSame(0, $stats['quarantined']);
  }

  private function projectionWith(LockBackendInterface $lock): UsageProjectionService {
    return new UsageProjectionService($this->database, $this->costRating, $lock, $this->time, new NullLogger());
  }

  private function activatePriceBook(): void {
    // 2 CNY / 0.2 CNY / 1 CNY / 8 CNY per million tokens, in micros.
    $rates = json_encode(['accounts' => ['xinshi' => ['models' => ['model-a' => [
      'per_million_input' => 2_000_000, 'per_million_cache_read' => 200_000,
      'per_million_cache_write' => 1_000_000, 'per_million_output' => 8_000_000,
    ]]]]]);
    // Effective before the sample events (2023-11-14) so they are rated.
    $version = $this->priceBook->createDraft(self::SITE, PriceBookService::KIND_SUPPLIER_CHAT, 'v1', 'CNY',
      $rates, 1_600_000_000_000);
    $this->now = 1_600_000_000;
    $this->priceBook->activate($version, self::SITE, PriceBookService::KIND_SUPPLIER_CHAT);
    $this->now = 1_700_000_000;
  }

  private function ingestAll(array $events): void {
    foreach ($this->ingest->ingest($events, 'chat-node', self::SITE) as $receipt) {
      $this->assertSame('accepted', $receipt['status'], json_encode($receipt));
    }
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

  /**
   * Projection and rollup contents without ids and timestamps, for equality checks.
   */
  private function snapshot(): array {
    $strip = static function (array $rows): array {
      $result = [];
      foreach ($rows as $row) {
        unset($row['id'], $row['updated_at'], $row['last_event_id']);
        ksort($row);
        $result[] = $row;
      }
      usort($result, static fn(array $a, array $b) => strcmp(json_encode($a), json_encode($b)));
      return $result;
    };
    $attempts = $this->database->select(UsageProjectionService::ATTEMPT_TABLE, 'a')
      ->fields('a')->execute()->fetchAll(\PDO::FETCH_ASSOC);
    return ['attempts' => $strip($attempts), 'rollup' => $strip($this->rollup())];
  }

  private function prepared(string $attemptId, array $overrides = []): array {
    $event = $this->event($attemptId);
    $event['event_id'] = "$attemptId:prepared:0";
    $event['event_type'] = 'attempt.prepared';
    $event['observation_revision'] = 0;
    $event['occurred_at'] = '2023-11-14T22:13:20.000Z';
    $event['provider']['resolved_model'] = NULL;
    $event['provider']['gateway_request_id'] = NULL;
    $event['dispatch_state'] = 'not_sent';
    $event['outcome'] = NULL;
    $event['usage'] = NULL;
    $event['timing']['first_token_at'] = NULL;
    $event['timing']['finished_at'] = NULL;
    return $this->merge($event, $overrides);
  }

  /**
   * @param array|null $usage
   *   Overrides for the usage block; NULL removes the block entirely.
   */
  private function observed(string $attemptId, int $revision, ?array $usage = [], array $overrides = []): array {
    $event = $this->event($attemptId);
    $event['event_id'] = "$attemptId:observed:$revision";
    $event['observation_revision'] = $revision;
    $event['usage'] = $usage === NULL ? NULL : array_replace($event['usage'], $usage);
    return $this->merge($event, $overrides);
  }

  private function merge(array $event, array $overrides): array {
    foreach ($overrides as $key => $value) {
      $event[$key] = is_array($value) && is_array($event[$key] ?? NULL) ? array_replace($event[$key], $value) : $value;
    }
    return $event;
  }

  private function event(string $attemptId): array {
    return [
      'schema_version' => 1,
      'event_id' => "$attemptId:observed:1",
      'producer_id' => 'chat-node',
      'site_id' => self::SITE,
      'event_type' => 'attempt.observed',
      'operation_id' => 'op-1',
      'authorization_id' => NULL,
      'attempt_id' => $attemptId,
      'logical_call_id' => 'lc-1',
      'observation_revision' => 1,
      'occurred_at' => '2023-11-14T22:13:24.120Z',
      'context' => ['billing_account_id' => NULL, 'actor_user_id' => '7', 'feature' => 'common',
        'stage' => 'classifier', 'attempt_no' => 1, 'payer' => 'platform',
        'chat_run_id' => 'run-1', 'task_id' => 'task-1', 'chat_id' => 'chat-1'],
      'provider' => ['account_ref' => 'xinshi', 'requested_model' => 'model-a',
        'resolved_model' => 'model-a', 'gateway_request_id' => 'gw-1', 'provider_request_id' => NULL],
      'dispatch_state' => 'sent',
      'outcome' => 'succeeded',
      'error_code' => NULL,
      'usage' => ['normalizer_version' => 'chat-completions-inclusive-v1', 'quality' => 'reported',
        'input_tokens_total' => '120', 'input_tokens_cache_read' => '64', 'input_tokens_cache_write' => NULL,
        'output_tokens_total' => '30', 'output_tokens_reasoning' => NULL, 'provider_total_tokens' => '150',
        'images_generated' => NULL, 'raw_usage' => ['prompt_tokens' => 120, 'completion_tokens' => 30,
          'total_tokens' => 150, 'prompt_tokens_details.cached_tokens' => 64],
        'raw_usage_hash' => str_repeat('a', 64)],
      'timing' => ['started_at' => '2023-11-14T22:13:20.000Z', 'first_token_at' => '2023-11-14T22:13:20.480Z',
        'finished_at' => '2023-11-14T22:13:24.100Z'],
    ];
  }

}
