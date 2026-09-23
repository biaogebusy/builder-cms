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
use Drupal\xinshi_ai_usage\Controller\UsageExportController;
use Drupal\xinshi_ai_usage\Service\CostRatingService;
use Drupal\xinshi_ai_usage\Service\CsvWriter;
use Drupal\xinshi_ai_usage\Service\PriceBookService;
use Drupal\xinshi_ai_usage\Service\ProducerVault;
use Drupal\xinshi_ai_usage\Service\SiteUsageReportService;
use Drupal\xinshi_ai_usage\Service\UsageExportService;
use Drupal\xinshi_ai_usage\Service\UsageProjectionService;
use Drupal\xinshi_ai_usage\Service\UsageReportException;
use Drupal\xinshi_ai_usage\Service\UsageReportService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Yaml\Yaml;

/**
 * CSV exports are private, short-lived, formula-safe and agree with the
 * reports; the consistency check proves every split adds up (UB3.4).
 *
 * Seeds the same ten attempts as SiteUsageReportTest, so the expected
 * figures are the ones proven there.
 */
final class UsageExportTest extends TestCase {

  private const SITE = 'site-a';
  private const ME = '7';
  private const OTHER = '8';
  private const T0 = 1_700_000_000_000;
  private const HOUR = 3_600_000;
  private const DAY = 86_400_000;
  private const WINDOW = ['from' => '2023-11-14', 'to' => '2023-11-18'];

  private Connection $database;
  private UsageProjectionService $projection;
  private UsageReportService $userReports;
  private SiteUsageReportService $siteReports;
  private UsageExportService $exports;
  private string $privatePath;
  private int $now = 0;
  private int $sequence = 0;

  protected function setUp(): void {
    parent::setUp();
    Database::addConnectionInfo('export_test', 'default', [
      'driver' => 'sqlite', 'database' => ':memory:',
      'namespace' => 'Drupal\\sqlite\\Driver\\Database\\sqlite',
    ]);
    $this->database = Database::getConnection('default', 'export_test');
    foreach (xinshi_ai_usage_schema() as $table => $spec) {
      $this->database->schema()->createTable($table, $spec);
    }
    $this->privatePath = sys_get_temp_dir() . '/xinshi-export-' . bin2hex(random_bytes(4));
    mkdir($this->privatePath);
    $time = $this->createMock(TimeInterface::class);
    $this->now = self::T0 + 3 * self::DAY;
    $time->method('getCurrentMicroTime')->willReturnCallback(fn(): float => $this->now / 1000);
    $priceBook = new PriceBookService($this->database, $time);
    $this->projection = new UsageProjectionService($this->database,
      new CostRatingService($this->database, $priceBook, $time),
      new \Drupal\Core\Lock\NullLockBackend(), $time, new NullLogger());
    $this->userReports = new UsageReportService($this->database, $this->projection);
    $this->siteReports = new SiteUsageReportService($this->database, $this->projection, $this->userReports, $priceBook);
    $this->exports = new UsageExportService($this->database, $this->settings(), $this->userReports, $this->siteReports);
  }

  protected function tearDown(): void {
    Database::removeConnection('export_test');
    $this->removeDirectory($this->privatePath);
    parent::tearDown();
  }

  public function testCsvCellsAreQuotedAndFormulaSafe(): void {
    $this->assertSame("a,b\r\n", CsvWriter::row(['a', 'b']));
    $this->assertSame('"say ""hi"", now"', CsvWriter::cell('say "hi", now'));
    $this->assertSame("\"line\nbreak\"", CsvWriter::cell("line\nbreak"));
    // Formula leads are neutralised, plain numbers (including negatives) are not.
    $this->assertSame("'=SUM(A1)", CsvWriter::cell('=SUM(A1)'));
    $this->assertSame("'+1234", CsvWriter::cell('+1234'));
    $this->assertSame("'@cmd", CsvWriter::cell('@cmd'));
    $this->assertSame("'-abc", CsvWriter::cell('-abc'));
    $this->assertSame('-15', CsvWriter::cell('-15'));
    $this->assertSame('616500', CsvWriter::cell('616500'));
    $this->assertSame('1.5', CsvWriter::cell(1.5));
    $this->assertSame("\"'\t tab\"", CsvWriter::cell("\t tab"));
    $this->assertSame('', CsvWriter::cell(NULL));
    $this->assertSame('1', CsvWriter::cell(TRUE));
  }

  public function testUserOperationsExportMatchesTheOperationList(): void {
    $this->seedScenario();
    $filter = $this->userReports->parseFilter(self::WINDOW, $this->now);

    $job = $this->exports->create(self::SITE, 'user', self::ME, 'operations', $filter, [], $this->now);

    $this->assertSame(['user', 'operations', 'ready', 4], [$job['scope'], $job['kind'], $job['status'], $job['row_count']]);
    $this->assertSame('2023-11-15T22:13:20.000+00:00', $job['data_as_of']);
    $this->assertSame(['from' => '2023-11-14T00:00:00.000+00:00', 'to' => '2023-11-18T00:00:00.000+00:00',
      'timezone' => 'UTC'], $job['filter']);
    $lines = $this->lines($job);
    $this->assertStringStartsWith('operation_id,created_at,', $lines[0]);
    $this->assertCount(5, $lines);
    // Newest first, like the list; the same operations the list returns.
    $ids = array_map(static fn(string $line): string => explode(',', $line)[0], array_slice($lines, 1));
    $list = $this->userReports->operations(self::SITE, self::ME, $filter, NULL, 50);
    $this->assertSame(array_column($list['operations'], 'operation_id'), $ids);
    $this->assertSame(['op-image', 'op-fail', 'op-plan', 'op-chat'], $ids);
    // The plan row: 3 attempts, 2 models, running.
    $plan = str_getcsv($lines[3]);
    $this->assertSame('op-plan', $plan[0]);
    $this->assertSame('running', $plan[5]);
    $this->assertSame('model-a model-b', $plan[8]);
    $this->assertSame(['3', '3', '1', '1', '0', '0'], array_slice($plan, 9, 6));
    // Other users' operations never appear in a user export.
    $this->assertStringNotContainsString('op-other', implode("\n", $lines));
  }

  public function testUserAttemptsExportIsScopedAndSiteAttemptsCarryCostsOnlyWhenAsked(): void {
    $this->seedScenario();
    $filter = $this->siteReports->parseFilter(self::WINDOW + ['timezone' => 'Asia/Shanghai'], $this->now);

    $mine = $this->exports->create(self::SITE, 'user', self::ME, 'attempts',
      $this->userReports->parseFilter(self::WINDOW + ['timezone' => 'Asia/Shanghai'], $this->now), [], $this->now);
    $this->assertSame(7, $mine['row_count']);
    $lines = $this->lines($mine);
    $this->assertStringEndsWith(',started_at,finished_at', $lines[0]);
    $this->assertStringNotContainsString('cost_', $lines[0]);
    $main = str_getcsv($lines[1]);
    $this->assertSame('att-main', $main[0]);
    $this->assertSame('2023-11-15T06:13:20.000+08:00', $main[23]);
    $this->assertSame('1000', $main[17]);
    // The repair attempt reported no usage and never finished: token cells stay
    // empty, not 0, and so does finished_at.
    $repair = str_getcsv($lines[2]);
    $this->assertSame('att-repair', $repair[0]);
    $this->assertSame(['', '', '', '', '', ''], array_slice($repair, 17, 6));
    $this->assertSame('', $repair[24]);

    $site = $this->exports->create(self::SITE, 'site', '1', 'attempts', $filter, ['include_costs' => TRUE], $this->now);
    $this->assertSame(10, $site['row_count']);
    $lines = $this->lines($site);
    $this->assertStringEndsWith(',cost_valuation_state,cost_currency,cost_total_micros,cost_unpriced_reason', $lines[0]);
    $usd = array_values(array_filter(array_map('str_getcsv', array_slice($lines, 1)),
      static fn(array $row): bool => $row[0] === 'other-usd'))[0];
    $this->assertSame(['reconciled', 'USD', '20000', ''], array_slice($usd, 25, 4));
    $this->assertSame(['include_costs' => TRUE], array_diff_key($site['filter'], array_flip(['from', 'to', 'timezone'])));

    $plain = $this->exports->create(self::SITE, 'site', '1', 'attempts', $filter, [], $this->now);
    $this->assertStringNotContainsString('cost_', $this->lines($plain)[0]);
    $this->assertSame(self::T0 + self::DAY, (int) (new \DateTimeImmutable($plain['data_as_of']))->format('Uv'));
  }

  public function testBreakdownExportWritesEveryRowRegardlessOfTheApiLimit(): void {
    $this->seedScenario();
    $filter = $this->siteReports->parseFilter(self::WINDOW, $this->now);

    $job = $this->exports->create(self::SITE, 'site', '1', 'breakdown', $filter, ['dimension' => 'models'], $this->now);

    $this->assertSame(5, $job['row_count']);
    $this->assertSame('models', $job['filter']['dimension']);
    $lines = $this->lines($job);
    $this->assertStringStartsWith('models,operations,attempts,active_users,', $lines[0]);
    $this->assertSame(['model-a', 'deepseek', 'model-b', 'model-c', 'qwen-image'],
      array_map(static fn(string $l): string => explode(',', $l)[0], array_slice($lines, 1)));
    $this->assertSame(['model-a', '4', '6', '2'], array_slice(str_getcsv($lines[1]), 0, 4));

    try {
      $this->exports->create(self::SITE, 'site', '1', 'breakdown', $filter, ['dimension' => 'stages'], $this->now);
      $this->fail('expected invalid_request');
    }
    catch (UsageReportException $e) {
      $this->assertSame('invalid_request', $e->reportCode);
    }
    try {
      $this->exports->create(self::SITE, 'user', self::ME, 'breakdown', $filter, [], $this->now);
      $this->fail('expected invalid_request for a kind outside the user scope');
    }
    catch (UsageReportException $e) {
      $this->assertSame('invalid_request', $e->reportCode);
    }
  }

  public function testJobsAreOwnedShortLivedAndPurged(): void {
    $this->seedScenario();
    $filter = $this->userReports->parseFilter(self::WINDOW, $this->now);
    $job = $this->exports->create(self::SITE, 'user', self::ME, 'operations', $filter, [], $this->now);

    $this->assertNotNull($this->exports->load($job['id'], 'user', self::ME, $this->now));
    // Another user, or the same id under the site scope, does not exist.
    $this->assertNull($this->exports->load($job['id'], 'user', self::OTHER, $this->now));
    $this->assertNull($this->exports->load($job['id'], 'site', self::ME, $this->now));
    $this->assertNull($this->exports->load(999, 'user', self::ME, $this->now));
    $path = $this->exports->filePath($job);
    $this->assertNotNull($path);
    $this->assertFileExists($path);
    $this->assertStringStartsWith($this->privatePath . '/ai-usage-exports/site-a/', $path);

    // One hour later the job reads expired and the file is refused.
    $later = $this->now + UsageExportService::LIFETIME_MS;
    $expired = $this->exports->load($job['id'], 'user', self::ME, $later);
    $this->assertSame('expired', $expired['status']);
    $this->assertNull($this->exports->filePath($expired));
    $this->assertFileExists($path);

    $this->assertSame(0, $this->exports->purgeExpired($this->now));
    $this->assertSame(1, $this->exports->purgeExpired($later));
    $this->assertFileDoesNotExist($path);
    $this->assertNull($this->exports->load($job['id'], 'user', self::ME, $later));
  }

  public function testExportsNeedThePrivatePath(): void {
    $this->seedScenario();
    $service = new UsageExportService($this->database, new Settings(['hash_salt' => 'x']), $this->userReports,
      $this->siteReports);
    try {
      $service->create(self::SITE, 'user', self::ME, 'operations',
        $this->userReports->parseFilter(self::WINDOW, $this->now), [], $this->now);
      $this->fail('expected export_unavailable');
    }
    catch (UsageReportException $e) {
      $this->assertSame('export_unavailable', $e->reportCode);
    }
  }

  public function testConsistencyCheckPassesOnTheSeedAndNamesEverySplit(): void {
    $this->seedScenario();
    $this->seedPriceBook();
    $filter = $this->siteReports->parseFilter(self::WINDOW, $this->now);

    $result = $this->siteReports->consistency(self::SITE, $filter, TRUE);

    $this->assertTrue($result['consistent']);
    $this->assertTrue($result['watermark_stable']);
    $names = array_column($result['checks'], 'name');
    foreach (['by_role', 'breakdown:users', 'breakdown:features', 'breakdown:models', 'breakdown:channels',
      'breakdown:roles'] as $split) {
      foreach (['attempts', 'sent', 'succeeded', 'failed', 'unknown', 'usage_missing', 'input_tokens_total',
        'output_tokens_total', 'images_generated'] as $key) {
        $this->assertContains("$split.$key", $names);
      }
    }
    $this->assertContains('breakdown:users.operations', $names);
    $this->assertContains('breakdown:users.active_users', $names);
    $this->assertContains('costs:CNY.micros', $names);
    $this->assertContains('costs:USD.attempts', $names);
    $this->assertContains('costs:none.attempts', $names);
    $byName = array_column($result['checks'], NULL, 'name');
    $this->assertSame(['expected' => '10', 'actual' => '10'],
      array_intersect_key($byName['breakdown:models.attempts'], array_flip(['expected', 'actual'])));
    $this->assertSame('616500', $byName['costs:CNY.micros']['actual']);
    $this->assertSame('7', $byName['breakdown:users.operations']['actual']);
    $this->assertSame('2', $byName['breakdown:users.active_users']['actual']);
    $this->assertCount(count($names), array_unique($names));

    // Without the cost permission no cost check is run or revealed.
    $quantities = $this->siteReports->consistency(self::SITE, $filter, FALSE);
    $this->assertTrue($quantities['consistent']);
    $this->assertSame([], array_filter(array_column($quantities['checks'], 'name'),
      static fn(string $n): bool => str_starts_with($n, 'costs:')));
  }

  public function testConsistencyCheckReportsADriftedSplit(): void {
    $this->seedScenario();
    $filter = $this->siteReports->parseFilter(self::WINDOW, $this->now);
    // A rollup-style drift cannot happen on the projection itself, so the check is
    // exercised by comparing against a summary read under a narrower filter.
    $narrow = $this->siteReports->parseFilter(self::WINDOW + ['feature' => 'plan'], $this->now);
    $wide = $this->siteReports->consistency(self::SITE, $filter, FALSE);
    $plan = $this->siteReports->consistency(self::SITE, $narrow, FALSE);
    $this->assertTrue($plan['consistent']);
    $this->assertSame('3', array_column($plan['checks'], 'actual', 'name')['breakdown:models.attempts']);
    $this->assertSame('10', array_column($wide['checks'], 'actual', 'name')['breakdown:models.attempts']);
    $this->assertSame('plan', $plan['filter']['feature']);
  }

  public function testRoutesAndPermissionsAreRegistered(): void {
    $module = dirname(__DIR__, 3);
    $routes = Yaml::parseFile($module . '/xinshi_ai_usage.routing.yml');
    $this->assertSame('/api/v3/ai/usage/me/exports', $routes['xinshi_ai_usage.report.exports']['path']);
    $this->assertSame(['POST'], $routes['xinshi_ai_usage.report.exports']['methods']);
    $this->assertSame('TRUE', $routes['xinshi_ai_usage.report.exports']['requirements']['_csrf_request_header_token']);
    $this->assertSame('view site ai usage,export site ai usage',
      $routes['xinshi_ai_usage.admin.exports']['requirements']['_permission']);
    $this->assertSame('view site ai usage,export site ai usage',
      $routes['xinshi_ai_usage.admin.export_download']['requirements']['_permission']);
    $this->assertSame('/api/v3/ai/admin/reports/consistency', $routes['xinshi_ai_usage.admin.consistency']['path']);
    foreach (['report.export_download', 'admin.export_download'] as $name) {
      $this->assertTrue($routes["xinshi_ai_usage.$name"]['options']['no_cache']);
      $this->assertSame('\d+', $routes["xinshi_ai_usage.$name"]['requirements']['export']);
    }
    $permissions = Yaml::parseFile($module . '/xinshi_ai_usage.permissions.yml');
    $this->assertTrue($permissions['export site ai usage']['restrict access']);
    $services = Yaml::parseFile($module . '/xinshi_ai_usage.services.yml')['services'];
    $this->assertSame(UsageExportService::class, $services['xinshi_ai_usage.export']['class']);
  }

  public function testControllerCreatesPollsAndDownloadsForTheRequesterOnly(): void {
    $this->seedScenario();
    $vault = $this->vault();
    $vault->set('chat-node', 'secret', self::SITE, 1);

    $created = $this->controller(self::ME, $vault)->createUser($this->post(self::WINDOW + ['kind' => 'operations']));
    $this->assertSame(202, $created->getStatusCode());
    $body = json_decode((string) $created->getContent(), TRUE);
    $this->assertSame('ready', $body['status']);
    $this->assertSame(4, $body['row_count']);
    $this->assertSame("/api/v3/ai/usage/me/exports/{$body['id']}/download", $body['download_url']);
    $this->assertArrayNotHasKey('file_name', $body);
    $this->assertArrayNotHasKey('site_id', $body);
    $this->assertUncached($created);

    $status = $this->controller(self::ME, $vault)->statusUser(Request::create('/x'), $body['id']);
    $this->assertSame(200, $status->getStatusCode());
    $download = $this->controller(self::ME, $vault)->downloadUser(Request::create('/x'), $body['id']);
    $this->assertSame(200, $download->getStatusCode());
    $this->assertSame('text/csv; charset=utf-8', $download->headers->get('Content-Type'));
    $this->assertStringContainsString('attachment', (string) $download->headers->get('Content-Disposition'));
    $this->assertStringContainsString("ai-usage-user-operations-{$body['id']}.csv", (string) $download->headers->get('Content-Disposition'));
    $this->assertUncached($download);

    // Another account sees neither the status nor the file.
    $this->assertSame(404, $this->controller(self::OTHER, $vault)->statusUser(Request::create('/x'), $body['id'])->getStatusCode());
    $this->assertSame(404, $this->controller(self::OTHER, $vault)->downloadUser(Request::create('/x'), $body['id'])->getStatusCode());
    // The same job is not reachable through the admin routes either.
    $this->assertSame(404, $this->controller(self::ME, $vault, ['view site ai usage' => TRUE, 'export site ai usage' => TRUE])
      ->statusSite(Request::create('/x'), $body['id'])->getStatusCode());

    // Expired jobs answer 410 on download.
    $this->now += UsageExportService::LIFETIME_MS;
    $gone = $this->controller(self::ME, $vault)->downloadUser(Request::create('/x'), $body['id']);
    $this->assertSame(410, $gone->getStatusCode());
    $this->assertSame('expired', json_decode((string) $gone->getContent(), TRUE)['code']);
  }

  public function testControllerRefusesScopeParametersBadBodiesAndCostColumnsWithoutPermission(): void {
    $this->seedScenario();
    $vault = $this->vault();
    $vault->set('chat-node', 'secret', self::SITE, 1);
    $user = $this->controller(self::ME, $vault);

    $this->assertSame(403, $user->createUser($this->post(self::WINDOW + ['kind' => 'operations', 'uid' => '8']))->getStatusCode());
    $this->assertSame(403, $user->createUser($this->post(self::WINDOW + ['kind' => 'operations', 'site' => 'b']))->getStatusCode());
    $bad = $user->createUser(Request::create('/x', 'POST', [], [], [], [], 'not json'));
    $this->assertSame(400, $bad->getStatusCode());
    $this->assertSame('invalid_request', json_decode((string) $bad->getContent(), TRUE)['code']);
    $this->assertSame(400, $user->createUser($this->post(self::WINDOW + ['kind' => 'breakdown']))->getStatusCode());
    $this->assertSame(422, $user->createUser($this->post(['from' => '2020-01-01', 'to' => '2023-11-18', 'kind' => 'operations']))->getStatusCode());
    $this->assertSame(403, $this->controller('0', $vault, [], TRUE)->createUser($this->post(['kind' => 'operations']))->getStatusCode());
    $this->assertSame(503, $this->controller(self::ME, $this->vault())->createUser($this->post(['kind' => 'operations']))->getStatusCode());

    $admin = $this->controller('1', $vault, ['view site ai usage' => TRUE, 'export site ai usage' => TRUE]);
    $denied = $admin->createSite($this->post(self::WINDOW + ['kind' => 'attempts', 'include_costs' => TRUE]));
    $this->assertSame(403, $denied->getStatusCode());
    $allowed = $this->controller('1', $vault, ['view site ai usage' => TRUE, 'export site ai usage' => TRUE,
      'view ai supplier costs' => TRUE])->createSite($this->post(self::WINDOW + ['kind' => 'attempts', 'include_costs' => TRUE, 'user' => self::OTHER]));
    $this->assertSame(202, $allowed->getStatusCode());
    $body = json_decode((string) $allowed->getContent(), TRUE);
    $this->assertSame(2, $body['row_count']);
    $this->assertSame(self::OTHER, $body['filter']['user']);
    $this->assertSame("/api/v3/ai/admin/reports/exports/{$body['id']}/download", $body['download_url']);

    // Without a private path the request is a 503, retryable.
    $noPath = new UsageExportController(
      new UsageExportService($this->database, new Settings(['hash_salt' => 'x']), $this->userReports, $this->siteReports),
      $this->userReports, $this->siteReports, $this->account(self::ME, [], FALSE),
      new Settings(['hash_salt' => 'x']), $vault, $this->time());
    $unavailable = $noPath->createUser($this->post(self::WINDOW + ['kind' => 'operations']));
    $this->assertSame(503, $unavailable->getStatusCode());
    $this->assertTrue(json_decode((string) $unavailable->getContent(), TRUE)['retryable']);
  }

  public function testSchemaUpgradeAddsTheExportTableIdempotently(): void {
    $schema = $this->database->schema();
    $schema->dropTable(UsageExportService::TABLE);
    $this->assertFalse($schema->tableExists(UsageExportService::TABLE));
    xinshi_ai_usage_apply_schema_updates($this->database);
    $this->assertTrue($schema->tableExists(UsageExportService::TABLE));
    xinshi_ai_usage_apply_schema_updates($this->database);
    $this->assertTrue($schema->indexExists(UsageExportService::TABLE, 'expires'));
  }

  /** @return list<string> */
  private function lines(array $job): array {
    $content = file_get_contents($this->exports->filePath($job));
    $this->assertStringStartsWith(CsvWriter::BOM, $content);
    $lines = explode("\r\n", substr($content, strlen(CsvWriter::BOM)));
    $this->assertSame('', array_pop($lines));
    return $lines;
  }

  /** Symfony re-serializes the directives in sorted order; compare the set, not the string. */
  private function assertUncached(Response $response): void {
    $directives = array_map('trim', explode(',', (string) $response->headers->get('Cache-Control')));
    sort($directives);
    $this->assertSame(['no-store', 'private'], $directives);
  }

  private function post(array $body): Request {
    return Request::create('/x', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($body));
  }

  private function settings(): Settings {
    return new Settings(['hash_salt' => 'export-salt', 'file_private_path' => $this->privatePath]);
  }

  private function time(): TimeInterface {
    $time = $this->createMock(TimeInterface::class);
    $time->method('getCurrentMicroTime')->willReturnCallback(fn(): float => $this->now / 1000);
    return $time;
  }

  private function account(string $uid, array $permissions, bool $anonymous): AccountInterface {
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn((int) $uid);
    $account->method('isAnonymous')->willReturn($anonymous);
    $account->method('hasPermission')->willReturnCallback(
      static fn(string $perm): bool => $permissions[$perm] ?? FALSE);
    return $account;
  }

  private function controller(string $uid, ProducerVault $vault, array $permissions = [],
    bool $anonymous = FALSE): UsageExportController {
    return new UsageExportController($this->exports, $this->userReports, $this->siteReports,
      $this->account($uid, $permissions, $anonymous), $this->settings(), $vault, $this->time());
  }

  private function vault(): ProducerVault {
    new Settings(['hash_salt' => 'export-salt']);
    $privateKey = $this->createMock(PrivateKey::class);
    $privateKey->method('get')->willReturn('export-private-key');
    return new ProducerVault(new KeyValueMemoryFactory(), $privateKey);
  }

  private function removeDirectory(string $directory): void {
    if (!is_dir($directory)) {
      return;
    }
    foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
      \RecursiveIteratorIterator::CHILD_FIRST) as $item) {
      $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($directory);
  }

  /** The SiteUsageReportTest scenario: ten attempts of two users plus a legacy row. */
  private function seedScenario(): void {
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
      'error_code' => 'process_interrupted', 'model_id' => 'model-a', 'finished_at' => NULL,
    ]);
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
    $imageAt = self::T0 + self::DAY - 2 * self::HOUR - 13 * 60_000 - 20_000;
    $this->attempt('op-image', 'att-img', self::ME, [
      'feature' => 'text_to_image', 'stage' => 'image', 'billing_role' => 'primary',
      'producer_id' => 'cms-image', 'started_at' => $imageAt, 'state' => 'succeeded',
      'usage_quality' => 'reported', 'images_generated' => 3, 'model_id' => 'qwen-image',
      'cost_valuation_state' => 'rated_estimate', 'cost_total_micros' => 600_000, 'cost_currency' => 'CNY',
    ]);
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
    $this->attempt('op-legacy', 'legacy-att', NULL, [
      'feature' => 'common', 'stage' => 'common',
      'started_at' => self::T0 + 3 * self::HOUR, 'state' => 'succeeded', 'usage_quality' => 'reported',
      'input_tokens_total' => 50, 'output_tokens_total' => 20, 'model_id' => 'deepseek',
      'billing_role' => NULL,
      'cost_valuation_state' => 'rated_estimate', 'cost_total_micros' => 1_000, 'cost_currency' => 'CNY',
    ]);
    $this->attempt('op-fail', 'fail-att', self::ME, [
      'feature' => 'common', 'stage' => 'common',
      'started_at' => self::T0 + 4 * self::HOUR, 'state' => 'failed', 'usage_quality' => 'missing',
      'error_code' => 'provider_error', 'model_id' => 'model-a',
      'cost_valuation_state' => 'rated_estimate', 'cost_total_micros' => 500, 'cost_currency' => 'CNY',
    ]);
    $this->database->insert(UsageProjectionService::WATERMARK_TABLE)->fields([
      'site_id' => self::SITE, 'projection' => UsageProjectionService::PROJECTION,
      'projection_version' => 1, 'last_event_id' => 99, 'processed_count' => 99,
      'data_as_of' => self::T0 + self::DAY, 'updated_at' => self::T0,
    ])->execute();
  }

  private function seedPriceBook(): void {
    $this->database->insert(PriceBookService::TABLE)->fields([
      'site_id' => self::SITE, 'book_kind' => PriceBookService::KIND_SUPPLIER_CHAT,
      'version' => '1', 'currency' => 'CNY', 'rates_json' => '{"accounts":{}}',
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

}
