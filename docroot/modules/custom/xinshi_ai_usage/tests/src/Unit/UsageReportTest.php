<?php

declare(strict_types=1);

namespace Drupal\Tests\xinshi_ai_usage\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\KeyValueStore\KeyValueMemoryFactory;
use Drupal\Core\Lock\NullLockBackend;
use Drupal\Core\PrivateKey;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Site\Settings;
use Drupal\xinshi_ai_usage\Controller\UsageReportController;
use Drupal\xinshi_ai_usage\Service\CostRatingService;
use Drupal\xinshi_ai_usage\Service\LocalUsageProducer;
use Drupal\xinshi_ai_usage\Service\PriceBookService;
use Drupal\xinshi_ai_usage\Service\ProducerVault;
use Drupal\xinshi_ai_usage\Service\UsageProjectionService;
use Drupal\xinshi_ai_usage\Service\UsageReportException;
use Drupal\xinshi_ai_usage\Service\UsageReportService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Yaml\Yaml;

/**
 * The user's read-only usage reports stay in their own scope and never show purchase prices (UB3.1).
 */
final class UsageReportTest extends TestCase {

  private const SITE = 'site-a';
  private const ME = '7';
  private const OTHER = '8';
  /** 2023-11-14T22:13:20Z. */
  private const T0 = 1_700_000_000_000;
  private const HOUR = 3_600_000;
  private const DAY = 86_400_000;

  private Connection $database;
  private UsageProjectionService $projection;
  private UsageReportService $reports;
  private int $now = self::T0 + 3 * self::DAY;
  private int $sequence = 0;

  protected function setUp(): void {
    parent::setUp();
    Database::addConnectionInfo('report_test', 'default', [
      'driver' => 'sqlite', 'database' => ':memory:',
      'namespace' => 'Drupal\\sqlite\\Driver\\Database\\sqlite',
    ]);
    $this->database = Database::getConnection('default', 'report_test');
    foreach (xinshi_ai_usage_schema() as $table => $spec) {
      $this->database->schema()->createTable($table, $spec);
    }
    $time = $this->createMock(TimeInterface::class);
    $time->method('getCurrentMicroTime')->willReturnCallback(fn(): float => $this->now / 1000);
    $priceBook = new PriceBookService($this->database, $time);
    $this->projection = new UsageProjectionService($this->database,
      new CostRatingService($this->database, $priceBook, $time), new NullLockBackend(), $time, new NullLogger());
    $this->reports = new UsageReportService($this->database, $this->projection);
  }

  protected function tearDown(): void {
    Database::removeConnection('report_test');
    parent::tearDown();
  }

  public function testServiceRoutesAndControllerAreWired(): void {
    $module = dirname(__DIR__, 3);
    $services = Yaml::parseFile($module . '/xinshi_ai_usage.services.yml')['services'];
    $this->assertSame(UsageReportService::class, $services['xinshi_ai_usage.report']['class']);
    $routes = Yaml::parseFile($module . '/xinshi_ai_usage.routing.yml');
    foreach (['summary', 'timeseries', 'operations', 'operation'] as $name) {
      $route = $routes['xinshi_ai_usage.report.' . $name];
      $this->assertStringStartsWith('/api/v3/ai/usage/me/', $route['path']);
      $this->assertSame(['GET'], $route['methods']);
      $this->assertSame('TRUE', $route['requirements']['_user_is_logged_in']);
      $this->assertTrue($route['options']['no_cache']);
      $this->assertSame(['oauth2', 'cookie'], $route['options']['_auth']);
    }
  }

  public function testTheFilterHasSafeDefaultsAndRejectsUnusableValues(): void {
    $filter = $this->reports->parseFilter([], $this->now);
    $this->assertSame([$this->now - UsageReportService::DEFAULT_RANGE_MS, $this->now, 'UTC', NULL, NULL],
      [$filter['from'], $filter['to'], $filter['timezone']->getName(), $filter['feature'], $filter['model']]);

    // A date without zone is read in the requested zone; [from, to) in that zone.
    $filter = $this->reports->parseFilter(['from' => '2023-11-15', 'to' => '2023-11-16', 'timezone' => 'Asia/Shanghai',
      'feature' => 'text_to_image', 'model' => 'qwen-image'], $this->now);
    $this->assertSame(1_699_977_600_000, $filter['from']);
    $this->assertSame(1_700_064_000_000, $filter['to']);
    $this->assertSame('Asia/Shanghai', $filter['timezone']->getName());
    $this->assertSame('text_to_image', $filter['feature']);
    // An explicit offset wins over the zone parameter: T0 is 06:13 on the 15th in Shanghai.
    $this->assertSame(self::T0, $this->reports->parseFilter(['from' => '2023-11-14T22:13:20Z', 'to' => '2023-11-16',
      'timezone' => 'Asia/Shanghai'], $this->now)['from']);

    $cases = [
      [['timezone' => 'Mars/Olympus'], 'invalid_request'],
      [['from' => '2023-11-16', 'to' => '2023-11-15'], 'invalid_request'],
      [['from' => 'yesterday'], 'invalid_request'],
      [['from' => '2022-01-01', 'to' => '2023-11-15'], 'range_too_large'],
      [['feature' => 'text to image'], 'invalid_request'],
      [['model' => str_repeat('m', 65)], 'invalid_request'],
    ];
    foreach ($cases as [$query, $code]) {
      try {
        $this->reports->parseFilter($query, $this->now);
        $this->fail('expected ' . $code . ' for ' . json_encode($query));
      }
      catch (UsageReportException $e) {
        $this->assertSame($code, $e->reportCode, json_encode($query));
      }
    }
    // The same 45-day window is fine per day and too long per hour.
    $this->assertSame(1_696_118_400_000, $this->reports->parseFilter(['from' => '2023-10-01', 'to' => '2023-11-15'],
      $this->now)['from']);
    try {
      $this->reports->parseFilter(['from' => '2023-10-01', 'to' => '2023-11-15'], $this->now, 'hour');
      $this->fail('expected range_too_large at hour granularity');
    }
    catch (UsageReportException $e) {
      $this->assertSame('range_too_large', $e->reportCode);
    }
  }

  public function testTheSummaryCountsOnlyTheUsersAttemptsAndNeverShowsCost(): void {
    $this->seedScenario();
    $filter = $this->reports->parseFilter(['from' => '2023-11-14', 'to' => '2023-11-18'], $this->now);

    $summary = $this->reports->summary(self::SITE, self::ME, $filter);

    $this->assertSame(3, $summary['totals']['operations']);
    $this->assertSame(6, $summary['totals']['attempts']);
    $this->assertSame(['sent' => 5, 'open' => 1, 'succeeded' => 3, 'failed' => 1, 'unknown' => 1, 'not_sent' => 0,
      'usage_reported' => 3, 'usage_missing' => 2],
      array_intersect_key($summary['totals'], array_flip(['sent', 'open', 'succeeded', 'failed', 'unknown',
        'not_sent', 'usage_reported', 'usage_missing'])));
    // Token sums are decimal strings and only count reported usage.
    $this->assertSame(['input_tokens_total' => '1100', 'input_tokens_cache_read' => '80',
      'input_tokens_cache_write' => '0', 'output_tokens_total' => '250', 'output_tokens_reasoning' => '0'],
      $summary['totals']['tokens']);
    $this->assertSame('3', $summary['totals']['images_generated']);
    $this->assertSame(2, $summary['totals']['images_delivered']);
    $this->assertSame(['status' => 'not_enabled'], $summary['charges']);
    $this->assertSame('2023-11-16T00:00:00.000+00:00', $summary['data_as_of']);
    $this->assertSame(1, $summary['projection_version']);
    $this->assertSame(0, $summary['pending_events']);
    $this->assertSame('2023-11-14T00:00:00.000+00:00', $summary['filter']['from']);
    $this->assertStringNotContainsString('cost', json_encode($summary));
    $this->assertStringNotContainsString('micros', json_encode($summary));
    $this->assertStringNotContainsString(self::OTHER . '-att', json_encode($summary));

    // Feature and model filters narrow the same rows.
    $images = $this->reports->summary(self::SITE, self::ME, ['feature' => 'text_to_image'] + $filter);
    $this->assertSame(1, $images['totals']['operations']);
    $this->assertSame('3', $images['totals']['images_generated']);
    $model = $this->reports->summary(self::SITE, self::ME, ['model' => 'model-b'] + $filter);
    $this->assertSame(1, $model['totals']['attempts']);
    // Another user sees only their own rows; an unknown user sees nothing.
    $this->assertSame(1, $this->reports->summary(self::SITE, self::OTHER, $filter)['totals']['attempts']);
    $this->assertSame(0, $this->reports->summary(self::SITE, '9', $filter)['totals']['attempts']);
    $this->assertSame(0, $this->reports->summary('other-site', self::ME, $filter)['totals']['attempts']);
  }

  public function testTheTimeSeriesIsContinuousInTheRequestedZone(): void {
    $this->seedScenario();
    $filter = $this->reports->parseFilter(['from' => '2023-11-15', 'to' => '2023-11-18', 'timezone' => 'Asia/Shanghai'],
      $this->now);

    $series = $this->reports->timeseries(self::SITE, self::ME, $filter, 'day');

    $this->assertSame('day', $series['filter']['granularity']);
    $this->assertSame(['2023-11-15T00:00:00+08:00', '2023-11-16T00:00:00+08:00', '2023-11-17T00:00:00+08:00'],
      array_column($series['buckets'], 'bucket_start'));
    // T0 (22:13Z on the 14th) is the 15th in Shanghai; the image job on the
    // 15th 20:00Z is the 16th; nothing on the 17th.
    $this->assertSame([FALSE, FALSE, TRUE], array_column($series['buckets'], 'empty'));
    $this->assertSame([2, 1, 0], array_column($series['buckets'], 'operations'));
    $this->assertSame([5, 1, 0], array_column($series['buckets'], 'attempts'));
    $this->assertSame(['0', '3', '0'], array_column($series['buckets'], 'images_generated'));
    $this->assertSame([1, 0, 0], array_column($series['buckets'], 'unknown'));
    $this->assertSame('1100', $series['buckets'][0]['tokens']['input_tokens_total']);

    $hours = $this->reports->timeseries(self::SITE, self::ME, $this->reports->parseFilter([
      'from' => '2023-11-14T22:00:00Z', 'to' => '2023-11-15T01:00:00Z'], $this->now, 'hour'), 'hour');
    $this->assertSame(['2023-11-14T22:00:00+00:00', '2023-11-14T23:00:00+00:00', '2023-11-15T00:00:00+00:00'],
      array_column($hours['buckets'], 'bucket_start'));
    $this->assertSame([5, 0, 0], array_column($hours['buckets'], 'attempts'));
  }

  public function testOperationsPageByCursorWithDerivedStatus(): void {
    $this->seedScenario();
    $filter = $this->reports->parseFilter(['from' => '2023-11-14', 'to' => '2023-11-18'], $this->now);

    $page = $this->reports->operations(self::SITE, self::ME, $filter, NULL, 2);
    $this->assertSame(['op-image', 'op-plan'], array_column($page['operations'], 'operation_id'));
    $this->assertNotNull($page['next_cursor']);
    $this->assertSame(2, $page['limit']);
    $image = $page['operations'][0];
    $this->assertSame('succeeded', $image['status']);
    $this->assertSame('text_to_image', $image['feature']);
    $this->assertSame('cms-image', $image['producer_id']);
    $this->assertSame(['qwen-image'], $image['models']);
    $this->assertSame(2, $image['images_delivered']);
    $this->assertSame('3', $image['images_generated']);
    $this->assertSame('2023-11-15T20:00:00.000+00:00', $image['created_at']);
    // The plan is still running: an open attempt outranks its finished ones.
    $this->assertSame('running', $page['operations'][1]['status']);
    $this->assertSame(['model-a', 'model-b'], $page['operations'][1]['models']);

    $rest = $this->reports->operations(self::SITE, self::ME, $filter, $page['next_cursor'], 2);
    $this->assertSame(['op-chat'], array_column($rest['operations'], 'operation_id'));
    $this->assertNull($rest['next_cursor']);
    // The interrupted chat call is unknown, never counted as a success.
    $this->assertSame('unknown', $rest['operations'][0]['status']);

    $this->assertSame(['op-plan'], array_column(
      $this->reports->operations(self::SITE, self::ME, $filter, NULL, 50, 'running')['operations'], 'operation_id'));
    $this->assertSame(['op-chat'], array_column(
      $this->reports->operations(self::SITE, self::ME, $filter, NULL, 50, 'unknown')['operations'], 'operation_id'));
    $this->assertSame(['op-image'], array_column(
      $this->reports->operations(self::SITE, self::ME, $filter, NULL, 50, 'succeeded')['operations'], 'operation_id'));
    $this->assertSame([], $this->reports->operations(self::SITE, self::ME, $filter, NULL, 50, 'failed')['operations']);
    $this->assertSame(UsageReportService::MAX_LIMIT,
      $this->reports->operations(self::SITE, self::ME, $filter, NULL, 500)['limit']);
    try {
      $this->reports->operations(self::SITE, self::ME, $filter, 'not-a-cursor', 10);
      $this->fail('expected an invalid cursor to be refused');
    }
    catch (UsageReportException $e) {
      $this->assertSame('invalid_request', $e->reportCode);
    }
  }

  public function testAuxiliaryStepsJoinTheirOperationWithoutDecidingItsStatus(): void {
    // An image job whose prompt rewrite, ordered by the CMS worker under the
    // job's operation (UB2.6), succeeded while the generation itself failed.
    $this->attempt('op-img-failed', 'att-rewrite', self::ME, ['feature' => 'query-transformer',
      'stage' => 'query-transformer', 'billing_role' => 'auxiliary', 'started_at' => self::T0,
      'state' => 'succeeded', 'usage_quality' => 'reported', 'input_tokens_total' => 40,
      'output_tokens_total' => 20, 'model_id' => 'deepseek-v4-flash', 'requested_model' => 'deepseek-v4-flash']);
    $this->attempt('op-img-failed', 'att-image', self::ME, ['feature' => 'text_to_image', 'stage' => 'image',
      'producer_id' => 'cms-image', 'started_at' => self::T0 + 5_000, 'state' => 'failed',
      'usage_quality' => 'missing', 'error_code' => 'content_policy', 'model_id' => 'qwen-image',
      'requested_model' => 'qwen-image']);
    // A legacy title request is an operation of auxiliary calls only: judged on them.
    $this->attempt('op-title', 'att-title', self::ME, ['feature' => 'title', 'stage' => 'title',
      'billing_role' => 'auxiliary', 'started_at' => self::T0 + self::HOUR, 'state' => 'succeeded',
      'usage_quality' => 'reported', 'input_tokens_total' => 10, 'output_tokens_total' => 5]);
    // A rewrite still in flight before the image call started keeps the job running.
    $this->attempt('op-img-open', 'att-rewrite-open', self::ME, ['feature' => 'query-transformer',
      'stage' => 'query-transformer', 'billing_role' => 'auxiliary', 'started_at' => self::T0 + 2 * self::HOUR,
      'state' => 'in_flight']);
    $filter = $this->reports->parseFilter(['from' => '2023-11-14', 'to' => '2023-11-18'], $this->now);

    $listed = $this->reports->operations(self::SITE, self::ME, $filter, NULL, 50)['operations'];
    $byId = array_column($listed, NULL, 'operation_id');
    $this->assertSame('failed', $byId['op-img-failed']['status'], 'a succeeded rewrite never stands in for the generation');
    $this->assertSame('text_to_image', $byId['op-img-failed']['feature'], 'the primary call leads the operation');
    $this->assertSame('cms-image', $byId['op-img-failed']['producer_id']);
    $this->assertSame(['deepseek-v4-flash', 'qwen-image'], $byId['op-img-failed']['models']);
    $this->assertSame(2, $byId['op-img-failed']['attempts']);
    $this->assertSame('2023-11-14T22:13:20.000+00:00', $byId['op-img-failed']['created_at'], 'created at its first attempt');
    $this->assertSame('succeeded', $byId['op-title']['status']);
    $this->assertSame('running', $byId['op-img-open']['status']);

    // The list filter agrees with the reported status.
    $ids = fn(string $status): array => array_column(
      $this->reports->operations(self::SITE, self::ME, $filter, NULL, 50, $status)['operations'], 'operation_id');
    $this->assertSame(['op-img-failed'], $ids('failed'));
    $this->assertSame(['op-title'], $ids('succeeded'));
    $this->assertSame(['op-img-open'], $ids('running'));
    $this->assertSame([], $ids('unknown'));
    $this->assertSame([], $ids('not_sent'));
    $detail = $this->reports->operation(self::SITE, self::ME, 'op-img-failed', new \DateTimeZone('UTC'));
    $this->assertSame('failed', $detail['status']);
    $this->assertSame(['auxiliary', 'primary'], array_column($detail['attempt_list'], 'billing_role'));
  }

  public function testTheOperationDetailListsAttemptsAndDeliveriesOfTheOwnerOnly(): void {
    $this->seedScenario();
    $tz = new \DateTimeZone('UTC');

    $detail = $this->reports->operation(self::SITE, self::ME, 'op-image', $tz);
    $this->assertSame('succeeded', $detail['status']);
    $this->assertSame(1, $detail['attempts'], 'the count stays next to the list');
    $this->assertCount(1, $detail['attempt_list']);
    $attempt = $detail['attempt_list'][0];
    $this->assertSame(['att-img', 'image', 1, 'primary', 'reported', '3'],
      [$attempt['attempt_id'], $attempt['logical_call_id'], $attempt['attempt_no'], $attempt['billing_role'],
        $attempt['usage_quality'], $attempt['images_generated']]);
    $this->assertSame([[0, 'media', 'persisted'], [0, 'image_asset', 'committed'], [1, 'media', 'persisted'],
      [1, 'image_asset', 'committed'], [2, 'media', 'persisted']],
      array_map(static fn(array $d): array => [$d['output_index'], $d['artifact_kind'], $d['state']],
        $detail['deliveries']));
    $this->assertSame(['status' => 'not_enabled'], $detail['charges']);
    $this->assertStringNotContainsString('cost', json_encode($detail));

    // Attempts without reported usage carry no token block instead of zeros.
    $chat = $this->reports->operation(self::SITE, self::ME, 'op-chat', $tz);
    $this->assertSame(['att-main', 'att-repair'], array_column($chat['attempt_list'], 'attempt_id'));
    $this->assertSame('1000', $chat['attempt_list'][0]['tokens']['input_tokens_total']);
    $this->assertNull($chat['attempt_list'][1]['tokens']);
    $this->assertSame('process_interrupted', $chat['attempt_list'][1]['error_code']);

    $this->assertNull($this->reports->operation(self::SITE, self::OTHER, 'op-image', $tz), 'not theirs');
    $this->assertNull($this->reports->operation(self::SITE, self::ME, 'op-other', $tz), 'someone else\'s');
    $this->assertNull($this->reports->operation(self::SITE, self::ME, 'op-missing', $tz));
  }

  public function testTheControllerRefusesScopeParametersAndUsesTheSessionIdentity(): void {
    $this->seedScenario();
    $vault = $this->vault();
    $vault->set('chat-node', 'secret', self::SITE, 1);
    $controller = $this->controller(self::ME, $vault);

    $response = $controller->summary(Request::create('/api/v3/ai/usage/me/summary', 'GET',
      ['from' => '2023-11-14', 'to' => '2023-11-18']));
    $this->assertSame(200, $response->getStatusCode());
    // Symfony re-serializes the directives in sorted order; compare the set, not the string.
    $directives = array_map('trim', explode(',', (string) $response->headers->get('Cache-Control')));
    sort($directives);
    $this->assertSame(['no-store', 'private'], $directives);
    $body = json_decode((string) $response->getContent(), TRUE);
    $this->assertSame(6, $body['totals']['attempts']);
    $this->assertNotEmpty($body['request_id']);

    foreach (['uid' => '8', 'user' => '8', 'userId' => '8', 'site' => 'other', 'account' => 'x'] as $key => $value) {
      $response = $controller->summary(Request::create('/api/v3/ai/usage/me/summary', 'GET', [$key => $value]));
      $this->assertSame(403, $response->getStatusCode(), $key);
      $this->assertSame('forbidden', json_decode((string) $response->getContent(), TRUE)['code']);
    }

    $response = $controller->timeseries(Request::create('/x', 'GET', ['timezone' => 'Nowhere/Land']));
    $this->assertSame(400, $response->getStatusCode());
    $error = json_decode((string) $response->getContent(), TRUE);
    $this->assertSame(['invalid_request', FALSE], [$error['code'], $error['retryable']]);
    $response = $controller->operations(Request::create('/x', 'GET', ['from' => '2020-01-01', 'to' => '2023-11-18']));
    $this->assertSame(422, $response->getStatusCode());

    $response = $controller->operation(Request::create('/x', 'GET', ['from' => '2023-11-14']), 'op-image');
    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame('op-image', json_decode((string) $response->getContent(), TRUE)['operation_id']);
    $response = $controller->operation(Request::create('/x', 'GET'), 'op-other');
    $this->assertSame(404, $response->getStatusCode());
    $this->assertSame('not_found', json_decode((string) $response->getContent(), TRUE)['code']);

    // Another session sees its own data under the same URL.
    $other = $this->controller(self::OTHER, $vault)->summary(Request::create('/x', 'GET', ['from' => '2023-11-14',
      'to' => '2023-11-18']));
    $this->assertSame(1, json_decode((string) $other->getContent(), TRUE)['totals']['attempts']);

    // Anonymous sessions and installations without a registered site are refused.
    $this->assertSame(403, $this->controller('0', $vault, TRUE)->summary(Request::create('/x'))->getStatusCode());
    $unregistered = $this->controller(self::ME, $this->vault())->summary(Request::create('/x'));
    $this->assertSame(503, $unregistered->getStatusCode());
    $this->assertTrue(json_decode((string) $unregistered->getContent(), TRUE)['retryable']);
  }

  /**
   * Two users, four operations: a chat with a finished and an interrupted
   * attempt, a running plan, an image job with three images and two committed
   * deliveries, and another user's operation.
   */
  private function seedScenario(): void {
    // Chat by me at T0: main call succeeded with usage, a repair interrupted.
    $this->attempt('op-chat', 'att-main', self::ME, ['feature' => 'common', 'stage' => 'common', 'started_at' => self::T0,
      'state' => 'succeeded', 'usage_quality' => 'reported', 'input_tokens_total' => 1000, 'input_tokens_cache_read' => 80,
      'output_tokens_total' => 200, 'model_id' => 'model-a']);
    $this->attempt('op-chat', 'att-repair', self::ME, ['feature' => 'common', 'stage' => 'repair', 'billing_role' => 'repair',
      'started_at' => self::T0 + 60_000, 'state' => 'unknown', 'dispatch_state' => 'unknown',
      'error_code' => 'process_interrupted', 'model_id' => 'model-a']);
    // Plan by me at T0 + 10 min: planner done, executor still open (usage missing on the failed critic).
    $this->attempt('op-plan', 'att-planner', self::ME, ['feature' => 'plan', 'stage' => 'planner',
      'started_at' => self::T0 + 600_000, 'state' => 'succeeded', 'usage_quality' => 'reported',
      'input_tokens_total' => 100, 'output_tokens_total' => 50, 'model_id' => 'model-a']);
    $this->attempt('op-plan', 'att-critic', self::ME, ['feature' => 'plan', 'stage' => 'critic',
      'started_at' => self::T0 + 700_000, 'state' => 'failed', 'usage_quality' => 'missing', 'model_id' => 'model-b']);
    $this->attempt('op-plan', 'att-executor', self::ME, ['feature' => 'plan', 'stage' => 'executor',
      'started_at' => self::T0 + 800_000, 'state' => 'in_flight', 'model_id' => 'model-a']);
    // Image job by me on the 15th 20:00Z: three generated, two committed.
    $imageAt = self::T0 + self::DAY - 2 * self::HOUR - 13 * 60_000 - 20_000;
    $this->attempt('op-image', 'att-img', self::ME, ['feature' => 'text_to_image', 'stage' => 'image',
      'producer_id' => 'cms-image', 'started_at' => $imageAt, 'state' => 'succeeded', 'usage_quality' => 'reported',
      'images_generated' => 3, 'model_id' => 'qwen-image', 'requested_model' => 'qwen-image',
      'cost_valuation_state' => 'rated_estimate', 'cost_total_micros' => 600_000, 'cost_currency' => 'CNY']);
    foreach ([[0, 'media', 'persisted'], [0, 'image_asset', 'committed'], [1, 'media', 'persisted'],
      [1, 'image_asset', 'committed'], [2, 'media', 'persisted']] as [$index, $kind, $state]) {
      $this->database->insert(LocalUsageProducer::DELIVERY_TABLE)->fields([
        'site_id' => self::SITE, 'operation_id' => 'op-image', 'attempt_id' => 'att-img', 'output_index' => $index,
        'artifact_kind' => $kind, 'artifact_ref' => "$kind-$index", 'state' => $state, 'delivered_at' => $imageAt + $index,
      ])->execute();
    }
    // Someone else's chat in the same window, and my own chat outside it.
    $this->attempt('op-other', self::OTHER . '-att', self::OTHER, ['feature' => 'common', 'stage' => 'common',
      'started_at' => self::T0 + 1000, 'state' => 'succeeded', 'usage_quality' => 'reported',
      'input_tokens_total' => 5, 'output_tokens_total' => 5, 'model_id' => 'model-a']);
    $this->attempt('op-old', 'att-old', self::ME, ['feature' => 'common', 'stage' => 'common',
      'started_at' => self::T0 - 10 * self::DAY, 'state' => 'succeeded', 'usage_quality' => 'reported',
      'input_tokens_total' => 5, 'output_tokens_total' => 5, 'model_id' => 'model-a']);
    $this->database->insert(UsageProjectionService::WATERMARK_TABLE)->fields([
      'site_id' => self::SITE, 'projection' => UsageProjectionService::PROJECTION, 'projection_version' => 1,
      'last_event_id' => 9, 'processed_count' => 9, 'data_as_of' => self::T0 + self::DAY + 6_400_000, 'updated_at' => self::T0,
    ])->execute();
  }

  private function attempt(string $operationId, string $attemptId, string $actor, array $overrides): void {
    $startedAt = (int) ($overrides['started_at'] ?? self::T0);
    $fields = $overrides + [
      'site_id' => self::SITE, 'attempt_id' => $attemptId, 'operation_id' => $operationId,
      'logical_call_id' => $overrides['stage'] ?? 'main', 'attempt_no' => 1, 'producer_id' => 'chat-node',
      'feature' => 'common', 'stage' => 'common', 'payer' => 'platform', 'billing_role' => 'primary',
      'actor_user_id' => $actor, 'provider_account_ref' => 'xinshi', 'requested_model' => 'model-a',
      'model_id' => 'model-a', 'state' => 'succeeded', 'dispatch_state' => 'sent', 'usage_revision' => 1,
      'started_at' => $startedAt, 'finished_at' => $startedAt + 1000, 'bucket_start' => intdiv($startedAt, self::HOUR) * self::HOUR,
      'occurred_at' => $startedAt + 1000, 'last_event_id' => ++$this->sequence, 'projection_version' => 1,
      'updated_at' => $startedAt + 1000,
    ];
    if (in_array($fields['state'], ['prepared', 'in_flight'], TRUE)) {
      $fields['dispatch_state'] = $fields['state'] === 'in_flight' ? 'sent' : 'not_sent';
      $fields['usage_revision'] = 0;
      $fields['finished_at'] = NULL;
    }
    $this->database->insert(UsageProjectionService::ATTEMPT_TABLE)->fields($fields)->execute();
  }

  private function vault(): ProducerVault {
    new Settings(['hash_salt' => 'isolated-hash-salt']);
    $privateKey = $this->createMock(PrivateKey::class);
    $privateKey->method('get')->willReturn('isolated-site-private-key');
    return new ProducerVault(new KeyValueMemoryFactory(), $privateKey);
  }

  private function controller(string $uid, ProducerVault $vault, bool $anonymous = FALSE): UsageReportController {
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn((int) $uid);
    $account->method('isAnonymous')->willReturn($anonymous);
    $time = $this->createMock(TimeInterface::class);
    $time->method('getCurrentMicroTime')->willReturnCallback(fn(): float => $this->now / 1000);
    return new UsageReportController($this->reports, $account, new Settings(['hash_salt' => 'isolated-hash-salt']),
      $vault, $time);
  }

}
