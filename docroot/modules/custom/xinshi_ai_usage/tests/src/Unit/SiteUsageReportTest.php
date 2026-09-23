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
use Drupal\xinshi_ai_usage\Service\LocalUsageProducer;
use Drupal\xinshi_ai_usage\Service\PriceBookService;
use Drupal\xinshi_ai_usage\Service\ProducerVault;
use Drupal\xinshi_ai_usage\Service\SiteUsageReportService;
use Drupal\xinshi_ai_usage\Service\UsageProjectionService;
use Drupal\xinshi_ai_usage\Service\UsageQualityReportService;
use Drupal\xinshi_ai_usage\Service\UsageReportException;
use Drupal\xinshi_ai_usage\Service\UsageReportService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Yaml\Yaml;

/**
 * Site-wide admin usage and cost reports stay permission-gated, add up under
 * one filter and never fold purchase cost into quantity responses (UB3.3).
 *
 * The scenario seeds ten attempts (see seedScenario()); every expectation
 * below is derived from that table by hand, so a change in the seed must be
 * reflected in the numbers.
 */
final class SiteUsageReportTest extends TestCase {

  private const SITE = 'site-a';
  private const ME = '7';
  private const OTHER = '8';
  /** 2023-11-14T22:13:20Z. */
  private const T0 = 1_700_000_000_000;
  private const HOUR = 3_600_000;
  private const DAY = 86_400_000;
  private const WINDOW = ['from' => '2023-11-14', 'to' => '2023-11-18'];
  private const COUNT_KEYS = ['attempts', 'sent', 'open', 'succeeded', 'failed', 'unknown', 'not_sent',
    'usage_reported', 'usage_missing'];
  private const NO_TOKENS = ['input_tokens_total' => '0', 'input_tokens_cache_read' => '0',
    'input_tokens_cache_write' => '0', 'output_tokens_total' => '0', 'output_tokens_reasoning' => '0'];

  private Connection $database;
  private UsageProjectionService $projection;
  private UsageReportService $userReports;
  private SiteUsageReportService $siteReports;
  private UsageQualityReportService $qualityReports;
  private int $now = 0;
  private int $sequence = 0;

  protected function setUp(): void {
    parent::setUp();
    Database::addConnectionInfo('site_report_test', 'default', [
      'driver' => 'sqlite', 'database' => ':memory:',
      'namespace' => 'Drupal\\sqlite\\Driver\\Database\\sqlite',
    ]);
    $this->database = Database::getConnection('default', 'site_report_test');
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
    $this->userReports = new UsageReportService($this->database, $this->projection);
    $this->siteReports = new SiteUsageReportService($this->database, $this->projection,
      $this->userReports, $priceBook);
    $this->qualityReports = new UsageQualityReportService($this->database,
      $this->projection, $this->siteReports);
  }

  protected function tearDown(): void {
    Database::removeConnection('site_report_test');
    parent::tearDown();
  }

  public function testRoutesAndPermissionsAreRegistered(): void {
    $module = dirname(__DIR__, 3);
    $services = Yaml::parseFile($module . '/xinshi_ai_usage.services.yml')['services'];
    $this->assertSame(SiteUsageReportService::class, $services['xinshi_ai_usage.site_report']['class']);

    $permissions = Yaml::parseFile($module . '/xinshi_ai_usage.permissions.yml');
    $this->assertTrue($permissions['view site ai usage']['restrict access']);

    $routes = Yaml::parseFile($module . '/xinshi_ai_usage.routing.yml');
    foreach (['summary', 'timeseries', 'breakdown', 'costs'] as $name) {
      $route = $routes["xinshi_ai_usage.admin.$name"];
      $this->assertSame(['GET'], $route['methods'], $name);
      $this->assertTrue($route['options']['no_cache'], $name);
      $this->assertSame(['oauth2', 'cookie'], $route['options']['_auth'], $name);
      $this->assertSame('\Drupal\xinshi_ai_usage\Controller\SiteUsageReportController::' . $name,
        $route['defaults']['_controller'], $name);
    }
    $this->assertSame('/api/v3/ai/admin/reports/summary', $routes['xinshi_ai_usage.admin.summary']['path']);
    $this->assertSame('view site ai usage', $routes['xinshi_ai_usage.admin.summary']['requirements']['_permission']);
    // Drupal's PermissionAccessCheck joins comma-separated permissions with
    // AND and `+`-separated ones with OR; the costs route needs both.
    $this->assertSame('view site ai usage,view ai supplier costs',
      $routes['xinshi_ai_usage.admin.costs']['requirements']['_permission']);
    $this->assertSame('users|features|models|channels|roles',
      $routes['xinshi_ai_usage.admin.costs']['requirements']['dimension']);
    $this->assertSame('users|features|models|channels|roles',
      $routes['xinshi_ai_usage.admin.breakdown']['requirements']['dimension']);
  }

  public function testFilterValidationExtendsUserFilter(): void {
    // Defaults match the user report.
    $filter = $this->siteReports->parseFilter([], $this->now);
    $this->assertSame([$this->now - UsageReportService::DEFAULT_RANGE_MS, $this->now, 'UTC', NULL, NULL],
      [$filter['from'], $filter['to'], $filter['timezone']->getName(), $filter['feature'], $filter['model']]);
    $this->assertSame([NULL, NULL, NULL, NULL],
      [$filter['user'], $filter['channel'], $filter['payer'], $filter['role']]);

    // Admin keys accepted when valid.
    $filter = $this->siteReports->parseFilter([
      'user' => '42', 'channel' => 'aliyun', 'payer' => 'customer_key', 'role' => 'auxiliary',
    ], $this->now);
    $this->assertSame(['42', 'aliyun', 'customer_key', 'auxiliary'],
      [$filter['user'], $filter['channel'], $filter['payer'], $filter['role']]);

    // Invalid admin keys rejected.
    foreach ([
      ['user' => 'bad id'],
      ['channel' => 'x y'],
      ['payer' => 'bogus'],
      ['role' => 'root'],
    ] as $query) {
      try {
        $this->siteReports->parseFilter($query, $this->now);
        $this->fail('expected invalid_request for ' . json_encode($query));
      }
      catch (UsageReportException $e) {
        $this->assertSame('invalid_request', $e->reportCode, json_encode($query));
      }
    }
  }

  public function testSummaryAggregatesAllUsersAndActiveUserCount(): void {
    $this->seedScenario();
    $filter = $this->siteReports->parseFilter(self::WINDOW, $this->now);

    $summary = $this->siteReports->summary(self::SITE, $filter);

    // ME 7 (op-chat 2, op-plan 3, op-image, op-fail) + OTHER 2 + legacy NULL actor 1.
    // Not sent: the unknown repair. Open: the in-flight executor. Missing usage:
    // repair (no usage block), critic and fail (usage missing).
    $this->assertSame([
      'attempts' => 10, 'sent' => 9, 'open' => 1, 'succeeded' => 6, 'failed' => 2, 'unknown' => 1,
      'not_sent' => 0, 'usage_reported' => 6, 'usage_missing' => 3,
      'operations' => 7, 'active_users' => 2,
      'tokens' => ['input_tokens_total' => '1255', 'input_tokens_cache_read' => '80',
        'input_tokens_cache_write' => '0', 'output_tokens_total' => '325', 'output_tokens_reasoning' => '0'],
      'images_generated' => '3',
    ], $summary['totals']);
    $this->assertSame([
      'from' => '2023-11-14T00:00:00.000+00:00', 'to' => '2023-11-18T00:00:00.000+00:00', 'timezone' => 'UTC',
      'feature' => NULL, 'model' => NULL, 'user' => NULL, 'channel' => NULL, 'payer' => NULL, 'role' => NULL,
    ], $summary['filter']);
    $this->assertSame(['status' => 'not_enabled'], $summary['charges']);
    $this->assertSame('2023-11-15T22:13:20.000+00:00', $summary['data_as_of']);
    $this->assertSame([1, 0, 0],
      [$summary['projection_version'], $summary['pending_events'], $summary['quarantined_events']]);
    // Quantity-only response must not contain cost fields.
    $json = json_encode($summary);
    $this->assertStringNotContainsString('cost', $json);
    $this->assertStringNotContainsString('micros', $json);
  }

  public function testUserFilteredSummaryMatchesIndividualUserReport(): void {
    $this->seedScenario();
    $filter = $this->siteReports->parseFilter(self::WINDOW + ['user' => self::ME], $this->now);

    $siteSummary = $this->siteReports->summary(self::SITE, $filter);
    $userSummary = $this->userReports->summary(self::SITE, self::ME,
      $this->userReports->parseFilter(self::WINDOW, $this->now));

    // The admin view filtered to one user equals that user's own report on
    // every shared column; the user report has no active_users.
    foreach ([...self::COUNT_KEYS, 'operations', 'tokens', 'images_generated'] as $key) {
      $this->assertSame($userSummary['totals'][$key], $siteSummary['totals'][$key], $key);
    }
    $this->assertSame(7, $siteSummary['totals']['attempts']);
    $this->assertSame(1, $siteSummary['totals']['active_users']);
    $this->assertSame(self::ME, $siteSummary['filter']['user']);
  }

  public function testByRoleSplitAddsUpToTheSummary(): void {
    $this->seedScenario();
    $filter = $this->siteReports->parseFilter(self::WINDOW, $this->now);
    $summary = $this->siteReports->summary(self::SITE, $filter);

    // Sorted by role; the legacy attempt without billing_role is `none`.
    $this->assertSame(['auxiliary', 'none', 'primary', 'repair'], array_keys($summary['by_role']));
    $this->assertSame(['auxiliary' => 1, 'none' => 1, 'primary' => 7, 'repair' => 1],
      array_map(static fn(array $role): int => $role['attempts'], $summary['by_role']));
    // op-plan is counted under primary (planner, executor) and auxiliary (critic).
    $this->assertSame(['auxiliary' => 1, 'none' => 1, 'primary' => 6, 'repair' => 1],
      array_map(static fn(array $role): int => $role['operations'], $summary['by_role']));
    $this->assertSame('1205', $summary['by_role']['primary']['tokens']['input_tokens_total']);
    $this->assertSame('3', $summary['by_role']['primary']['images_generated']);
    $this->assertSame(self::NO_TOKENS, $summary['by_role']['repair']['tokens']);
    $this->assertRowsSumToTotals($summary['by_role'], $summary['totals'], 'by_role');
  }

  public function testAdminFiltersNarrowTheScope(): void {
    $this->seedScenario();
    $expected = [
      'role=auxiliary' => ['role' => 'auxiliary', 'attempts' => 1, 'operations' => 1],
      'role=repair' => ['role' => 'repair', 'attempts' => 1, 'operations' => 1],
      'payer=customer_key' => ['payer' => 'customer_key', 'attempts' => 1, 'operations' => 1],
      'channel=usd-account' => ['channel' => 'usd-account', 'attempts' => 1, 'operations' => 1],
      'user=OTHER' => ['user' => self::OTHER, 'attempts' => 2, 'operations' => 2],
      'feature=plan' => ['feature' => 'plan', 'attempts' => 3, 'operations' => 1],
      'user=OTHER&model=model-c' => ['user' => self::OTHER, 'model' => 'model-c', 'attempts' => 1, 'operations' => 1],
      'user=nobody' => ['user' => 'nobody', 'attempts' => 0, 'operations' => 0],
    ];
    foreach ($expected as $label => $case) {
      $query = array_diff_key($case, ['attempts' => 0, 'operations' => 0]);
      $summary = $this->siteReports->summary(self::SITE,
        $this->siteReports->parseFilter(self::WINDOW + $query, $this->now));
      $this->assertSame([$case['attempts'], $case['operations']],
        [$summary['totals']['attempts'], $summary['totals']['operations']], $label);
    }
  }

  public function testTimeSeriesBucketsMatchLocalTimeZone(): void {
    $this->seedScenario();
    $filter = $this->siteReports->parseFilter(self::WINDOW + ['timezone' => 'Asia/Shanghai'], $this->now, 'day');

    $series = $this->siteReports->timeseries(self::SITE, $filter, 'day');

    // T0 is 06:13 on Nov 15 in Shanghai; the image job (20:00Z on Nov 15) is
    // 04:00 on Nov 16 there. Nov 14 and Nov 17 are present but empty.
    $this->assertSame(['2023-11-14T00:00:00+08:00', '2023-11-15T00:00:00+08:00',
      '2023-11-16T00:00:00+08:00', '2023-11-17T00:00:00+08:00'], array_column($series['buckets'], 'bucket_start'));
    $this->assertSame([TRUE, FALSE, FALSE, TRUE], array_column($series['buckets'], 'empty'));
    $this->assertSame([0, 9, 1, 0], array_column($series['buckets'], 'attempts'));
    $this->assertSame([0, 6, 1, 0], array_column($series['buckets'], 'operations'));
    $this->assertSame([0, 2, 1, 0], array_column($series['buckets'], 'active_users'));
    $this->assertSame(['0', '0', '3', '0'], array_column($series['buckets'], 'images_generated'));
    $this->assertSame(['input_tokens_total' => '1255', 'input_tokens_cache_read' => '80',
      'input_tokens_cache_write' => '0', 'output_tokens_total' => '325', 'output_tokens_reasoning' => '0'],
      $series['buckets'][1]['tokens']);
    $this->assertSame(self::NO_TOKENS, $series['buckets'][0]['tokens']);
    $this->assertSame(['2023-11-14T00:00:00.000+08:00', '2023-11-18T00:00:00.000+08:00', 'Asia/Shanghai', 'day'],
      [$series['filter']['from'], $series['filter']['to'], $series['filter']['timezone'], $series['filter']['granularity']]);

    // The buckets add up to the summary under the same filter.
    $summary = $this->siteReports->summary(self::SITE, $filter);
    $this->assertRowsSumToTotals($series['buckets'], $summary['totals'], 'buckets');

    // Hour buckets: 96 hours, the first starting at local midnight.
    $hourly = $this->siteReports->timeseries(self::SITE,
      $this->siteReports->parseFilter(self::WINDOW + ['timezone' => 'Asia/Shanghai'], $this->now, 'hour'), 'hour');
    $this->assertCount(96, $hourly['buckets']);
    $this->assertSame('2023-11-14T00:00:00+08:00', $hourly['buckets'][0]['bucket_start']);
    $this->assertSame(10, array_sum(array_column($hourly['buckets'], 'attempts')));
    // T0 falls in the 06:00 bucket of Nov 15: the chat pair, plan trio and OTHER's first call.
    $this->assertSame(['2023-11-15T06:00:00+08:00', 6],
      [$hourly['buckets'][30]['bucket_start'], $hourly['buckets'][30]['attempts']]);
  }

  public function testBreakdownRanksByAttemptsAndKeepsTheNoneRow(): void {
    $this->seedScenario();
    $filter = $this->siteReports->parseFilter(self::WINDOW, $this->now);

    // Users: one operation belongs to exactly one actor, so the split is exclusive.
    $users = $this->siteReports->breakdown(self::SITE, $filter, 'users', 10);
    $this->assertSame([self::ME, self::OTHER, 'none'], array_column($users['rows'], 'dimension'));
    $this->assertSame([7, 2, 1], array_column($users['rows'], 'attempts'));
    $this->assertSame([4, 2, 1], array_column($users['rows'], 'operations'));
    $this->assertArrayNotHasKey('active_users', $users['rows'][0]);
    $this->assertSame([3, FALSE, TRUE, 10],
      [$users['row_count'], $users['truncated'], $users['operations_exclusive'], $users['limit']]);
    $this->assertSame('users', $users['filter']['dimension']);

    // Models: op-plan spans model-a and model-b, so operations add up to more than 7.
    $models = $this->siteReports->breakdown(self::SITE, $filter, 'models', 10);
    $this->assertSame(['model-a', 'deepseek', 'model-b', 'model-c', 'qwen-image'],
      array_column($models['rows'], 'dimension'));
    $this->assertSame([6, 1, 1, 1, 1], array_column($models['rows'], 'attempts'));
    $this->assertSame([4, 1, 1, 1, 1], array_column($models['rows'], 'operations'));
    $this->assertSame([2, 0, 1, 1, 1], array_column($models['rows'], 'active_users'));
    $this->assertSame([
      'dimension' => 'model-a', 'operations' => 4, 'attempts' => 6, 'active_users' => 2,
      'sent' => 5, 'open' => 1, 'succeeded' => 3, 'failed' => 1, 'unknown' => 1, 'not_sent' => 0,
      'usage_reported' => 3, 'usage_missing' => 2,
      'tokens' => ['input_tokens_total' => '1105', 'input_tokens_cache_read' => '80',
        'input_tokens_cache_write' => '0', 'output_tokens_total' => '255', 'output_tokens_reasoning' => '0'],
      'images_generated' => '0',
    ], $models['rows'][0]);
    $this->assertSame([5, FALSE, FALSE], [$models['row_count'], $models['truncated'], $models['operations_exclusive']]);

    $features = $this->siteReports->breakdown(self::SITE, $filter, 'features', 10);
    $this->assertSame(['common', 'plan', 'text_to_image'], array_column($features['rows'], 'dimension'));
    $this->assertSame([6, 3, 1], array_column($features['rows'], 'attempts'));
    $this->assertSame([5, 1, 1], array_column($features['rows'], 'operations'));

    $channels = $this->siteReports->breakdown(self::SITE, $filter, 'channels', 10);
    $this->assertSame(['xinshi', 'usd-account'], array_column($channels['rows'], 'dimension'));
    $this->assertSame([9, 1], array_column($channels['rows'], 'attempts'));

    $roles = $this->siteReports->breakdown(self::SITE, $filter, 'roles', 10);
    $this->assertSame(['primary', 'auxiliary', 'none', 'repair'], array_column($roles['rows'], 'dimension'));
    $this->assertSame([7, 1, 1, 1], array_column($roles['rows'], 'attempts'));

    // Limit and truncation: one row beyond the limit is fetched, never returned.
    $limited = $this->siteReports->breakdown(self::SITE, $filter, 'models', 2);
    $this->assertSame(['model-a', 'deepseek'], array_column($limited['rows'], 'dimension'));
    $this->assertSame([5, TRUE, 2], [$limited['row_count'], $limited['truncated'], $limited['limit']]);

    try {
      $this->siteReports->breakdown(self::SITE, $filter, 'stages', 10);
      $this->fail('expected invalid_request for an unknown dimension');
    }
    catch (UsageReportException $e) {
      $this->assertSame('invalid_request', $e->reportCode);
    }
  }

  public function testEveryBreakdownAddsUpToTheSummary(): void {
    $this->seedScenario();
    $filter = $this->siteReports->parseFilter(self::WINDOW, $this->now);
    $summary = $this->siteReports->summary(self::SITE, $filter);

    // The SQL aggregation of breakdown() and the PHP aggregation of summary()
    // must agree on every count and sum, whichever dimension splits the rows.
    foreach (SiteUsageReportService::DIMENSIONS as $dimension) {
      $breakdown = $this->siteReports->breakdown(self::SITE, $filter, $dimension, 200);
      $this->assertFalse($breakdown['truncated'], $dimension);
      $this->assertCount($breakdown['row_count'], $breakdown['rows'], $dimension);
      $this->assertRowsSumToTotals($breakdown['rows'], $summary['totals'], $dimension);
      $operations = array_sum(array_column($breakdown['rows'], 'operations'));
      if ($breakdown['operations_exclusive']) {
        $this->assertSame($summary['totals']['operations'], $operations, $dimension);
        $actors = array_filter(array_column($breakdown['rows'], 'dimension'), static fn(string $d): bool => $d !== 'none');
        $this->assertCount($summary['totals']['active_users'], $actors, $dimension);
      }
      else {
        $this->assertGreaterThanOrEqual($summary['totals']['operations'], $operations, $dimension);
      }
    }
  }

  public function testCostsEndpointShowsPurchaseCostByCurrency(): void {
    $this->seedScenario();
    $this->seedPriceBook();
    $filter = $this->siteReports->parseFilter(self::WINDOW, $this->now);

    $costs = $this->siteReports->costs(self::SITE, $filter, 'models', 10);

    // Eight attempts carry a valuation: the unknown repair and the open
    // executor have none and are not counted. Currencies never mix.
    $this->assertSame([
      ['currency' => 'CNY', 'attempts' => 6, 'rated_estimate_attempts' => 6, 'reconciled_attempts' => 0,
        'unpriced_attempts' => 0, 'rated_micros' => '616500', 'reconciled_micros' => '0', 'estimated' => TRUE],
      ['currency' => 'USD', 'attempts' => 1, 'rated_estimate_attempts' => 0, 'reconciled_attempts' => 1,
        'unpriced_attempts' => 0, 'rated_micros' => '0', 'reconciled_micros' => '20000', 'estimated' => TRUE],
      ['currency' => NULL, 'attempts' => 1, 'rated_estimate_attempts' => 0, 'reconciled_attempts' => 0,
        'unpriced_attempts' => 1, 'rated_micros' => '0', 'reconciled_micros' => '0', 'estimated' => TRUE],
    ], $costs['totals']);

    $this->assertSame([
      ['role' => 'auxiliary', 'by_currency' => [
        'CNY' => ['currency' => 'CNY', 'attempts' => 1, 'cost_micros' => '1000'],
      ]],
      ['role' => 'none', 'by_currency' => [
        'CNY' => ['currency' => 'CNY', 'attempts' => 1, 'cost_micros' => '1000'],
      ]],
      ['role' => 'primary', 'by_currency' => [
        'CNY' => ['currency' => 'CNY', 'attempts' => 4, 'cost_micros' => '614500'],
        'USD' => ['currency' => 'USD', 'attempts' => 1, 'cost_micros' => '20000'],
        'none' => ['currency' => NULL, 'attempts' => 1, 'cost_micros' => '0'],
      ]],
    ], $costs['by_role']);

    $this->assertSame([
      ['state' => 'failed', 'by_currency' => [
        'CNY' => ['currency' => 'CNY', 'attempts' => 2, 'cost_micros' => '1500'],
      ]],
      ['state' => 'succeeded', 'by_currency' => [
        'CNY' => ['currency' => 'CNY', 'attempts' => 4, 'cost_micros' => '615000'],
        'USD' => ['currency' => 'USD', 'attempts' => 1, 'cost_micros' => '20000'],
        'none' => ['currency' => NULL, 'attempts' => 1, 'cost_micros' => '0'],
      ]],
    ], $costs['by_state']);

    $this->assertSame([
      ['reason' => 'customer_key', 'by_currency' => [
        'none' => ['currency' => NULL, 'attempts' => 1, 'cost_micros' => '0'],
      ]],
    ], $costs['unpriced_reasons']);

    $this->assertSame([[
      'book_kind' => 'supplier_chat', 'version' => '1', 'currency' => 'CNY', 'rounding_policy' => 'half_up',
      'effective_from' => '1970-01-01T00:00:00.000+00:00', 'effective_to' => NULL,
    ]], $costs['active_price_books']);

    // Rows rank by attempts like breakdown(); cost is never a cross-currency sort key.
    $this->assertSame(['model-a', 'deepseek', 'model-b', 'model-c', 'qwen-image'],
      array_column($costs['rows'], 'dimension'));
    $this->assertSame([4, 1, 1, 1, 1], array_column($costs['rows'], 'attempts'));
    $this->assertSame([
      'dimension' => 'model-a', 'attempts' => 4, 'by_currency' => [
        'CNY' => ['currency' => 'CNY', 'attempts' => 3, 'rated_count' => 3, 'unpriced_count' => 0, 'cost_micros' => '14500'],
        'none' => ['currency' => NULL, 'attempts' => 1, 'rated_count' => 0, 'unpriced_count' => 1, 'cost_micros' => '0'],
      ],
    ], $costs['rows'][0]);
    $this->assertSame(['USD' => ['currency' => 'USD', 'attempts' => 1, 'rated_count' => 1, 'unpriced_count' => 0,
      'cost_micros' => '20000']], $costs['rows'][3]['by_currency']);
    $this->assertSame([5, FALSE, 10, 'models'],
      [$costs['row_count'], $costs['truncated'], $costs['limit'], $costs['filter']['dimension']]);

    // Per-currency attempts of the rows add up to the totals.
    $byCurrency = [];
    foreach ($costs['rows'] as $row) {
      foreach ($row['by_currency'] as $key => $detail) {
        $byCurrency[$key] = ($byCurrency[$key] ?? 0) + $detail['attempts'];
      }
    }
    ksort($byCurrency);
    $this->assertSame(['CNY' => 6, 'USD' => 1, 'none' => 1], $byCurrency);

    $limited = $this->siteReports->costs(self::SITE, $filter, 'models', 2);
    $this->assertSame(['model-a', 'deepseek'], array_column($limited['rows'], 'dimension'));
    $this->assertSame([5, TRUE, 2], [$limited['row_count'], $limited['truncated'], $limited['limit']]);
  }

  public function testCostsIncludeFailedAndAuxiliary(): void {
    $this->seedScenario();
    $this->seedPriceBook();
    $filter = $this->siteReports->parseFilter(self::WINDOW, $this->now);

    $costs = $this->siteReports->costs(self::SITE, $filter, 'roles', 10);

    // The failed auxiliary critic and the legacy attempt without a role are
    // both platform cost; `none` reads the same here as in the quantity reports.
    $this->assertSame(['primary', 'auxiliary', 'none'], array_column($costs['rows'], 'dimension'));
    $this->assertSame([6, 1, 1], array_column($costs['rows'], 'attempts'));
    $this->assertSame(['CNY' => ['currency' => 'CNY', 'attempts' => 1, 'rated_count' => 1, 'unpriced_count' => 0,
      'cost_micros' => '1000']], $costs['rows'][1]['by_currency']);
    $this->assertSame(['auxiliary', 'none', 'primary'], array_column($costs['by_role'], 'role'));
    $this->assertSame(3, $costs['row_count']);

    // Failed attempts (critic 1000 + fail 500) keep their cost.
    $this->assertSame(['failed', 'succeeded'], array_column($costs['by_state'], 'state'));
    $this->assertSame(['CNY' => ['currency' => 'CNY', 'attempts' => 2, 'cost_micros' => '1500']],
      $costs['by_state'][0]['by_currency']);
  }

  public function testControllerRequiresPermissionAndRefusesSiteParam(): void {
    $this->seedScenario();
    $vault = $this->vault();
    $vault->set('chat-node', 'secret', self::SITE, 1);

    // No site permission -> 403.
    $noAccess = $this->controller('99', $vault, ['view site ai usage' => FALSE, 'view ai supplier costs' => FALSE])
      ->summary(Request::create('/api/v3/ai/admin/reports/summary', 'GET', self::WINDOW));
    $this->assertSame(403, $noAccess->getStatusCode());
    $this->assertSame('forbidden', json_decode((string) $noAccess->getContent(), TRUE)['code']);

    // Has site permission -> 200.
    $hasAccess = $this->controller('99', $vault, ['view site ai usage' => TRUE, 'view ai supplier costs' => FALSE])
      ->summary(Request::create('/x', 'GET', self::WINDOW));
    $this->assertSame(200, $hasAccess->getStatusCode());
    $body = json_decode((string) $hasAccess->getContent(), TRUE);
    $this->assertSame([10, 7, 2],
      [$body['totals']['attempts'], $body['totals']['operations'], $body['totals']['active_users']]);
    $this->assertArrayHasKey('request_id', $body);

    // site query param is refused.
    $withSite = $this->controller('99', $vault, ['view site ai usage' => TRUE, 'view ai supplier costs' => TRUE])
      ->summary(Request::create('/x', 'GET', ['site' => 'other']));
    $this->assertSame(403, $withSite->getStatusCode());

    // Costs endpoint needs both permissions; either one alone is refused.
    $onlySite = $this->controller('99', $vault, ['view site ai usage' => TRUE, 'view ai supplier costs' => FALSE])
      ->costs(Request::create('/x', 'GET', self::WINDOW), 'models');
    $this->assertSame(403, $onlySite->getStatusCode());
    $onlyCosts = $this->controller('99', $vault, ['view site ai usage' => FALSE, 'view ai supplier costs' => TRUE])
      ->costs(Request::create('/x', 'GET', self::WINDOW), 'models');
    $this->assertSame(403, $onlyCosts->getStatusCode());

    $bothPerms = $this->controller('99', $vault, ['view site ai usage' => TRUE, 'view ai supplier costs' => TRUE])
      ->costs(Request::create('/x', 'GET', self::WINDOW), 'models');
    $this->assertSame(200, $bothPerms->getStatusCode());
    $this->assertSame(5, json_decode((string) $bothPerms->getContent(), TRUE)['row_count']);

    // Unknown dimension -> 400 invalid_request.
    $badDimension = $this->controller('99', $vault, ['view site ai usage' => TRUE])
      ->breakdown(Request::create('/x', 'GET', self::WINDOW), 'stages');
    $this->assertSame(400, $badDimension->getStatusCode());
    $this->assertSame('invalid_request', json_decode((string) $badDimension->getContent(), TRUE)['code']);

    // Anonymous is refused.
    $anon = $this->controller('0', $vault, ['view site ai usage' => TRUE], TRUE)->summary(Request::create('/x'));
    $this->assertSame(403, $anon->getStatusCode());

    // No registered site -> 503.
    $noSite = $this->controller('99', $this->vault(), ['view site ai usage' => TRUE])->summary(Request::create('/x'));
    $this->assertSame(503, $noSite->getStatusCode());
  }

  public function testSchemaUpgradeAddsReportIndexesIdempotently(): void {
    $schema = $this->database->schema();
    // Drop the indexes first to simulate a pre-UB3.3 schema.
    $schema->dropIndex('ai_provider_attempt', 'started');
    $schema->dropIndex('ai_provider_attempt', 'actor_started');
    $this->assertFalse($schema->indexExists('ai_provider_attempt', 'started'));
    $this->assertFalse($schema->indexExists('ai_provider_attempt', 'actor_started'));

    xinshi_ai_usage_apply_schema_updates($this->database);

    $this->assertTrue($schema->indexExists('ai_provider_attempt', 'started'));
    $this->assertTrue($schema->indexExists('ai_provider_attempt', 'actor_started'));

    // Second run is a no-op.
    xinshi_ai_usage_apply_schema_updates($this->database);
    $this->assertTrue($schema->indexExists('ai_provider_attempt', 'started'));
  }

  /**
   * Every split of one window must add up to its summary totals.
   *
   * @param array<array> $rows
   *   Breakdown rows, buckets or the by_role map; each carries the totals shape.
   */
  private function assertRowsSumToTotals(array $rows, array $totals, string $label): void {
    foreach (self::COUNT_KEYS as $key) {
      $this->assertSame($totals[$key], array_sum(array_column($rows, $key)), "$label: $key");
    }
    foreach (array_keys($totals['tokens']) as $token) {
      $sum = array_sum(array_map(static fn(array $row): int => (int) $row['tokens'][$token], $rows));
      $this->assertSame($totals['tokens'][$token], (string) $sum, "$label: $token");
    }
    $images = array_sum(array_map(static fn(array $row): int => (int) $row['images_generated'], $rows));
    $this->assertSame($totals['images_generated'], (string) $images, "$label: images_generated");
  }

  /**
   * Seeds ten attempts of two users plus a legacy NULL-actor attempt.
   *
   * Cost figures are written straight into the projection so the report is
   * tested independently of the rating service.
   */
  private function seedScenario(): void {
    // Chat by ME at T0: main call succeeded (12000 CNY), repair unknown (no valuation).
    $this->attempt('op-chat', 'att-main', self::ME, [
      'feature' => 'common', 'stage' => 'common', 'started_at' => self::T0,
      'state' => 'succeeded', 'usage_quality' => 'reported',
      'input_tokens_total' => 1000, 'input_tokens_cache_read' => 80,
      'output_tokens_total' => 200, 'model_id' => 'model-a',
      'cost_valuation_state' => 'rated_estimate', 'cost_total_micros' => 12_000, 'cost_currency' => 'CNY',
    ]);
    $this->attempt('op-chat', 'att-repair', self::ME, [
      'feature' => 'common', 'stage' => 'repair', 'billing_role' => 'repair',
      'started_at' => self::T0 + 60_000, 'state' => 'unknown', 'dispatch_state' => 'unknown',
      'error_code' => 'process_interrupted', 'model_id' => 'model-a',
    ]);

    // Plan by ME: planner (2000 CNY) + critic failed, usage missing (1000 CNY) + executor in flight.
    $this->attempt('op-plan', 'att-planner', self::ME, [
      'feature' => 'plan', 'stage' => 'planner', 'billing_role' => 'primary',
      'started_at' => self::T0 + 600_000, 'state' => 'succeeded', 'usage_quality' => 'reported',
      'input_tokens_total' => 100, 'output_tokens_total' => 50, 'model_id' => 'model-a',
      'cost_valuation_state' => 'rated_estimate', 'cost_total_micros' => 2_000, 'cost_currency' => 'CNY',
    ]);
    $this->attempt('op-plan', 'att-critic', self::ME, [
      'feature' => 'plan', 'stage' => 'critic', 'billing_role' => 'auxiliary',
      'started_at' => self::T0 + 700_000, 'state' => 'failed', 'usage_quality' => 'missing',
      'model_id' => 'model-b',
      'cost_valuation_state' => 'rated_estimate', 'cost_total_micros' => 1_000, 'cost_currency' => 'CNY',
    ]);
    $this->attempt('op-plan', 'att-executor', self::ME, [
      'feature' => 'plan', 'stage' => 'executor', 'billing_role' => 'primary',
      'started_at' => self::T0 + 800_000, 'state' => 'in_flight', 'model_id' => 'model-a',
    ]);

    // Image job by ME at 2023-11-15T20:00Z: 3 generated (600000 CNY), 2 committed deliveries.
    $imageAt = self::T0 + self::DAY - 2 * self::HOUR - 13 * 60_000 - 20_000;
    $this->attempt('op-image', 'att-img', self::ME, [
      'feature' => 'text_to_image', 'stage' => 'image', 'billing_role' => 'primary',
      'producer_id' => 'cms-image', 'started_at' => $imageAt, 'state' => 'succeeded',
      'usage_quality' => 'reported', 'images_generated' => 3, 'model_id' => 'qwen-image',
      'cost_valuation_state' => 'rated_estimate', 'cost_total_micros' => 600_000, 'cost_currency' => 'CNY',
    ]);
    foreach ([[0, 'image_asset', 'committed'], [1, 'image_asset', 'committed'],
      [2, 'media', 'persisted']] as [$index, $kind, $state]) {
      $this->database->insert(LocalUsageProducer::DELIVERY_TABLE)->fields([
        'site_id' => self::SITE, 'operation_id' => 'op-image', 'attempt_id' => 'att-img',
        'output_index' => $index, 'artifact_kind' => $kind, 'artifact_ref' => "$kind-$index",
        'state' => $state, 'delivered_at' => $imageAt + $index,
      ])->execute();
    }

    // OTHER: a call on their own key (unpriced) and a USD-priced, reconciled call.
    $this->attempt('op-other', 'other-att', self::OTHER, [
      'feature' => 'common', 'stage' => 'common',
      'started_at' => self::T0 + 1000, 'state' => 'succeeded', 'usage_quality' => 'reported',
      'input_tokens_total' => 5, 'output_tokens_total' => 5, 'model_id' => 'model-a',
      'cost_valuation_state' => 'unpriced', 'cost_unpriced_reason' => 'customer_key',
      'payer' => 'customer_key',
    ]);
    $this->attempt('op-other-usd', 'other-usd', self::OTHER, [
      'feature' => 'common', 'stage' => 'common',
      'started_at' => self::T0 + 2 * self::HOUR, 'state' => 'succeeded', 'usage_quality' => 'reported',
      'input_tokens_total' => 100, 'output_tokens_total' => 50, 'model_id' => 'model-c',
      'provider_account_ref' => 'usd-account',
      'cost_valuation_state' => 'reconciled', 'cost_total_micros' => 20_000, 'cost_currency' => 'USD',
    ]);

    // Legacy attempt with NULL actor and NULL billing_role (1000 CNY).
    $this->attempt('op-legacy', 'legacy-att', NULL, [
      'feature' => 'common', 'stage' => 'common',
      'started_at' => self::T0 + 3 * self::HOUR, 'state' => 'succeeded', 'usage_quality' => 'reported',
      'input_tokens_total' => 50, 'output_tokens_total' => 20, 'model_id' => 'deepseek',
      'billing_role' => NULL,
      'cost_valuation_state' => 'rated_estimate', 'cost_total_micros' => 1_000, 'cost_currency' => 'CNY',
    ]);

    // A failed attempt by ME that still cost 500 CNY.
    $this->attempt('op-fail', 'fail-att', self::ME, [
      'feature' => 'common', 'stage' => 'common',
      'started_at' => self::T0 + 4 * self::HOUR, 'state' => 'failed', 'usage_quality' => 'missing',
      'error_code' => 'provider_error', 'model_id' => 'model-a',
      'cost_valuation_state' => 'rated_estimate', 'cost_total_micros' => 500, 'cost_currency' => 'CNY',
    ]);

    // Watermark: the projection is complete up to T0 + 1 day.
    $this->database->insert(UsageProjectionService::WATERMARK_TABLE)->fields([
      'site_id' => self::SITE, 'projection' => UsageProjectionService::PROJECTION,
      'projection_version' => 1, 'last_event_id' => 99, 'processed_count' => 99,
      'data_as_of' => self::T0 + self::DAY, 'updated_at' => self::T0,
    ])->execute();
  }

  private function seedPriceBook(): void {
    $rates = json_encode([
      'accounts' => [
        'xinshi' => [
          'models' => [
            'model-a' => [
              'per_million_input' => 2000,
              'per_million_cache_read' => 200,
              'per_million_cache_write' => 500,
              'per_million_output' => 8000,
            ],
          ],
        ],
      ],
    ]);
    $this->database->insert(PriceBookService::TABLE)->fields([
      'site_id' => self::SITE, 'book_kind' => PriceBookService::KIND_SUPPLIER_CHAT,
      'version' => '1', 'currency' => 'CNY', 'rates_json' => $rates,
      'rounding_policy' => 'half_up', 'status' => 'active',
      'effective_from' => 0, 'effective_to' => NULL,
      'source_ref' => 'test', 'created_at' => 1, 'created_by' => NULL,
    ])->execute();
  }

  private function attempt(string $operationId, string $attemptId, ?string $actor, array $overrides): void {
    $startedAt = (int) ($overrides['started_at'] ?? self::T0);
    $fields = $overrides + [
      'site_id' => self::SITE, 'attempt_id' => $attemptId, 'operation_id' => $operationId,
      'logical_call_id' => $overrides['stage'] ?? 'main', 'attempt_no' => 1,
      'producer_id' => 'chat-node', 'feature' => 'common', 'stage' => 'common',
      'payer' => 'platform', 'billing_role' => 'primary',
      'actor_user_id' => $actor, 'provider_account_ref' => 'xinshi',
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
    new Settings(['hash_salt' => 'site-report-salt']);
    $privateKey = $this->createMock(PrivateKey::class);
    $privateKey->method('get')->willReturn('site-report-private-key');
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
      new Settings(['hash_salt' => 'site-report-salt']), $vault, $time, $this->qualityReports);
  }

}
