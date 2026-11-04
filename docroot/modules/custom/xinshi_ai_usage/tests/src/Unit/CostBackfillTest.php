<?php

declare(strict_types=1);

namespace Drupal\Tests\xinshi_ai_usage\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\Context\CacheContextsManager;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Component\DependencyInjection\ReverseContainer;
use Drupal\Core\Form\FormState;
use Drupal\Core\KeyValueStore\KeyValueMemoryFactory;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Lock\NullLockBackend;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\PrivateKey;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\xinshi_ai_usage\Form\CostBackfillForm;
use Drupal\user\Access\PermissionAccessCheck;
use Drupal\xinshi_ai_usage\Service\CostBackfillService;
use Drupal\xinshi_ai_usage\Service\CostRatingService;
use Drupal\xinshi_ai_usage\Service\PriceBookException;
use Drupal\xinshi_ai_usage\Service\PriceBookService;
use Drupal\xinshi_ai_usage\Service\ProducerVault;
use Drupal\xinshi_ai_usage\Service\UsageIngestService;
use Drupal\xinshi_ai_usage\Service\UsageProjectionService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Routing\Route;

/** Exercises historical publication, immutable costs and atomic backfill against synthetic SQLite. */
final class CostBackfillTest extends TestCase {

  private Connection $database;
  private TimeInterface $time;
  private PriceBookService $books;
  private CostRatingService $rating;
  private UsageProjectionService $projection;
  private UsageIngestService $ingest;
  private CostBackfillService $backfill;
  private AccountInterface $account;
  private Settings $settings;
  private ProducerVault $vault;
  private int $now = 1_700_000_100_250;
  private string $denied = '';
  private string $actor = '42';
  private bool $authenticated = TRUE;
  private const START = 1_700_000_004_120;
  private const KIND = PriceBookService::KIND_SUPPLIER_CHAT;

  protected function setUp(): void {
    $connection = getenv('XINSHI_USAGE_ISOLATED_MYSQL') === '1'
      ? ['driver' => 'mysql', 'database' => 'usage_backfill_test', 'username' => 'usage_test',
        'password' => 'isolated-test', 'host' => '127.0.0.1', 'port' => '3306',
        'namespace' => 'Drupal\\mysql\\Driver\\Database\\mysql']
      : ['driver' => 'sqlite', 'database' => ':memory:',
        'namespace' => 'Drupal\\sqlite\\Driver\\Database\\sqlite'];
    Database::addConnectionInfo('backfill_test', 'default', $connection);
    $this->database = Database::getConnection('default', 'backfill_test');
    foreach (xinshi_ai_usage_schema() as $table => $spec) {
      $this->database->schema()->createTable($table, $spec);
    }
    $this->time = $this->createMock(TimeInterface::class);
    $this->time->method('getCurrentMicroTime')->willReturnCallback(fn() => $this->now / 1000);
    $this->books = new PriceBookService($this->database, $this->time);
    $this->rating = new CostRatingService($this->database, $this->books, $this->time);
    $this->projection = new UsageProjectionService($this->database, $this->rating,
      new NullLockBackend(), $this->time, new NullLogger());
    $this->ingest = new UsageIngestService($this->database, $this->time, new NullLogger());
    $this->settings = new Settings(['hash_salt' => 'synthetic', 'xinshi_ai_usage.site_id' => 'site-a']);
    $key = $this->createMock(PrivateKey::class);
    $key->method('get')->willReturn('synthetic-site-key');
    $this->vault = new ProducerVault(new KeyValueMemoryFactory(), $key);
    $this->account = $this->createMock(AccountInterface::class);
    $this->account->method('id')->willReturnCallback(fn() => $this->actor);
    $this->account->method('isAuthenticated')->willReturnCallback(fn() => $this->authenticated);
    $this->account->method('hasPermission')->willReturnCallback(fn($permission) => $permission !== $this->denied);
    $this->backfill = $this->service(new NullLockBackend());
    $container = new ContainerBuilder();
    $translation = $this->createMock(TranslationInterface::class);
    $translation->method('translateString')->willReturnCallback(
      fn(TranslatableMarkup $markup) => $markup->getUntranslatedString());
    $container->set('string_translation', $translation);
    $container->set('messenger', $this->createMock(MessengerInterface::class));
    $container->set('current_user', $this->account);
    $container->set('cache_contexts_manager', new CacheContextsManager($container, ['user.permissions']));
    $container->set('database', $this->database);
    $container->set('xinshi_ai_usage.cost_backfill', $this->backfill);
    $container->set(ReverseContainer::class, new ReverseContainer($container));
    \Drupal::setContainer($container);
  }

  protected function tearDown(): void {
    \Drupal::unsetContainer();
    if (getenv('XINSHI_USAGE_ISOLATED_MYSQL') === '1') {
      foreach (array_keys(xinshi_ai_usage_schema()) as $table) {
        $this->database->schema()->dropTable($table);
      }
    }
    Database::removeConnection('backfill_test');
  }

  public function testGapPublicationPreservesExistingIntervalsAndMillisecondBoundaries(): void {
    $current = $this->books->createDraft('site-a', self::KIND, 'current', 'CNY', self::rates(), $this->now);
    $this->books->activate($current, 'site-a', self::KIND, '42');
    $before = $this->books->loadVersion($current);
    $history = $this->history();
    $this->assertSame($before, $this->books->loadVersion($current));
    $this->assertNull($this->books->loadActive('site-a', self::KIND, self::START - 1));
    $this->assertSame('history', $this->books->loadActive('site-a', self::KIND, self::START)['version']);
    $this->assertSame('history', $this->books->loadActive('site-a', self::KIND, $this->now - 1)['version']);
    $this->assertSame('current', $this->books->loadActive('site-a', self::KIND, $this->now)['version']);
    $this->assertSame('42', $this->books->loadVersion($history)['activated_by']);
    $this->assertSame($this->now, (int) $this->books->loadVersion($history)['activated_at']);
    $this->now++;
    $future = $this->books->createDraft('site-a', self::KIND, 'next', 'CNY', self::rates(), $this->now);
    $this->books->activate($future, 'site-a', self::KIND, '42');
    $this->assertSame('history', $this->books->loadActive('site-a', self::KIND, self::START)['version']);
  }

  public function testInvalidIntervalsSourcesActorsAndOverlapAreRejected(): void {
    foreach ([[self::START, self::START, 'quote'], [self::START, $this->now + 1, 'quote'],
      [self::START, $this->now, ' ']] as [$from, $to, $source]) {
      $this->reject('invalid_historical_interval', fn() => $this->books->createDraft('site-a',
        self::KIND, 'bad', 'CNY', self::rates(), $from, $source, '42', $to));
    }
    $id = $this->books->createDraft('site-a', self::KIND, 'actor', 'CNY', self::rates(),
      self::START, 'quote', '42', $this->now);
    $this->reject('invalid_historical_interval', fn() => $this->books->activate($id, 'site-a', self::KIND, '0'));
    $this->books->activate($id, 'site-a', self::KIND, '42');
    $overlap = $this->books->createDraft('site-a', self::KIND, 'overlap', 'CNY', self::rates(),
      self::START - 1, 'quote', '42', self::START + 1);
    $this->reject('overlapping_version', fn() => $this->books->activate($overlap, 'site-a', self::KIND, '42'));
    $this->assertSame('draft', $this->books->loadVersion($overlap)['status']);
  }

  public function testPreviewDoesNotWriteAndExecutionAppendsCostsOnceAcrossReplayAndRebuild(): void {
    $this->ingestEvents([self::event('a')]);
    $original = $this->rows(CostRatingService::TABLE);
    $id = $this->history();
    // Merely publishing a price book must not re-rate immutable missing-price facts on rebuild.
    $this->projection->rebuild('site-a');
    $this->assertSame('no_price_book', $this->rows(UsageProjectionService::ATTEMPT_TABLE)[0]['cost_unpriced_reason']);
    $before = $this->snapshot();
    $preview = $this->backfill->preview($id);
    $this->assertSame($before, $this->snapshot());
    $this->assertSame('365', $preview['amount_micros']);
    $this->assertCount(1, $preview['entries']);
    $result = $this->backfill->execute($preview, 'contract quote verified');
    $this->assertSame(['count' => 1, 'amount_micros' => '365', 'currency' => 'CNY', 'replayed' => FALSE], $result);
    $costs = $this->rows(CostRatingService::TABLE);
    $this->assertCount(2, $costs);
    $this->assertSame($original[0], $costs[0]);
    $this->assertSame('price_book_backfill', $costs[1]['source_kind']);
    $receipt = $this->rows(CostBackfillService::TABLE)[0];
    $this->assertSame('42', $receipt['actor_user_id']);
    $this->assertSame((int) $original[0]['id'], (int) $receipt['original_cost_entry_id']);
    $this->assertSame('contract quote verified', $receipt['reason']);
    $this->assertSame(365, (int) $this->rows(UsageProjectionService::ROLLUP_TABLE)[0]['cost_micros']);
    $this->now += CostBackfillService::PREVIEW_TTL_MS + 1;
    $this->assertTrue($this->backfill->execute($preview, 'contract quote verified')['replayed']);
    $this->reject('invalid_preview', fn() => $this->backfill->execute($preview, 'changed reason'));
    $this->projection->rebuild('site-a');
    $this->assertSame(365, (int) $this->rows(UsageProjectionService::ATTEMPT_TABLE)[0]['cost_total_micros']);
    $this->assertSame($costs, $this->rows(CostRatingService::TABLE));
    $this->assertSame([], $this->backfill->preview($id)['entries']);
    $this->assertCount(1, $this->rows(CostBackfillService::TABLE));
  }

  public function testNewUsageRevisionInvalidatesAnEntirePendingBatch(): void {
    $this->ingestEvents([self::event('a'), self::event('b')]);
    $id = $this->history();
    $preview = $this->backfill->preview($id);
    $event = self::event('b', 2);
    $event['usage']['input_tokens_total'] = '140';
    $this->ingestEvents([$event]);
    $before = $this->snapshot();
    $this->reject('stale_preview', fn() => $this->backfill->execute($preview, 'verified'));
    $this->assertSame($before, $this->snapshot());
    $next = $this->backfill->preview($id);
    $this->assertSame(['a'], array_column($next['entries'], 'attempt_id'));
    $this->assertSame(405, (int) $this->database->select(UsageProjectionService::ATTEMPT_TABLE, 'a')
      ->fields('a', ['cost_total_micros'])->condition('attempt_id', 'b')->execute()->fetchField());
  }

  public function testExpiryPriceChangesMalformedPreviewAndActorChangeFailWithoutWrites(): void {
    $this->ingestEvents([self::event('a')]);
    $id = $this->history();
    $preview = $this->backfill->preview($id);
    $before = $this->snapshot();
    $this->now += CostBackfillService::PREVIEW_TTL_MS + 1;
    $this->reject('stale_preview', fn() => $this->backfill->execute($preview, 'verified'));
    $this->now -= CostBackfillService::PREVIEW_TTL_MS + 1;
    $this->actor = '43';
    $this->reject('invalid_preview', fn() => $this->backfill->execute($preview, 'verified'));
    $this->actor = '42';
    foreach (['version_hash', 'created_at', 'amount_micros', 'currency', 'entries'] as $key) {
      $invalid = $preview;
      unset($invalid[$key], $invalid['hash']);
      $invalid['hash'] = hash('sha256', json_encode($invalid, JSON_THROW_ON_ERROR));
      $this->reject('invalid_preview', fn() => $this->backfill->execute($invalid, 'verified'));
    }
    $this->assertSame($before, $this->snapshot());
    $this->database->update(PriceBookService::TABLE)->fields(['source_ref' => 'changed'])->condition('id', $id)->execute();
    $this->reject('stale_preview', fn() => $this->backfill->execute($preview, 'verified'));
    $this->assertCount(1, $this->rows(CostRatingService::TABLE));
    $this->assertSame([], $this->rows(CostBackfillService::TABLE));
  }

  public function testCurrentPermissionsScopeAndSharedConsumerLockAreRequired(): void {
    $this->ingestEvents([self::event('a')]);
    $id = $this->history();
    $preview = $this->backfill->preview($id);
    foreach (['administer xinshi ai pricing', 'view site ai usage', 'view ai supplier costs'] as $permission) {
      $this->denied = $permission;
      $this->reject('access_denied', fn() => $this->backfill->preview($id));
      $this->reject('access_denied', fn() => $this->backfill->execute($preview, 'verified'));
    }
    $this->denied = '';
    $this->authenticated = FALSE;
    $this->reject('access_denied', fn() => $this->backfill->preview($id));
    $this->authenticated = TRUE;
    $foreign = $this->books->createDraft('site-b', self::KIND, 'foreign', 'CNY', self::rates(), self::START, 'quote', '42', $this->now);
    $this->books->activate($foreign, 'site-b', self::KIND, '42');
    $this->reject('version_not_found', fn() => $this->backfill->preview($foreign));
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects($this->once())->method('acquire')->with(UsageProjectionService::LOCK_NAME, 300.0)->willReturn(FALSE);
    $this->reject('projection_busy', fn() => $this->service($lock)->execute($preview, 'verified'));
    $this->assertSame([], $this->rows(CostBackfillService::TABLE));
  }

  public function testCandidateScopeExcludesOtherModelsPayersKindsTimesAndSavedCosts(): void {
    $events = [self::event('eligible')];
    foreach (['model', 'account', 'payer', 'image', 'before', 'end', 'missing', 'saved'] as $name) {
      $event = self::event($name);
      if ($name === 'model') { $event['provider']['resolved_model'] = 'other'; }
      if ($name === 'account') { $event['provider']['account_ref'] = 'other'; }
      if ($name === 'payer') { $event['context']['payer'] = 'customer_key'; }
      if ($name === 'image') {
        $event['usage']['normalizer_version'] = 'images-v1';
        $event['usage']['images_generated'] = '1';
      }
      if ($name === 'before') { $event['occurred_at'] = '2023-11-14T22:13:24.119Z'; }
      if ($name === 'end') { $event['occurred_at'] = '2023-11-14T22:15:00.250Z'; }
      if ($name === 'missing') { $event['usage'] = NULL; }
      $events[] = $event;
    }
    $this->ingestEvents($events);
    $id = $this->history();
    $saved = self::event('saved');
    $this->rating->rateAttempt('site-a', 'saved', 1, $saved['provider'], $saved['usage'], self::START,
      CostRatingService::SOURCE_SUPPLIER_RECONCILIATION);
    $this->assertSame(['eligible'], array_column($this->backfill->preview($id)['entries'], 'attempt_id'));
  }

  public function testBoundedPreviewContinuesToNextBatch(): void {
    $this->ingestEvents(array_map(fn($n) => self::event('batch-' . $n), range(1, 201)));
    $id = $this->history();
    $first = $this->backfill->preview($id);
    $this->assertCount(200, $first['entries']);
    $this->assertNotNull($first['next_after_id']);
    $this->backfill->execute($first, 'verified');
    $next = $this->backfill->preview($id, $first['next_after_id']);
    $this->assertCount(1, $next['entries']);
    $this->assertNull($next['next_after_id']);
    $this->backfill->execute($next, 'verified');
    $this->assertCount(201, $this->rows(CostBackfillService::TABLE));
    $this->assertSame(201 * 365, (int) $this->rows(UsageProjectionService::ROLLUP_TABLE)[0]['cost_micros']);
  }

  public function testImageCostsUseTheirOwnHistoricalBook(): void {
    $event = self::event('image');
    $event['usage']['normalizer_version'] = 'images-v1';
    foreach (array_keys($event['usage']) as $key) {
      if (str_contains($key, 'tokens')) {
        $event['usage'][$key] = NULL;
      }
    }
    $event['usage']['images_generated'] = '2';
    $this->ingestEvents([$event]);
    $id = $this->books->createDraft('site-a', PriceBookService::KIND_SUPPLIER_IMAGE,
      'images', 'CNY', '{"accounts":{"xinshi":{"models":{"model-a":{"per_image":5000}}}}}',
      self::START, 'image-contract', '42', $this->now);
    $this->books->activate($id, 'site-a', PriceBookService::KIND_SUPPLIER_IMAGE, '42');
    $preview = $this->backfill->preview($id);
    $this->assertSame('10000', $preview['amount_micros']);
    $this->backfill->execute($preview, 'verified');
    $this->assertSame(10000, (int) $this->rows(UsageProjectionService::ATTEMPT_TABLE)[0]['cost_total_micros']);
    $this->assertSame(2, (int) $this->rows(UsageProjectionService::ROLLUP_TABLE)[0]['images_generated']);
  }

  public function testInvalidCostIsSkippedWithoutCreatingAZeroCost(): void {
    $event = self::event('overflow');
    $event['usage'] = array_replace($event['usage'], ['input_tokens_total' => (string) PHP_INT_MAX,
      'input_tokens_cache_read' => '0', 'output_tokens_total' => '0', 'provider_total_tokens' => (string) PHP_INT_MAX]);
    $this->ingestEvents([$event]);
    $id = $this->history();
    $before = $this->snapshot();
    $preview = $this->backfill->preview($id);
    $this->assertSame([], $preview['entries']);
    $this->assertSame(['cost_overflow' => 1], $preview['skipped']);
    $this->assertSame($before, $this->snapshot());
  }

  public function testRollupFailureRollsBackAppendedCostsReceiptsAndProjection(): void {
    if (getenv('XINSHI_USAGE_ISOLATED_MYSQL') === '1') {
      $this->markTestSkipped('The injected SQLite trigger tests transaction rollback.');
    }
    $this->ingestEvents([self::event('a'), self::event('b')]);
    $preview = $this->backfill->preview($this->history());
    $before = $this->snapshot();
    $this->database->query("CREATE TRIGGER reject_rollup BEFORE INSERT ON {ai_usage_rollup_hour} "
      . "BEGIN SELECT RAISE(ABORT, 'injected rollup failure'); END", [],
      ['allow_delimiter_in_query' => TRUE]);
    try {
      $this->backfill->execute($preview, 'verified');
      $this->fail('The rollup write should fail.');
    }
    catch (\Drupal\Core\Database\IntegrityConstraintViolationException $e) {
      $this->assertStringContainsString('injected rollup failure', $e->getMessage());
    }
    $this->assertSame($before, $this->snapshot());
    $this->assertSame([], $this->rows(CostBackfillService::TABLE));
  }

  public function testCachedFormRetainsPreviewAndRequiresExplicitConfirmation(): void {
    $this->ingestEvents([self::event('a')]);
    $id = $this->history();
    $form = unserialize(serialize(CostBackfillForm::create(\Drupal::getContainer())));
    $state = new FormState();
    $built = $form->buildForm([], $state, $id);
    $this->assertArrayNotHasKey('execute', $built);
    $form->previewBatch($built, $state);
    $preview = $state->get('preview');
    $built = $form->buildForm([], $state, $id);
    $this->assertArrayHasKey('execute', $built);
    $this->assertSame($preview, $state->get('preview'));
    $state->setTriggeringElement($built['execute'])->setValue('reason', 'verified');
    $form->validateForm($built, $state);
    $this->assertArrayHasKey('confirm', $state->getErrors());
    $state->clearErrors();
    $state->setValue('confirm', TRUE);
    $form->submitForm($built, $state);
    $this->assertSame(1, $state->get('result')['count']);
    $routes = Yaml::parseFile(dirname(__DIR__, 3) . '/xinshi_ai_usage.routing.yml');
    $this->assertSame('administer xinshi ai pricing,view site ai usage,view ai supplier costs',
      $routes['xinshi_ai_usage.cost_backfill']['requirements']['_permission']);
    $definition = $routes['xinshi_ai_usage.cost_backfill'];
    $route = new Route($definition['path'], $definition['defaults'], $definition['requirements']);
    $access = new PermissionAccessCheck();
    $this->assertTrue($access->access($route, $this->account)->isAllowed());
    foreach (['administer xinshi ai pricing', 'view site ai usage', 'view ai supplier costs'] as $permission) {
      $this->denied = $permission;
      $this->assertFalse($access->access($route, $this->account)->isAllowed());
    }
  }

  public function testSchemaUpgradePreservesOldFactsAndIsIdempotent(): void {
    $this->ingestEvents([self::event('a')]);
    $id = $this->books->createDraft('site-a', self::KIND, 'existing', 'CNY', self::rates(), $this->now);
    $schema = $this->database->schema();
    $schema->dropTable(CostBackfillService::TABLE);
    $schema->dropField(PriceBookService::TABLE, 'activated_at');
    $schema->dropField(PriceBookService::TABLE, 'activated_by');
    $before = $this->snapshot();
    xinshi_ai_usage_update_10010();
    xinshi_ai_usage_update_10010();
    foreach ($before as $table => $rows) {
      $this->assertSame($rows, $this->rows($table));
    }
    $this->assertNull($this->books->loadVersion($id)['activated_by']);
    $this->assertSame([], $this->rows(CostBackfillService::TABLE));
  }

  public function testConcurrentPublicationAndDuplicateBackfillOnMysql(): void {
    if (getenv('XINSHI_USAGE_ISOLATED_MYSQL') !== '1') {
      $this->markTestSkipped('Requires the disposable, network-none MySQL fixture.');
    }
    $this->ingestEvents([self::event('a'), self::event('b')]);
    $versions = [];
    foreach (['first', 'second'] as $name) {
      $versions[] = $this->books->createDraft('site-a', self::KIND, $name, 'CNY', self::rates(),
        self::START, 'contract', '42', $this->now);
    }
    $results = $this->workers('activate', $versions);
    $this->assertCount(1, array_filter($results, fn($r) => $r['status'] === 'published'));
    $this->assertCount(1, array_filter($results, fn($r) => $r['status'] === 'overlapping_version'));
    $id = (int) $this->books->loadActive('site-a', self::KIND, self::START)['id'];
    $preview = $this->backfill->preview($id);
    $file = tempnam(sys_get_temp_dir(), 'usage-preview-');
    try {
      file_put_contents($file, json_encode($preview, JSON_THROW_ON_ERROR));
      $results = $this->workers('execute', [$file, $file]);
      $this->assertCount(1, array_filter($results, fn($r) => $r['status'] === 'written'));
      $this->assertCount(1, array_filter($results, fn($r) => in_array($r['status'], ['replayed', 'projection_busy'], TRUE)));
      $this->assertTrue($this->backfill->execute($preview, 'verified')['replayed']);
      $this->assertCount(2, $this->rows(CostBackfillService::TABLE));
      $this->assertCount(4, $this->rows(CostRatingService::TABLE));
      $this->assertSame(730, (int) $this->rows(UsageProjectionService::ROLLUP_TABLE)[0]['cost_micros']);
    }
    finally {
      unlink($file);
    }
  }

  private function workers(string $mode, array $arguments): array {
    $gate = tempnam(sys_get_temp_dir(), 'usage-gate-');
    unlink($gate);
    $processes = $streams = [];
    try {
      foreach ($arguments as $index => $argument) {
        $processes[$index] = proc_open([PHP_BINARY, '-d', 'extension=pdo_mysql', '-d',
          'extension=pdo_sqlite', dirname(__DIR__, 2) . '/cost-backfill-worker.php', $mode,
          (string) $argument, $gate, (string) $this->now],
          [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $streams[$index]);
      }
      touch($gate);
      $results = [];
      foreach ($processes as $index => $process) {
        fclose($streams[$index][0]);
        $output = stream_get_contents($streams[$index][1]);
        $error = stream_get_contents($streams[$index][2]);
        fclose($streams[$index][1]);
        fclose($streams[$index][2]);
        $this->assertSame(0, proc_close($process), $error . $output);
        $results[] = json_decode($output, TRUE, 64, JSON_THROW_ON_ERROR);
      }
      return $results;
    }
    finally {
      if (is_file($gate)) {
        unlink($gate);
      }
    }
  }

  private function service(LockBackendInterface $lock): CostBackfillService {
    return new CostBackfillService($this->database, $this->books, $this->rating, $this->projection,
      $this->time, $this->account, $this->settings, $this->vault, $lock);
  }

  private function history(): int {
    $id = $this->books->createDraft('site-a', self::KIND, 'history', 'CNY', self::rates(),
      self::START, 'contract-2023', '42', $this->now);
    $this->books->activate($id, 'site-a', self::KIND, '42');
    return $id;
  }

  private function ingestEvents(array $events): void {
    foreach ($this->ingest->ingest($events, 'chat-node', 'site-a') as $receipt) {
      $this->assertSame('accepted', $receipt['status']);
    }
    $stats = $this->projection->process('site-a', count($events));
    $this->assertSame(0, $stats['failed']);
  }

  private function snapshot(): array {
    $result = [];
    foreach ([UsageIngestService::TABLE, CostRatingService::TABLE,
      UsageProjectionService::ATTEMPT_TABLE, UsageProjectionService::ROLLUP_TABLE] as $table) {
      $result[$table] = $this->rows($table);
    }
    return $result;
  }

  private function rows(string $table): array {
    return $this->database->select($table, 'r')->fields('r')->orderBy('id')
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  private function reject(string $code, callable $action): void {
    try {
      $action();
      $this->fail('Expected ' . $code);
    }
    catch (PriceBookException $e) {
      $this->assertSame($code, $e->priceCode);
    }
  }

  private static function rates(): string {
    return json_encode(['accounts' => ['xinshi' => ['models' => ['model-a' => [
      'per_million_input' => 2_000_000, 'per_million_cache_read' => 200_000,
      'per_million_cache_write' => 1_000_000, 'per_million_output' => 8_000_000]]]]]);
  }

  private static function event(string $attempt, int $revision = 1): array {
    return ['schema_version' => 1, 'event_id' => "$attempt:observed:$revision", 'producer_id' => 'chat-node',
      'site_id' => 'site-a', 'event_type' => 'attempt.observed', 'operation_id' => 'op-' . $attempt,
      'authorization_id' => NULL, 'attempt_id' => $attempt, 'logical_call_id' => 'lc-' . $attempt,
      'observation_revision' => $revision, 'occurred_at' => '2023-11-14T22:13:24.120Z',
      'context' => ['billing_account_id' => NULL, 'actor_user_id' => '7', 'feature' => 'common',
        'stage' => 'classifier', 'attempt_no' => 1, 'payer' => 'platform', 'billing_role' => 'auxiliary',
        'chat_run_id' => NULL, 'task_id' => NULL, 'chat_id' => NULL],
      'provider' => ['account_ref' => 'xinshi', 'requested_model' => 'model-a', 'resolved_model' => 'model-a',
        'gateway_request_id' => NULL, 'provider_request_id' => NULL], 'dispatch_state' => 'sent',
      'outcome' => 'succeeded', 'error_code' => NULL,
      'usage' => ['normalizer_version' => 'chat-completions-inclusive-v1', 'quality' => 'reported',
        'input_tokens_total' => '120', 'input_tokens_cache_read' => '64', 'input_tokens_cache_write' => NULL,
        'output_tokens_total' => '30', 'output_tokens_reasoning' => NULL, 'provider_total_tokens' => '150',
        'images_generated' => NULL, 'raw_usage' => NULL, 'raw_usage_hash' => NULL],
      'timing' => ['started_at' => '2023-11-14T22:13:20.000Z', 'first_token_at' => NULL,
        'finished_at' => '2023-11-14T22:13:24.100Z']];
  }

}
