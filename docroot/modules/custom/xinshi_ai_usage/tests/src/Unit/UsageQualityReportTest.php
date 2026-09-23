<?php

declare(strict_types=1);

namespace Drupal\Tests\xinshi_ai_usage\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\KeyValueStore\KeyValueMemoryFactory;
use Drupal\Core\PrivateKey;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Site\Settings;
use Drupal\xinshi_ai_usage\Controller\SiteUsageReportController;
use Drupal\xinshi_ai_usage\Service\CostRatingService;
use Drupal\xinshi_ai_usage\Service\PriceBookService;
use Drupal\xinshi_ai_usage\Service\ProducerVault;
use Drupal\xinshi_ai_usage\Service\SiteUsageReportService;
use Drupal\xinshi_ai_usage\Service\UsageProjectionService;
use Drupal\xinshi_ai_usage\Service\UsageQualityReportService;
use Drupal\xinshi_ai_usage\Service\UsageReportException;
use Drupal\xinshi_ai_usage\Service\UsageReportService;
use Drupal\xinshi_ai_usage\Service\UsageIngestService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Yaml\Yaml;

/**
 * The quality report and its work queue stay read-only, deduplicated and
 * permission-gated (UB3.5a).
 *
 * seedScenario() plants every problem shape by hand; the expectations below
 * are derived from that table, so a change in the seed must be reflected in
 * the numbers. Overlapping attempts are the point of several rows: one
 * unknown attempt with missing usage and no price appears in all three
 * families but only once in the queue.
 */
final class UsageQualityReportTest extends TestCase {

  private const SITE = 'site-a';
  private const ME = '7';
  /** 2023-11-14T22:13:20Z. */
  private const T0 = 1_700_000_000_000;
  private const HOUR = 3_600_000;
  private const MINUTE = 60_000;
  private const DAY = 86_400_000;
  private const WINDOW = ['from' => '2023-11-14', 'to' => '2023-11-18'];

  private Connection $database;
  private UsageProjectionService $projection;
  private SiteUsageReportService $siteReports;
  private UsageQualityReportService $quality;
  private int $now = 0;
  private int $sequence = 0;

  protected function setUp(): void {
    parent::setUp();
    Database::addConnectionInfo('quality_report_test', 'default', [
      'driver' => 'sqlite', 'database' => ':memory:',
      'namespace' => 'Drupal\\sqlite\\Driver\\Database\\sqlite',
    ]);
    $this->database = Database::getConnection('default', 'quality_report_test');
    foreach (xinshi_ai_usage_schema() as $table => $spec) {
      $this->database->schema()->createTable($table, $spec);
    }
    $time = $this->createMock(TimeInterface::class);
    $this->now = self::T0 + 3 * self::DAY;
    $time->method('getCurrentMicroTime')->willReturnCallback(fn(): float => $this->now / 1000);
    $priceBook = new PriceBookService($this->database, $time);
    $this->projection = new UsageProjectionService($this->database,
      new CostRatingService($this->database, $priceBook, $time),
      new \Drupal\Core\Lock\NullLockBackend(), $time, new NullLogger());
    $userReports = new UsageReportService($this->database, $this->projection);
    $this->siteReports = new SiteUsageReportService($this->database, $this->projection,
      $userReports, $priceBook);
    $this->quality = new UsageQualityReportService($this->database,
      $this->projection, $this->siteReports);
  }

  protected function tearDown(): void {
    Database::removeConnection('quality_report_test');
    parent::tearDown();
  }

  public function testRoutesServicesAndPermissionsAreRegistered(): void {
    $module = dirname(__DIR__, 3);
    $services = Yaml::parseFile($module . '/xinshi_ai_usage.services.yml')['services'];
    $this->assertSame(UsageQualityReportService::class,
      $services['xinshi_ai_usage.quality_report']['class']);

    $routes = Yaml::parseFile($module . '/xinshi_ai_usage.routing.yml');
    foreach (['quality' => 'quality', 'quality_items' => 'qualityItems'] as $name => $method) {
      $route = $routes["xinshi_ai_usage.admin.$name"];
      $this->assertSame("/api/v3/ai/admin/reports/quality" . ($name === 'quality_items' ? '/items' : ''),
        $route['path'], $name);
      $this->assertSame(['GET'], $route['methods'], $name);
      $this->assertSame('view site ai usage', $route['requirements']['_permission'], $name);
      $this->assertTrue($route['options']['no_cache'], $name);
      $this->assertSame(['oauth2', 'cookie'], $route['options']['_auth'], $name);
      $this->assertSame('\Drupal\xinshi_ai_usage\Controller\SiteUsageReportController::' . $method,
        $route['defaults']['_controller'], $name);
    }
  }

  public function testFamiliesCountTheirOwnMembers(): void {
    $this->seedScenario();
    $report = $this->quality->quality(self::SITE, self::WINDOW, TRUE, $this->now);

    // Family A: three unknown outcomes (att-unknown, att-unknown-img, att-multi)
    // and one intent open past the threshold (att-stuck); att-fresh (10
    // minutes old) is still within the default 60-minute threshold.
    $incomplete = $report['families']['incomplete'];
    $this->assertSame(3, $incomplete['unknown_attempt']['count']);
    $this->assertSame(1, $incomplete['stuck_intent']['count']);
    $this->assertSame('2023-11-14T23:13:20.000+00:00', $incomplete['unknown_attempt']['oldest_started_at']);

    // Splits keep the producer and model so systemic gaps stand out.
    $byProducer = [];
    foreach ($incomplete['by_producer'] as $row) {
      $byProducer[$row['producer']] = $row;
    }
    $this->assertSame([2, 1], [$byProducer['chat-node']['unknown_attempt'],
      $byProducer['cms-image']['unknown_attempt']]);
    $this->assertSame(1, $byProducer['chat-node']['stuck_intent']);

    // Family B: three missing (att-usage-missing, att-multi, att-blocked-usage)
    // and one invalid (att-usage-invalid), split by model and account.
    $usageUnknown = $report['families']['usage_unknown'];
    $this->assertSame([3, 1], [$usageUnknown['missing']['count'], $usageUnknown['invalid']['count']]);
    $byModel = [];
    foreach ($usageUnknown['by_model'] as $row) {
      $byModel[$row['model']] = $row;
    }
    // One row per model, keyed by model_id: att-usage-missing keeps the
    // default model-a, att-multi is on model-b, att-blocked-usage on model-e.
    $this->assertSame([1, 1, 1], [$byModel['model-a']['missing'], $byModel['model-b']['missing'],
      $byModel['model-e']['missing']]);
    $this->assertSame(1, $byModel['model-d']['invalid']);

    // Family C: every reason once; actionable 2, blocked 1, by design 1.
    $unpriced = $report['families']['unpriced'];
    $this->assertSame([['reason' => 'customer_key', 'attempts' => 1],
      ['reason' => 'missing_usage', 'attempts' => 1],
      ['reason' => 'no_price_book', 'attempts' => 1],
      ['reason' => 'unknown_model', 'attempts' => 1]],
      array_map(static fn(array $row): array => ['reason' => $row['reason'],
        'attempts' => $row['attempts']], $unpriced['by_reason']),
      'reasons are sorted by attempts desc then reason asc');
    $this->assertSame([2, 1, 1], [$unpriced['actionable_total'],
      $unpriced['blocked_by_usage_total'], $unpriced['by_design_total']]);
  }

  public function testQueueDeduplicatesAcrossFamilies(): void {
    $this->seedScenario();
    $report = $this->quality->quality(self::SITE, self::WINDOW, TRUE, $this->now);

    // 8 attempts carry at least one issue; the 10 family members overlap on
    // att-multi (all three families) and att-blocked-usage (B and blocked C),
    // so the queue is smaller than the family sum.
    $this->assertSame(8, $report['queue']['total']);
    $this->assertSame(['unknown_attempt' => 3, 'stuck_intent' => 1, 'usage_missing' => 2,
      'usage_invalid' => 1, 'unpriced' => 1], $report['queue']['by_issue']);
    $this->assertTrue($report['queue']['families_overlap']);

    // BYOK calls and reasons blocked by unknown usage never become work items.
    $this->assertNotContains('att-customer-key', array_column(
      $this->quality->items(self::SITE, self::WINDOW + ['limit' => '200'], TRUE, $this->now)['rows'],
      'attempt_id'));
  }

  public function testWithoutCostsPermissionTheUnpricedFamilyIsHidden(): void {
    $this->seedScenario();
    $report = $this->quality->quality(self::SITE, self::WINDOW, FALSE, $this->now);

    $this->assertArrayNotHasKey('unpriced', $report['families']);
    // att-unpriced leaves the queue entirely; the rest is unaffected.
    $this->assertSame(7, $report['queue']['total']);
    $this->assertSame(0, $report['queue']['by_issue']['unpriced']);
  }

  public function testStuckThresholdIsValidatedAndApplied(): void {
    $this->seedScenario();

    foreach (['4' => 'below', '1441' => 'above', 'soon' => 'not a number'] as $value => $label) {
      try {
        $this->quality->quality(self::SITE, self::WINDOW + ['stuck_after_minutes' => $value], TRUE, $this->now);
        $this->fail("expected invalid_request for stuck_after_minutes $label");
      }
      catch (UsageReportException $e) {
        $this->assertSame('invalid_request', $e->reportCode, $label);
      }
    }

    // Boundaries accepted: at 5 minutes the fresh in-flight intent (10
    // minutes old) joins the stuck family; at 1440 minutes neither open
    // intent is old enough, so nothing is stuck.
    foreach (['5' => 2, '1440' => 0] as $minutes => $expected) {
      $report = $this->quality->quality(self::SITE,
        self::WINDOW + ['stuck_after_minutes' => $minutes], TRUE, $this->now);
      $this->assertSame($expected, $report['families']['incomplete']['stuck_intent']['count'],
        "stuck_after_minutes=$minutes");
    }
  }

  public function testAttemptGapsDetectMissingEventsOnly(): void {
    $this->seedScenario();
    $report = $this->quality->quality(self::SITE, self::WINDOW, TRUE, $this->now);
    $gaps = $report['attempt_gaps'];

    // op-gap misses attempt 2 internally; op-lead misses 1 and 2 at the front.
    // op-cut only looks like a gap inside the window: its attempt 1 started
    // before the window and exists, so the re-check drops it.
    $this->assertSame(2, $gaps['total']);
    $this->assertSame([
      ['operation_id' => 'op-lead', 'missing_attempt_numbers' => [1, 2]],
      ['operation_id' => 'op-gap', 'missing_attempt_numbers' => [2]],
    ], array_map(static fn(array $row): array => [
      'operation_id' => $row['operation_id'],
      'missing_attempt_numbers' => $row['missing_attempt_numbers'],
    ], $gaps['rows']), 'rows are ordered by the group\'s newest attempt, newest first');
    $this->assertSame(['investigate'], array_unique(array_column($gaps['rows'], 'resolution')));
  }

  public function testAttemptGapsIgnoreReportFiltersOnRecheck(): void {
    // A fallback retry on another model is part of the same logical call: a
    // model filter must not turn the filtered-out attempt 1 into a gap.
    $this->attempt('op-fallback', 'att-fb-1', [
      'logical_call_id' => 't1', 'model_id' => 'model-a', 'started_at' => self::T0 + self::HOUR,
    ]);
    $this->attempt('op-fallback', 'att-fb-2', [
      'logical_call_id' => 't1', 'attempt_no' => 2, 'model_id' => 'model-b',
      'started_at' => self::T0 + self::HOUR + 1,
    ]);
    // A real hole inside the filtered model is still reported.
    $this->attempt('op-hole', 'att-hole-1', [
      'logical_call_id' => 't1', 'model_id' => 'model-b', 'started_at' => self::T0 + 2 * self::HOUR,
    ]);
    $this->attempt('op-hole', 'att-hole-3', [
      'logical_call_id' => 't1', 'attempt_no' => 3, 'model_id' => 'model-b',
      'started_at' => self::T0 + 2 * self::HOUR + 1,
    ]);

    $report = $this->quality->quality(self::SITE, self::WINDOW + ['model' => 'model-b'], TRUE, $this->now);
    $gaps = $report['attempt_gaps'];
    $this->assertSame(1, $gaps['total']);
    $this->assertSame('op-hole', $gaps['rows'][0]['operation_id']);
    $this->assertSame([2], $gaps['rows'][0]['missing_attempt_numbers']);
    $this->assertFalse($gaps['candidates_truncated']);
  }

  public function testPipelineReportsBacklogAndQuarantine(): void {
    $this->seedScenario();
    $report = $this->quality->quality(self::SITE, self::WINDOW, TRUE, $this->now);
    $pipeline = $report['pipeline'];

    // One pending event received 90 minutes ago; two quarantined events with
    // distinct errors; the processed event counts for neither.
    $this->assertSame(1, $pipeline['pending_events']);
    $this->assertSame(90, $pipeline['oldest_pending_age_minutes']);
    $this->assertSame(2, $pipeline['quarantined_events']);
    $this->assertSame(180, $pipeline['oldest_quarantined_age_minutes']);
    $this->assertSame([['error' => 'Error A', 'events' => 1], ['error' => 'Error B', 'events' => 1]],
      $pipeline['quarantine_reasons']);

    $quarantined = $report['quarantined_events'];
    $this->assertSame(2, $quarantined['total']);
    $this->assertSame('ev-q2', $quarantined['rows'][0]['event_id'], 'newest quarantine first');
    $this->assertSame(['release_quarantine'], array_unique(array_column($quarantined['rows'], 'resolution')));
  }

  public function testItemsAreTheDeduplicatedQueueWithResolutionPointers(): void {
    $this->seedScenario();

    $queue = $this->quality->items(self::SITE, self::WINDOW + ['limit' => '200'], TRUE, $this->now);
    // The SQL predicate and the PHP classification must agree: every row is
    // classified, and re-classifying each row yields the same primary issue.
    $this->assertSame(8, $queue['total']);
    $this->assertSame([
      'att-stuck', 'att-blocked-usage', 'att-unpriced', 'att-multi', 'att-usage-invalid',
      'att-usage-missing', 'att-unknown-img', 'att-unknown',
    ], array_column($queue['rows'], 'attempt_id'), 'ordered by started_at desc');

    $expected = [
      'att-stuck' => ['stuck_intent', 'node_reconcile'],
      'att-blocked-usage' => ['usage_missing', 'investigate'],
      'att-unpriced' => ['unpriced', 'price_book'],
      'att-multi' => ['unknown_attempt', 'node_reconcile'],
      'att-usage-invalid' => ['usage_invalid', 'investigate'],
      'att-usage-missing' => ['usage_missing', 'investigate'],
      'att-unknown-img' => ['unknown_attempt', 'cms_reconcile'],
      'att-unknown' => ['unknown_attempt', 'node_reconcile'],
    ];
    foreach ($queue['rows'] as $row) {
      [$issue, $resolution] = $expected[$row['attempt_id']];
      $this->assertSame([$issue, $resolution], [$row['primary_issue'], $row['resolution']],
        $row['attempt_id']);
    }

    // Without costs permission the unpriced reason is not part of a row, even
    // on a row that is queued for another issue.
    $withoutCosts = $this->quality->items(self::SITE, self::WINDOW + ['limit' => '200'], FALSE, $this->now);
    $this->assertNotContains('att-unpriced', array_column($withoutCosts['rows'], 'attempt_id'));
    $blocked = NULL;
    foreach ($withoutCosts['rows'] as $row) {
      if ($row['attempt_id'] === 'att-blocked-usage') {
        $blocked = $row;
      }
    }
    $this->assertNotNull($blocked, 'att-blocked-usage stays queued as usage_missing without costs permission');
    $this->assertNull($blocked['cost_unpriced_reason']);
  }

  public function testItemsIssueFilterAndCursorPagination(): void {
    $this->seedScenario();

    // Issue filter narrows the queue to one primary issue.
    $unknowns = $this->quality->items(self::SITE,
      self::WINDOW + ['issue' => 'unknown_attempt', 'limit' => '200'], TRUE, $this->now);
    $this->assertSame(3, $unknowns['total']);
    $this->assertSame(['att-multi', 'att-unknown-img', 'att-unknown'],
      array_column($unknowns['rows'], 'attempt_id'));

    $unpriced = $this->quality->items(self::SITE,
      self::WINDOW + ['issue' => 'unpriced', 'limit' => '200'], TRUE, $this->now);
    $this->assertSame(1, $unpriced['total']);
    $this->assertSame('price_book', $unpriced['rows'][0]['resolution']);

    // Unknown issue refused; the unpriced issue without costs is a refusal too.
    foreach ([[TRUE, 'bogus_issue', 'invalid_request'], [FALSE, 'unpriced', 'forbidden']] as
      [$withCosts, $issue, $code]) {
      try {
        $this->quality->items(self::SITE,
          self::WINDOW + ['issue' => $issue], $withCosts, $this->now);
        $this->fail("expected $code for issue=$issue");
      }
      catch (UsageReportException $e) {
        $this->assertSame($code, $e->reportCode);
      }
    }

    // Cursor pagination walks the queue newest first without repeats.
    $page1 = $this->quality->items(self::SITE, self::WINDOW + ['limit' => '3'], TRUE, $this->now);
    $this->assertTrue($page1['truncated']);
    $this->assertSame(['att-stuck', 'att-blocked-usage', 'att-unpriced'],
      array_column($page1['rows'], 'attempt_id'));
    $this->assertSame(8, $page1['total']);

    $page2 = $this->quality->items(self::SITE,
      self::WINDOW + ['limit' => '3', 'cursor' => $page1['next_cursor']], TRUE, $this->now);
    $this->assertSame(['att-multi', 'att-usage-invalid', 'att-usage-missing'],
      array_column($page2['rows'], 'attempt_id'));

    $page3 = $this->quality->items(self::SITE,
      self::WINDOW + ['limit' => '3', 'cursor' => $page2['next_cursor']], TRUE, $this->now);
    $this->assertFalse($page3['truncated']);
    $this->assertNull($page3['next_cursor']);
    $this->assertSame(['att-unknown-img', 'att-unknown'], array_column($page3['rows'], 'attempt_id'));

    // A damaged cursor is refused, not silently ignored.
    try {
      $this->quality->items(self::SITE, self::WINDOW + ['cursor' => 'not-a-cursor'], TRUE, $this->now);
      $this->fail('expected invalid_request for a damaged cursor');
    }
    catch (UsageReportException $e) {
      $this->assertSame('invalid_request', $e->reportCode);
    }
  }

  public function testOversizedWindowIsRefused(): void {
    $this->seedScenario();
    try {
      $this->quality->quality(self::SITE,
        ['from' => '2022-11-14', 'to' => '2023-11-18'], TRUE, $this->now);
      $this->fail('expected range_too_large');
    }
    catch (UsageReportException $e) {
      $this->assertSame('range_too_large', $e->reportCode);
    }
  }

  public function testControllerGatesAndCostsHiding(): void {
    $this->seedScenario();
    $vault = $this->vault();
    $vault->set('chat-node', 'secret', self::SITE, 1);

    // No permission, anonymous, site param, no registered site.
    $noAccess = $this->controller('99', $vault, ['view site ai usage' => FALSE])
      ->quality(Request::create('/x', 'GET', self::WINDOW));
    $this->assertSame(403, $noAccess->getStatusCode());
    $anon = $this->controller('0', $vault, ['view site ai usage' => TRUE], TRUE)
      ->quality(Request::create('/x'));
    $this->assertSame(403, $anon->getStatusCode());
    $withSite = $this->controller('99', $vault, ['view site ai usage' => TRUE])
      ->quality(Request::create('/x', 'GET', ['site' => 'other']));
    $this->assertSame(403, $withSite->getStatusCode());
    $noSite = $this->controller('99', $this->vault(), ['view site ai usage' => TRUE])
      ->quality(Request::create('/x'));
    $this->assertSame(503, $noSite->getStatusCode());

    // Site permission only: 200 with the unpriced family hidden.
    $onlySite = $this->controller('99', $vault,
      ['view site ai usage' => TRUE, 'view ai supplier costs' => FALSE])
      ->quality(Request::create('/x', 'GET', self::WINDOW));
    $this->assertSame(200, $onlySite->getStatusCode());
    $body = json_decode((string) $onlySite->getContent(), TRUE);
    $this->assertArrayNotHasKey('unpriced', $body['families']);
    $this->assertSame(7, $body['queue']['total']);
    $this->assertArrayHasKey('pipeline', $body);

    // Both permissions: the family appears.
    $both = $this->controller('99', $vault,
      ['view site ai usage' => TRUE, 'view ai supplier costs' => TRUE])
      ->quality(Request::create('/x', 'GET', self::WINDOW));
    $body = json_decode((string) $both->getContent(), TRUE);
    $this->assertSame(2, $body['families']['unpriced']['actionable_total']);
    $this->assertSame(8, $body['queue']['total']);

    // Asking for the unpriced queue without costs permission is a 403, not an
    // empty answer that would read as "no problems".
    $refused = $this->controller('99', $vault,
      ['view site ai usage' => TRUE, 'view ai supplier costs' => FALSE])
      ->qualityItems(Request::create('/x', 'GET', self::WINDOW + ['issue' => 'unpriced']));
    $this->assertSame(403, $refused->getStatusCode());
    $this->assertSame('forbidden', json_decode((string) $refused->getContent(), TRUE)['code']);

    $items = $this->controller('99', $vault,
      ['view site ai usage' => TRUE, 'view ai supplier costs' => TRUE])
      ->qualityItems(Request::create('/x', 'GET', self::WINDOW + ['limit' => '3']));
    $body = json_decode((string) $items->getContent(), TRUE);
    $this->assertSame(8, $body['total']);
    $this->assertSame('att-stuck', $body['rows'][0]['attempt_id']);

    // An invalid issue is a 400 through the controller as well.
    $badIssue = $this->controller('99', $vault, ['view site ai usage' => TRUE])
      ->qualityItems(Request::create('/x', 'GET', self::WINDOW + ['issue' => 'bogus']));
    $this->assertSame(400, $badIssue->getStatusCode());
  }

  /**
   * Plants every problem shape once, plus clean rows that must stay quiet.
   *
   * Attempts marked (queue) carry at least one issue; the others prove the
   * report does not invent problems:
   * - att-unknown, att-unknown-img, att-multi: unknown outcomes;
   * - att-stuck: open past the default threshold; att-fresh: still within it;
   * - att-usage-missing, att-usage-invalid: family B;
   * - att-multi also lacks a price (unknown_model) and is the dedup case;
   * - att-blocked-usage: usage missing AND unpriced missing_usage (blocked C);
   * - att-customer-key: BYOK, by design never queued;
   * - op-gap / op-lead: real attempt-number gaps; op-cut: window artifact.
   */
  private function seedScenario(): void {
    // att-multi is the overlap: unknown + missing usage + unknown_model. Its
    // only attempt keeps number 1, so the gap check does not read the row as a
    // leading hole as well.
    $this->attempt('op-a', 'att-unknown', [
      'logical_call_id' => 'classifier', 'state' => 'unknown', 'started_at' => self::T0 + self::HOUR,
    ]);
    $this->attempt('op-b', 'att-unknown-img', [
      'logical_call_id' => 'image', 'producer_id' => 'cms-image', 'model_id' => 'qwen-image',
      'state' => 'unknown', 'dispatch_state' => 'unknown', 'started_at' => self::T0 + 2 * self::HOUR,
    ]);
    $this->attempt('op-c', 'att-stuck', [
      'logical_call_id' => 'executor', 'model_id' => 'model-c', 'state' => 'in_flight',
      'started_at' => $this->now - 2 * self::HOUR,
    ]);
    $this->attempt('op-d', 'att-fresh', [
      'logical_call_id' => 'planner', 'state' => 'in_flight',
      'started_at' => $this->now - 10 * self::MINUTE,
    ]);
    $this->attempt('op-e', 'att-usage-missing', [
      'state' => 'succeeded', 'usage_quality' => 'missing', 'started_at' => self::T0 + 3 * self::HOUR,
    ]);
    $this->attempt('op-f', 'att-usage-invalid', [
      'state' => 'failed', 'usage_quality' => 'invalid', 'model_id' => 'model-d',
      'started_at' => self::T0 + 4 * self::HOUR,
    ]);
    $this->attempt('op-g', 'att-multi', [
      'logical_call_id' => 'critic', 'model_id' => 'model-b',
      'state' => 'unknown', 'usage_quality' => 'missing',
      'cost_valuation_state' => 'unpriced', 'cost_unpriced_reason' => 'unknown_model',
      'started_at' => self::T0 + 5 * self::HOUR,
    ]);
    $this->attempt('op-h', 'att-unpriced', [
      'state' => 'succeeded', 'usage_quality' => 'reported',
      'cost_valuation_state' => 'unpriced', 'cost_unpriced_reason' => 'no_price_book',
      'started_at' => self::T0 + 6 * self::HOUR,
    ]);
    $this->attempt('op-i', 'att-customer-key', [
      'state' => 'succeeded', 'usage_quality' => 'reported', 'payer' => 'customer_key',
      'cost_valuation_state' => 'unpriced', 'cost_unpriced_reason' => 'customer_key',
      'started_at' => self::T0 + 7 * self::HOUR,
    ]);
    $this->attempt('op-j', 'att-blocked-usage', [
      'state' => 'succeeded', 'usage_quality' => 'missing', 'model_id' => 'model-e',
      'cost_valuation_state' => 'unpriced', 'cost_unpriced_reason' => 'missing_usage',
      'started_at' => self::T0 + 8 * self::HOUR,
    ]);
    // Real gaps: op-gap misses attempt 2, op-lead misses 1 and 2.
    $this->attempt('op-gap', 'att-gap-1', [
      'logical_call_id' => 't1', 'state' => 'succeeded', 'usage_quality' => 'reported',
      'cost_valuation_state' => 'rated_estimate', 'cost_total_micros' => 100, 'cost_currency' => 'CNY',
      'started_at' => self::T0 + 9 * self::HOUR,
    ]);
    $this->attempt('op-gap', 'att-gap-3', [
      'logical_call_id' => 't1', 'attempt_no' => 3, 'state' => 'succeeded', 'usage_quality' => 'reported',
      'cost_valuation_state' => 'rated_estimate', 'cost_total_micros' => 100, 'cost_currency' => 'CNY',
      'started_at' => self::T0 + 9 * self::HOUR + 1,
    ]);
    $this->attempt('op-lead', 'att-lead-3', [
      'logical_call_id' => 't1', 'attempt_no' => 3, 'state' => 'succeeded', 'usage_quality' => 'reported',
      'cost_valuation_state' => 'rated_estimate', 'cost_total_micros' => 100, 'cost_currency' => 'CNY',
      'started_at' => self::T0 + 10 * self::HOUR,
    ]);
    // Window artifact: attempt 1 exists but started before the window opens,
    // so the window alone would read its absence as a leading gap.
    $this->attempt('op-cut', 'att-cut-1', [
      'logical_call_id' => 't1', 'state' => 'succeeded', 'usage_quality' => 'reported',
      'cost_valuation_state' => 'rated_estimate', 'cost_total_micros' => 100, 'cost_currency' => 'CNY',
      'started_at' => self::T0 - 25 * self::HOUR,
    ]);
    $this->attempt('op-cut', 'att-cut-2', [
      'logical_call_id' => 't1', 'attempt_no' => 2, 'state' => 'succeeded', 'usage_quality' => 'reported',
      'cost_valuation_state' => 'rated_estimate', 'cost_total_micros' => 100, 'cost_currency' => 'CNY',
      'started_at' => self::T0 + 11 * self::HOUR,
    ]);
    // Clean rows that must not appear anywhere in the report.
    $this->attempt('op-k', 'att-clean', [
      'state' => 'succeeded', 'usage_quality' => 'reported',
      'cost_valuation_state' => 'rated_estimate', 'cost_total_micros' => 100, 'cost_currency' => 'CNY',
      'started_at' => self::T0 + 12 * self::HOUR,
    ]);

    // Pipeline: one pending (90 minutes old), two quarantined with distinct
    // errors, one processed that must count for neither.
    $event = function (string $eventId, string $attemptId, array $overrides): void {
      $this->database->insert(UsageIngestService::TABLE)->fields($overrides + [
        'site_id' => self::SITE, 'producer_id' => 'chat-node', 'event_id' => $eventId,
        'event_type' => 'attempt.observed', 'schema_version' => 1,
        'operation_id' => 'op-x', 'attempt_id' => $attemptId, 'observation_revision' => 1,
        'payload_hash' => hash('sha256', $eventId), 'occurred_at' => self::T0,
        'received_at' => $this->now - 2 * self::HOUR, 'processed_at' => NULL,
        'failure_count' => 0, 'last_error' => NULL, 'quarantined_at' => NULL,
        'payload_json' => '{}',
      ])->execute();
    };
    $event('ev-pending', 'ev-att-1', ['received_at' => $this->now - 90 * self::MINUTE]);
    $event('ev-q1', 'ev-att-2', [
      'failure_count' => 5, 'last_error' => 'Error A', 'quarantined_at' => $this->now - 3 * self::HOUR,
    ]);
    $event('ev-q2', 'ev-att-3', [
      'failure_count' => 5, 'last_error' => 'Error B', 'quarantined_at' => $this->now - 2 * self::HOUR,
    ]);
    $event('ev-done', 'ev-att-4', ['processed_at' => $this->now - self::HOUR]);

    $this->database->insert(UsageProjectionService::WATERMARK_TABLE)->fields([
      'site_id' => self::SITE, 'projection' => UsageProjectionService::PROJECTION,
      'projection_version' => 1, 'last_event_id' => 99, 'processed_count' => 99,
      'data_as_of' => self::T0 + self::DAY, 'updated_at' => self::T0,
    ])->execute();
  }

  private function attempt(string $operationId, string $attemptId, array $overrides): void {
    $startedAt = (int) ($overrides['started_at'] ?? self::T0);
    $fields = $overrides + [
      'site_id' => self::SITE, 'attempt_id' => $attemptId, 'operation_id' => $operationId,
      'logical_call_id' => $overrides['stage'] ?? 'main', 'attempt_no' => 1,
      'producer_id' => 'chat-node', 'feature' => 'common', 'stage' => 'common',
      'payer' => 'platform', 'billing_role' => 'primary',
      'actor_user_id' => self::ME, 'provider_account_ref' => 'xinshi',
      'requested_model' => 'model-a', 'model_id' => 'model-a',
      'state' => 'succeeded', 'dispatch_state' => 'sent', 'usage_revision' => 1,
      'started_at' => $startedAt, 'finished_at' => $startedAt + 1000,
      'bucket_start' => intdiv($startedAt, self::HOUR) * self::HOUR,
      'occurred_at' => $startedAt + 1000, 'last_event_id' => ++$this->sequence,
      'projection_version' => 1, 'updated_at' => $startedAt + 1000,
    ];
    if (in_array($fields['state'], ['prepared', 'in_flight'], TRUE)) {
      $fields['dispatch_state'] = $fields['state'] === 'in_flight' ? 'sent' : 'not_sent';
      $fields['usage_revision'] = 0;
      $fields['finished_at'] = NULL;
    }
    $this->database->insert(UsageProjectionService::ATTEMPT_TABLE)->fields($fields)->execute();
  }

  private function vault(): ProducerVault {
    new Settings(['hash_salt' => 'quality-report-salt']);
    $privateKey = $this->createMock(PrivateKey::class);
    $privateKey->method('get')->willReturn('quality-report-private-key');
    return new ProducerVault(new KeyValueMemoryFactory(), $privateKey);
  }

  /**
   * Builds a controller with granular permission control.
   *
   * @param array<string,bool> $permissions
   */
  private function controller(string $uid, ProducerVault $vault,
    array $permissions = [], bool $anonymous = FALSE): SiteUsageReportController {

    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn((int) $uid);
    $account->method('isAnonymous')->willReturn($anonymous);
    $account->method('hasPermission')->willReturnCallback(
      static fn(string $perm): bool => $permissions[$perm] ?? FALSE);
    $time = $this->createMock(TimeInterface::class);
    $time->method('getCurrentMicroTime')->willReturnCallback(fn(): float => $this->now / 1000);
    return new SiteUsageReportController($this->siteReports, $account,
      new Settings(['hash_salt' => 'quality-report-salt']), $vault, $time, $this->quality);
  }

}
