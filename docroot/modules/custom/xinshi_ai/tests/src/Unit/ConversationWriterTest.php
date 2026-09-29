<?php

declare(strict_types=1);

namespace Drupal\Tests\xinshi_ai\Unit;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Drupal\node\NodeInterface;
use Drupal\node\NodeStorageInterface;
use Drupal\xinshi_ai\Controller\ConversationController;
use Drupal\xinshi_ai\Service\ConversationWriter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Yaml\Yaml;

/** Exercises the transaction boundary with persisted entity snapshots and aliased fields. */
final class ConversationWriterTest extends TestCase {

  private const CHAT = '11111111-1111-4111-8111-111111111111';
  private const A = '22222222-2222-4222-8222-222222222222';
  private const B = '33333333-3333-4333-8333-333333333333';
  private Connection $db;
  private ConversationWriter $writer;
  private array $cache = [];
  private string $uid = '7';
  private bool $nodeAccess = TRUE;
  private bool $fieldAccess = TRUE;
  private bool $failSave = FALSE;
  private array $loads = [];

  protected function setUp(): void {
    $mysql = getenv('CONVERSATION_TEST_MYSQL_HOST');
    Database::addConnectionInfo('conversation_test', 'default', $mysql ? [
      'driver' => 'mysql', 'database' => 'harness_test', 'host' => $mysql,
      'username' => 'harness', 'password' => 'harness-test-only', 'prefix' => 'cw_test_',
      'namespace' => 'Drupal\\mysql\\Driver\\Database\\mysql',
    ] : [
      'driver' => 'sqlite', 'database' => ':memory:',
      'namespace' => 'Drupal\\sqlite\\Driver\\Database\\sqlite',
    ]);
    $this->db = Database::getConnection('default', 'conversation_test');
    if (!getenv('CONVERSATION_TEST_WORKER')) {
      $this->db->schema()->dropTable('node');
      $this->db->schema()->createTable('node', [
        'fields' => [
          'nid' => ['type' => 'int', 'not null' => TRUE],
          'uuid' => ['type' => 'varchar', 'length' => 36, 'not null' => TRUE],
          'type' => ['type' => 'varchar', 'length' => 32, 'not null' => TRUE],
          'data' => ['type' => 'text', 'not null' => TRUE],
        ], 'primary key' => ['nid'],
      ]);
      foreach ([1 => [self::CHAT, 'conversation'], 2 => [self::A, 'ai_session'], 3 => [self::B, 'ai_session']] as $nid => [$uuid, $type]) {
        $this->db->insert('node')->fields(['nid' => $nid, 'uuid' => $uuid, 'type' => $type,
          'data' => json_encode(['uid' => '7', 'title' => 'Initial', 'field_ai_optimized' => FALSE,
            'field_sessions' => [], 'sticky' => FALSE])])->execute();
      }
    }
    $storage = $this->createMock(NodeStorageInterface::class);
    $storage->method('resetCache')->willReturnCallback(function (?array $ids = NULL): void {
      $this->loads[] = 'reset';
      foreach ($ids ?? array_keys($this->cache) as $id) unset($this->cache[$id]);
    });
    $storage->method('load')->willReturnCallback(function ($nid) {
      $this->loads[] = 'load';
      $entity = $this->cache[$nid] ??= $this->entity((int) $nid);
      if (getenv('CONVERSATION_TEST_WORKER') === 'A') {
        // Hold an already loaded snapshot while a second process attempts to append.
        $barrier = getenv('CONVERSATION_TEST_BARRIER');
        touch($barrier);
        $deadline = microtime(TRUE) + 20;
        while (!file_exists($barrier . '.second') && microtime(TRUE) < $deadline) usleep(10000);
        if (!file_exists($barrier . '.second')) throw new \RuntimeException('Second worker did not start.');
        usleep(500000);
      }
      return $entity;
    });
    $storage->method('loadByProperties')->willReturnCallback(function (array $properties): array {
      $nid = $this->db->select('node', 'n')->fields('n', ['nid'])
        ->condition('uuid', $properties['uuid'])->condition('type', $properties['type'])
        ->execute()->fetchField();
      return $nid ? [$this->entity((int) $nid)] : [];
    });
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->with('node')->willReturn($storage);
    $account = $this->createMock(AccountProxyInterface::class);
    $account->method('id')->willReturnCallback(fn() => $this->uid);
    $account->method('isAuthenticated')->willReturnCallback(fn() => $this->uid !== '0');
    $resource = $this->createMock(ResourceType::class);
    $resource->method('getInternalName')->willReturnCallback(fn($name) =>
      in_array($name, ['sessions', 'ai_optimized'], TRUE) ? 'field_' . $name : $name);
    $resource->method('isFieldEnabled')->willReturn(TRUE);
    $resources = $this->createMock(ResourceTypeRepositoryInterface::class);
    $resources->method('get')->with('node', 'conversation')->willReturn($resource);
    $this->writer = new ConversationWriter($this->db, $entities, $account, $resources);
  }

  protected function tearDown(): void {
    Database::removeConnection('conversation_test');
    parent::tearDown();
  }

  public function testAppendsReloadStaleEntitiesAndDeduplicateRetries(): void {
    $stale = $this->entity(1);
    $this->cache[1] = $stale;
    $this->writer->update(self::CHAT, [self::A], []);
    // A second request can have loaded the old entity before the first writer committed.
    $this->cache[1] = $stale;
    $this->writer->update(self::CHAT, [self::B, self::B], []);
    $this->writer->update(self::CHAT, [self::A], []);
    $this->assertSame([['target_id' => 2], ['target_id' => 3]], $this->data()['field_sessions']);
    $this->assertSame(['reset', 'load', 'reset', 'load', 'reset', 'load'], $this->loads);
  }

  public function testAttributeEditsPreserveSessionsAndAnOptimizedTitle(): void {
    $this->writer->update(self::CHAT, [self::A], ['title' => 'Initial']);
    $this->writer->update(self::CHAT, [], ['title' => 'Generated', 'ai_optimized' => TRUE]);
    $this->writer->update(self::CHAT, [self::B], ['title' => 'Stale']);
    $result = $this->writer->update(self::CHAT, [], ['sticky' => TRUE]);
    $this->assertSame('Generated', $result['title']);
    $this->assertTrue($result['ai_optimized']);
    $this->assertCount(2, $this->data()['field_sessions']);
    $this->assertTrue($this->data()['sticky']);
  }

  public function testDeletedReferencesAreRemovedOnAppend(): void {
    $this->writer->update(self::CHAT, [self::A], []);
    $this->db->delete('node')->condition('nid', 2)->execute();
    $this->writer->update(self::CHAT, [self::B], []);
    $this->assertSame([['target_id' => 3]], $this->data()['field_sessions']);
  }

  #[DataProvider('invalidUpdates')]
  public function testInvalidUpdatesLeaveTheConversationUnchanged(array $sessions, array $attrs): void {
    $before = $this->data();
    try {
      $this->writer->update(self::CHAT, $sessions, $attrs);
      $this->fail('Expected rejection.');
    }
    catch (HttpException $error) {
      $this->assertSame(422, $error->getStatusCode());
    }
    $this->assertSame($before, $this->data());
  }

  public static function invalidUpdates(): array {
    return [[['invalid'], []], [[], ['uid' => 8]], [[], ['title' => ['invalid']]],
      [['not-a-list' => self::A], []]];
  }

  #[DataProvider('deniedUpdates')]
  public function testPermissionsAndOwnershipAreRequired(string $denial): void {
    if ($denial === 'owner') $this->uid = '8';
    if ($denial === 'anonymous') $this->uid = '0';
    if ($denial === 'node') $this->nodeAccess = FALSE;
    if ($denial === 'field') $this->fieldAccess = FALSE;
    if ($denial === 'session') $this->writeData(2, [...$this->data(2), 'uid' => '8']);
    $before = $this->data();
    try {
      $this->writer->update(self::CHAT, [self::A], []);
      $this->fail('Expected denial.');
    }
    catch (HttpException $error) {
      $this->assertSame(403, $error->getStatusCode());
    }
    $this->assertSame($before, $this->data());
  }

  public static function deniedUpdates(): array {
    return array_map(fn($reason) => [$reason], ['owner', 'anonymous', 'node', 'field', 'session']);
  }

  public function testFailedSavesRollbackAndCanBeRetried(): void {
    $this->writer->update(self::CHAT, [self::A], []);
    $before = $this->data();
    $this->failSave = TRUE;
    try {
      $this->writer->update(self::CHAT, [self::B], ['title' => 'Uncommitted']);
      $this->fail('Expected write failure.');
    }
    catch (\RuntimeException $error) {
      $this->assertSame('save failed', $error->getMessage());
    }
    $this->assertSame($before, $this->data());
    $this->failSave = FALSE;
    $this->writer->update(self::CHAT, [self::B], []);
    $this->assertCount(2, $this->data()['field_sessions']);
  }

  public function testTheRouteIsOauthOnlyAndControllerRejectsUnknownKeys(): void {
    $routes = Yaml::parseFile(dirname(__DIR__, 3) . '/xinshi_ai.routing.yml');
    $route = $routes['xinshi_ai.conversation.update'];
    $this->assertSame(['POST'], $route['methods']);
    $this->assertSame(['oauth2'], $route['options']['_auth']);
    $this->assertSame('TRUE', $route['requirements']['_user_is_logged_in']);
    $controller = new ConversationController($this->writer);
    $response = $controller->update(self::CHAT, new Request(content: json_encode([
      'sessions' => [self::A], 'attributes' => (object) [],
    ])));
    $this->assertSame(200, $response->getStatusCode());
    $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
    $this->expectException(HttpException::class);
    $controller->update(self::CHAT, new Request(content: '{"sessions":[],"attributes":{},"uid":8}'));
  }

  public function testConcurrentWritersKeepBothResults(): void {
    if (!getenv('CONVERSATION_TEST_MYSQL_HOST')) {
      $this->markTestSkipped('Requires an isolated MariaDB test container.');
    }
    $barrier = tempnam(sys_get_temp_dir(), 'cw-barrier-');
    unlink($barrier);
    $workers = [];
    $logs = [];
    try {
      foreach (['A', 'B'] as $worker) {
        $log = tempnam(sys_get_temp_dir(), 'cw-worker-');
        $logs[] = $log;
        $command = [PHP_BINARY, '-d', 'extension=pdo_mysql', $_SERVER['argv'][0], '--bootstrap',
          dirname(__DIR__, 2) . '/bootstrap.php', '--filter', 'testConcurrentAppendWorker', __FILE__];
        $process = proc_open($command, [0 => ['file', '/dev/null', 'r'],
          1 => ['file', $log, 'w'], 2 => ['file', $log, 'a']], $pipes, NULL,
          [...getenv(), 'CONVERSATION_TEST_WORKER' => $worker, 'CONVERSATION_TEST_BARRIER' => $barrier]);
        $this->assertIsResource($process);
        $workers[] = $process;
        if ($worker === 'A') {
          $deadline = microtime(TRUE) + 20;
          while (!file_exists($barrier) && microtime(TRUE) < $deadline) usleep(10000);
          $this->assertFileExists($barrier, file_get_contents($log));
        }
      }
      foreach ($workers as $index => $process) {
        $status = proc_close($process);
        unset($workers[$index]);
        $this->assertSame(0, $status, file_get_contents($logs[$index]));
      }
      $this->assertSame([['target_id' => 2], ['target_id' => 3]], $this->data()['field_sessions']);
    }
    finally {
      foreach ($workers as $process) { proc_terminate($process); proc_close($process); }
      foreach ([$barrier, $barrier . '.second', ...$logs] as $path) if (file_exists($path)) unlink($path);
    }
  }

  public function testConcurrentAppendWorker(): void {
    $worker = getenv('CONVERSATION_TEST_WORKER');
    if (!$worker) $this->markTestSkipped('Executed by the concurrent writer test.');
    if ($worker === 'B') touch(getenv('CONVERSATION_TEST_BARRIER') . '.second');
    $result = $this->writer->update(self::CHAT, [$worker === 'A' ? self::A : self::B], []);
    $this->assertSame(self::CHAT, $result['id']);
  }

  private function data(int $nid = 1): array {
    $data = $this->db->select('node', 'n')->fields('n', ['data'])->condition('nid', $nid)
      ->execute()->fetchField();
    return $data ? json_decode($data, TRUE) : [];
  }

  private function writeData(int $nid, array $data): void {
    $this->db->update('node')->fields(['data' => json_encode($data)])->condition('nid', $nid)->execute();
  }

  private function entity(int $nid): ?NodeInterface {
    $values = $this->data($nid);
    if (!$values) return NULL;
    $node = $this->createMock(NodeInterface::class);
    $node->method('id')->willReturn($nid);
    $node->method('uuid')->willReturn([1 => self::CHAT, 2 => self::A, 3 => self::B][$nid]);
    $node->method('bundle')->willReturn($nid === 1 ? 'conversation' : 'ai_session');
    $node->method('getOwnerId')->willReturn($values['uid']);
    $node->method('access')->willReturnCallback(fn() => $this->nodeAccess);
    $node->method('hasField')->willReturn(TRUE);
    $node->method('get')->willReturnCallback(function ($name) use (&$values) {
      $field = $this->createMock(EntityReferenceFieldItemListInterface::class);
      $field->method('access')->willReturnCallback(fn() => $this->fieldAccess);
      $field->method('__get')->willReturnCallback(fn($key) => $key === 'value' ? ($values[$name] ?? NULL) : NULL);
      $field->method('referencedEntities')->willReturnCallback(fn() => array_filter(array_map(
        fn($ref) => $this->entity((int) $ref['target_id']), $values[$name] ?? [])));
      return $field;
    });
    $node->method('set')->willReturnCallback(function ($name, $value) use (&$values, $node) {
      $values[$name] = $value;
      return $node;
    });
    $node->method('label')->willReturnCallback(fn() => $values['title']);
    $node->method('validate')->willReturn(new ConstraintViolationList());
    $node->method('save')->willReturnCallback(function () use ($nid, &$values) {
      $this->writeData($nid, $values);
      if ($this->failSave) throw new \RuntimeException('save failed');
      return 2;
    });
    return $node;
  }

}
