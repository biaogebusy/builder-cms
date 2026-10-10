<?php

declare(strict_types=1);

namespace Drupal\Tests\xinshi_ai\Unit;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Drupal\node\NodeInterface;
use Drupal\node\NodeStorageInterface;
use Drupal\xinshi_ai\Controller\SessionContentController;
use Drupal\xinshi_ai\Service\SessionContentWriter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Yaml\Yaml;

/** Uses real database transactions with cached entity snapshots and aliased fields. */
final class SessionContentWriterTest extends TestCase {

  private const CHAT = '11111111-1111-4111-8111-111111111111';
  private const SESSION = '22222222-2222-4222-8222-222222222222';
  private Connection $db;
  private SessionContentWriter $writer;
  private array $cache = [];
  private string $uid = '7';
  private string $denied = '';
  private bool $failSave = FALSE;
  private bool $invalid = FALSE;
  private array $loads = [];

  protected function setUp(): void {
    $mysql = getenv('SESSION_CONTENT_TEST_MYSQL_HOST');
    Database::addConnectionInfo('session_content_test', 'default', $mysql ? [
      'driver' => 'mysql', 'database' => 'harness_test', 'host' => $mysql,
      'username' => 'harness', 'password' => 'harness-test-only', 'prefix' => 'scw_test_',
      'namespace' => 'Drupal\\mysql\\Driver\\Database\\mysql',
    ] : [
      'driver' => 'sqlite', 'database' => ':memory:',
      'namespace' => 'Drupal\\sqlite\\Driver\\Database\\sqlite',
    ]);
    $this->db = Database::getConnection('default', 'session_content_test');
    if (!getenv('SESSION_CONTENT_TEST_WORKER')) {
      $this->db->schema()->dropTable('node');
      $this->db->schema()->createTable('node', [
        'fields' => [
          'nid' => ['type' => 'int', 'not null' => TRUE],
          'uuid' => ['type' => 'varchar', 'length' => 36, 'not null' => TRUE],
          'type' => ['type' => 'varchar', 'length' => 32, 'not null' => TRUE],
          'data' => ['type' => 'text', 'not null' => TRUE],
        ], 'primary key' => ['nid'],
      ]);
      foreach ([1 => [self::CHAT, 'conversation'], 2 => [self::SESSION, 'ai_session']] as $nid => [$uuid, $type]) {
        $this->db->insert('node')->fields(['nid' => $nid, 'uuid' => $uuid, 'type' => $type,
          'data' => json_encode(['uid' => '7', 'field_sessions' => [['target_id' => 2]],
            'field_content' => 'initial source', 'title' => 'Unchanged title'])])->execute();
      }
    }
    $storage = $this->createMock(NodeStorageInterface::class);
    $storage->method('resetCache')->willReturnCallback(function (?array $ids = NULL): void {
      $this->loads[] = 'reset';
      foreach ($ids ?? array_keys($this->cache) as $id) unset($this->cache[$id]);
    });
    $storage->method('load')->willReturnCallback(function ($nid) {
      $this->loads[] = 'load';
      $node = $this->cache[$nid] ??= $this->entity((int) $nid);
      if ($nid === 2 && getenv('SESSION_CONTENT_TEST_WORKER') === 'A') {
        $barrier = getenv('SESSION_CONTENT_TEST_BARRIER');
        touch($barrier);
        $deadline = microtime(TRUE) + 20;
        while (!file_exists($barrier . '.second') && microtime(TRUE) < $deadline) usleep(10000);
        if (!file_exists($barrier . '.second')) throw new \RuntimeException('Second writer did not start.');
        usleep(500000);
      }
      return $node;
    });
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->with('node')->willReturn($storage);
    $account = $this->createMock(AccountProxyInterface::class);
    $account->method('id')->willReturnCallback(fn() => $this->uid);
    $account->method('isAuthenticated')->willReturnCallback(fn() => $this->uid !== '0');
    $resource = $this->createMock(ResourceType::class);
    $resource->method('getInternalName')->willReturnCallback(fn($name) => 'field_' . $name);
    $resource->method('isFieldEnabled')->willReturnCallback(fn() => $this->denied !== 'disabled');
    $resources = $this->createMock(ResourceTypeRepositoryInterface::class);
    $resources->method('get')->willReturn($resource);
    $this->writer = new SessionContentWriter($this->db, $entities, $account, $resources);
  }

  protected function tearDown(): void {
    Database::removeConnection('session_content_test');
    parent::tearDown();
  }

  public function testRejectsStaleSnapshotsAndReturnsTheCurrentContent(): void {
    $stale = $this->entity(2);
    $initial = hash('sha256', 'initial source');
    $this->assertSame(['updated' => TRUE, 'content' => 'new source'],
      $this->writer->update(self::CHAT, self::SESSION, $initial, 'new source'));
    $this->cache[2] = $stale;
    $this->assertSame(['updated' => FALSE, 'content' => 'new source'],
      $this->writer->update(self::CHAT, self::SESSION, $initial, 'old source and review'));
    $this->assertSame('new source', $this->data()['field_content']);
    $this->assertSame('Unchanged title', $this->data()['title']);
    $this->assertSame(['reset', 'load', 'load', 'reset', 'load', 'load'], $this->loads);
    $this->assertTrue($this->writer->update(self::CHAT, self::SESSION,
      hash('sha256', 'new source'), 'new source and review')['updated']);
  }

  #[DataProvider('denials')]
  public function testRequiresCurrentOwnershipAccessAndConversationMembership(string $denial): void {
    $this->denied = $denial;
    if ($denial === 'owner') $this->uid = '8';
    if ($denial === 'anonymous') $this->uid = '0';
    if ($denial === 'chat-owner') $this->writeData(1, [...$this->data(1), 'uid' => '8']);
    if ($denial === 'membership') $this->writeData(1, [...$this->data(1), 'field_sessions' => []]);
    $before = $this->data();
    try {
      $this->writer->update(self::CHAT, self::SESSION, hash('sha256', 'initial source'), 'changed');
      $this->fail('Expected denial.');
    }
    catch (HttpException $error) {
      $this->assertSame(403, $error->getStatusCode());
    }
    $this->assertSame($before, $this->data());
  }

  public static function denials(): array {
    return array_map(fn($reason) => [$reason], ['owner', 'anonymous', 'chat-owner', 'membership',
      'node-view-1', 'node-update-1', 'node-view-2', 'node-update-2',
      'field_content-view', 'field_content-edit', 'field_sessions-view', 'disabled']);
  }

  public function testDeletedMessagesAreNotRecreated(): void {
    $this->db->delete('node')->condition('nid', 2)->execute();
    $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
    $this->writer->update(self::CHAT, self::SESSION, hash('sha256', 'initial source'), 'changed');
  }

  #[DataProvider('saveFailures')]
  public function testValidationAndFailedSavesLeaveNoPartialUpdate(bool $invalid): void {
    $before = $this->data();
    $this->invalid = $invalid;
    $this->failSave = !$invalid;
    try {
      $this->writer->update(self::CHAT, self::SESSION, hash('sha256', 'initial source'), 'changed');
      $this->fail('Expected failure.');
    }
    catch (\RuntimeException $error) {
      $this->assertSame($invalid ? 'Invalid session fields.' : 'save failed', $error->getMessage());
    }
    $this->assertSame($before, $this->data());
  }

  public static function saveFailures(): array {
    return [[TRUE], [FALSE]];
  }

  public function testControllerReturnsNoStoreConflictsAndTheRouteIsOauthOnly(): void {
    $controller = new SessionContentController($this->writer);
    $request = new Request(content: json_encode(['expectedHash' => hash('sha256', 'initial source'),
      'content' => 'changed']));
    foreach ([200, 409] as $status) {
      $response = $controller->update(self::CHAT, self::SESSION, $request);
      $this->assertSame($status, $response->getStatusCode());
      $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
      $this->assertSame('changed', json_decode($response->getContent(), TRUE)['content']);
    }
    $route = Yaml::parseFile(dirname(__DIR__, 3) . '/xinshi_ai.routing.yml')['xinshi_ai.session.content'];
    $this->assertSame(['PATCH'], $route['methods']);
    $this->assertSame(['oauth2'], $route['options']['_auth']);
    $this->assertSame('TRUE', $route['requirements']['_user_is_logged_in']);
  }

  #[DataProvider('invalidInputs')]
  public function testControllerRejectsMalformedInput(array $input, int $status): void {
    $before = $this->data();
    try {
      (new SessionContentController($this->writer))->update(self::CHAT, self::SESSION,
        new Request(content: json_encode($input)));
      $this->fail('Expected validation failure.');
    }
    catch (HttpException $error) {
      $this->assertSame($status, $error->getStatusCode());
    }
    $this->assertSame($before, $this->data());
  }

  public static function invalidInputs(): array {
    $valid = ['expectedHash' => hash('sha256', 'initial source'), 'content' => 'changed'];
    return [[[], 400], [[...$valid, 'uid' => 8], 400], [[...$valid, 'content' => []], 400],
      [[...$valid, 'expectedHash' => 'bad'], 422],
      [[...$valid, 'content' => str_repeat('x', 4 * 1024 * 1024 + 1)], 422]];
  }

  public function testConcurrentWritersCannotBothReplaceTheSameVersion(): void {
    if (!getenv('SESSION_CONTENT_TEST_MYSQL_HOST')) {
      $this->markTestSkipped('Requires an isolated MariaDB test container.');
    }
    $barrier = tempnam(sys_get_temp_dir(), 'scw-barrier-');
    unlink($barrier);
    $workers = [];
    $logs = [];
    try {
      foreach (['A', 'B'] as $worker) {
        $log = tempnam(sys_get_temp_dir(), 'scw-worker-');
        $logs[] = $log;
        $command = [PHP_BINARY, '-d', 'extension=pdo_mysql', $_SERVER['argv'][0], '--bootstrap',
          dirname(__DIR__, 2) . '/bootstrap.php', '--filter', 'testConcurrentContentWorker', __FILE__];
        $process = proc_open($command, [0 => ['file', '/dev/null', 'r'],
          1 => ['file', $log, 'w'], 2 => ['file', $log, 'a']], $pipes, NULL,
          [...getenv(), 'SESSION_CONTENT_TEST_WORKER' => $worker, 'SESSION_CONTENT_TEST_BARRIER' => $barrier]);
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
      $this->assertSame('writer A', $this->data()['field_content']);
    }
    finally {
      foreach ($workers as $process) { proc_terminate($process); proc_close($process); }
      foreach ([$barrier, $barrier . '.second', ...$logs] as $path) if (file_exists($path)) unlink($path);
    }
  }

  public function testConcurrentContentWorker(): void {
    $worker = getenv('SESSION_CONTENT_TEST_WORKER');
    if (!$worker) $this->markTestSkipped('Executed by the concurrent content test.');
    if ($worker === 'B') touch(getenv('SESSION_CONTENT_TEST_BARRIER') . '.second');
    $result = $this->writer->update(self::CHAT, self::SESSION,
      hash('sha256', 'initial source'), 'writer ' . $worker);
    $this->assertSame($worker === 'A', $result['updated']);
    $this->assertSame('writer A', $result['content']);
  }

  private function data(int $nid = 2): array {
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
    $node->method('bundle')->willReturn($nid === 1 ? 'conversation' : 'ai_session');
    $node->method('getOwnerId')->willReturn($values['uid']);
    $node->method('access')->willReturnCallback(function ($op, $account) use ($nid): bool {
      $this->assertSame($this->uid, $account->id());
      return $this->denied !== "node-$op-$nid";
    });
    $node->method('hasField')->willReturn(TRUE);
    $node->method('get')->willReturnCallback(function ($name) use (&$values) {
      $field = $this->createMock(FieldItemListInterface::class);
      $field->method('access')->willReturnCallback(fn($op) => $this->denied !== "$name-$op");
      $field->method('__get')->willReturnCallback(fn($key) => $key === 'value' ? ($values[$name] ?? NULL) : NULL);
      $field->method('getValue')->willReturnCallback(fn() => $values[$name] ?? []);
      return $field;
    });
    $node->method('set')->willReturnCallback(function ($name, $value) use (&$values, $node) {
      $values[$name] = $value;
      return $node;
    });
    $node->method('validate')->willReturnCallback(fn() => new ConstraintViolationList($this->invalid
      ? [new ConstraintViolation('invalid', 'invalid', [], NULL, 'content', NULL)] : []));
    $node->method('save')->willReturnCallback(function () use ($nid, &$values) {
      $this->writeData($nid, $values);
      if ($this->failSave) throw new \RuntimeException('save failed');
      return 2;
    });
    return $node;
  }

}
