<?php

declare(strict_types=1);

/**
 * @file
 * Exercises actual Chat transactions under observed InnoDB row-lock contention.
 */

use Drupal\Core\DrupalKernel;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\node\Entity\Node;
use Symfony\Component\HttpFoundation\Request;

$root = dirname(__DIR__, 5);
if (getenv('XINSHI_CHAT_ISOLATED_TEST') !== '1' ||
  getenv('XINSHI_CHAT_TEST_MYSQL') !== '1' || !is_file('/.dockerenv') || $root !== '/app') {
  throw new RuntimeException('Use the disposable Chat MySQL runner only.');
}
if (isset($argv[1])) {
  if (!is_file('/tmp/chat-test/mysql-ready') ||
    !preg_match('@^/tmp/chat-test/race-[a-zA-Z0-9]+$@D', $argv[1])) {
    throw new RuntimeException('Unexpected worker input.');
  }
  $loader = require $root . '/vendor/autoload.php';
  $loader->addPsr4('Drupal\\Core\\', $root . '/docroot/core/lib/Drupal/Core');
  $loader->addPsr4('Drupal\\Component\\', $root . '/docroot/core/lib/Drupal/Component');
  require_once $root . '/docroot/core/includes/bootstrap.inc';
  chdir($root . '/docroot');
  $request = Request::create('http://chat-test/');
  $kernel = DrupalKernel::createFromRequest($request, $loader, 'prod', FALSE);
  $kernel->boot();
  $kernel->preHandle($request);
  $database = \Drupal::database()->getConnectionOptions();
  if ($database['host'] !== 'chat-db' || $database['database'] !== 'chat_test') {
    throw new RuntimeException('Unexpected worker database.');
  }
  $job = json_decode(file_get_contents($argv[1]), FALSE, 64, JSON_THROW_ON_ERROR);
  \Drupal::currentUser()->setAccount(User::load($job->actor ?? $job->uid));
  try {
    if ($job->action === 'delete') {
      \Drupal::service('xinshi_chat.conversation_deletion')->delete($job->uuid);
    }
    elseif ($job->action === 'append') {
      \Drupal::service('xinshi_chat.conversation_writer')->update($job->uuid, [$job->message], []);
    }
    else {
      $node = Node::load($job->nid);
      $node->set('sessions', [['target_id' => $job->messageId]]);
      $node->save();
    }
    $result = ['ok' => TRUE];
  }
  catch (\Throwable $error) {
    $cause = $error;
    while ($cause->getPrevious()) $cause = $cause->getPrevious();
    $result = ['error' => get_class($cause), 'message' => $cause->getMessage()];
    $request->attributes->set('_route', match ($job->action) {
      'delete' => 'xinshi_chat.conversation.delete',
      'append' => 'xinshi_ai.conversation.update',
      default => 'jsonapi.node--conversation.relationship.post',
    });
    $event = new \Symfony\Component\HttpKernel\Event\ExceptionEvent($kernel, $request,
      \Symfony\Component\HttpKernel\HttpKernelInterface::MAIN_REQUEST, $error);
    \Drupal::service('xinshi_ai_reference.responses')->onException($event);
    $result['status'] = $event->getResponse()?->getStatusCode();
    if ($cause instanceof \PDOException) {
      // Store diagnostics only in the synthetic fixture, never in the API response.
      file_put_contents('php://stderr', str_replace("\0", '', (string) $error) . PHP_EOL);
    }
  }
  file_put_contents($argv[1] . '.result', json_encode($result, JSON_THROW_ON_ERROR));
  exit;
}

require __DIR__ . '/install-fixture.php';
\Drupal::service('module_installer')->install(['xinshi_chat']);
touch('/tmp/chat-test/mysql-ready');
$checks = 0;
function check(bool $ok, string $label): void {
  if (!$ok) {
    echo json_encode($GLOBALS['results'] ?? [], JSON_THROW_ON_ERROR) . PHP_EOL;
    throw new RuntimeException($label);
  }
  $GLOBALS['checks']++;
  echo 'PASS: ' . $label . PHP_EOL;
}
function waitFor(callable $ready, string $label): void {
  $deadline = microtime(TRUE) + 20;
  do {
    if ($ready()) return;
    usleep(100000);
  } while (microtime(TRUE) < $deadline);
  throw new RuntimeException('Timed out: ' . $label);
}
function race(int $uid, int $held, array $jobs, ?callable $beforeRelease = NULL, ?callable $beforeLock = NULL): array {
  $observer = new PDO('mysql:host=chat-db;dbname=chat_test', 'chat', 'chat-test-only');
  $waits = str_contains($observer->query('SELECT VERSION()')->fetchColumn(), 'MariaDB')
    ? 'SELECT COUNT(DISTINCT requesting_trx_id) FROM information_schema.INNODB_LOCK_WAITS'
    : 'SELECT COUNT(DISTINCT REQUESTING_ENGINE_TRANSACTION_ID) FROM performance_schema.data_lock_waits';
  $transaction = \Drupal::database()->startTransaction();
  if ($beforeLock) $beforeLock();
  \Drupal::database()->select('node', 'n')->fields('n', ['nid'])
    ->condition('nid', $held)->forUpdate()->execute()->fetchField();
  $workers = [];
  try {
    foreach ($jobs as $job) {
      $file = tempnam('/tmp/chat-test', 'race-');
      file_put_contents($file, json_encode(['uid' => $uid] + $job, JSON_THROW_ON_ERROR));
      $process = proc_open([PHP_BINARY, '-d', 'extension=pdo_mysql', __FILE__, $file], [
        0 => ['file', '/dev/null', 'r'], 1 => ['file', $file . '.log', 'w'],
        2 => ['file', $file . '.error', 'w'],
      ], $pipes);
      if (!is_resource($process)) throw new RuntimeException('Cannot start write worker.');
      $workers[] = ['process' => $process, 'file' => $file];
    }
    waitFor(fn() => (int) $observer->query($waits)->fetchColumn() === count($jobs),
      'all writers waiting in InnoDB');
    check(TRUE, 'Observed ' . count($jobs) . ' independent write transactions waiting in InnoDB');
    if ($beforeRelease) $beforeRelease();
    unset($transaction);
    $results = [];
    foreach ($workers as $worker) {
      waitFor(fn() => is_file($worker['file'] . '.result'), 'worker completion');
      $results[] = json_decode(file_get_contents($worker['file'] . '.result'), TRUE, 64, JSON_THROW_ON_ERROR);
    }
    return $results;
  }
  finally {
    unset($transaction);
    foreach ($workers as $worker) {
      if (proc_get_status($worker['process'])['running']) proc_terminate($worker['process']);
      proc_close($worker['process']);
      foreach (['.log', '.error'] as $suffix) {
        $output = file_get_contents($worker['file'] . $suffix);
        if ($output !== '') echo $output;
      }
    }
  }
}

Role::create(['id' => 'chat_writer', 'label' => 'Chat writer', 'permissions' => [
  'access content', 'create conversation content', 'edit own conversation content',
  'delete own conversation content', 'create ai_session content', 'edit own ai_session content',
  'delete own ai_session content', 'view own unpublished content', 'use text format plain_text',
]])->save();
$user = User::create(['name' => 'chat-race', 'status' => 1, 'roles' => ['chat_writer']]);
$user->save();
$uid = (int) $user->id();
\Drupal::currentUser()->setAccount($user);
$storage = \Drupal::entityTypeManager()->getStorage('node');
function message(): Node {
  $node = Node::create(['type' => 'ai_session', 'uid' => $GLOBALS['uid'], 'title' => 'Race message',
    'session_role' => 'user', 'storage_version' => 3, 'edit_version' => 1, 'storage_mode' => 'inline',
    'content' => ['value' => 'Ordinary message', 'format' => 'plain_text']]);
  $node->save();
  return $node;
}
function conversation(array $messages): Node {
  $node = Node::create(['type' => 'conversation', 'uid' => $GLOBALS['uid'], 'title' => 'Race conversation',
    'model' => 'fixture', 'platform' => 'fixture', 'chat_type' => 'common',
    'sessions' => array_map(static fn($item) => ['target_id' => $item->id()], $messages)]);
  $node->save();
  return $node;
}
function exists(Node $node): bool {
  $GLOBALS['storage']->resetCache([$node->id()]);
  return Node::load($node->id()) !== NULL;
}
echo 'Database: ' . \Drupal::database()->query('SELECT VERSION()')->fetchField() . PHP_EOL;
echo 'Isolation: ' . (getenv('CHAT_TEST_REPEATABLE_READ') === '1'
  ? 'REPEATABLE READ; memory entity cache' : 'READ COMMITTED; database entity cache') . PHP_EOL;
$initial = message();
$chat = conversation([$initial]);
$results = race($uid, (int) $chat->id(), [
  ['action' => 'delete', 'uuid' => $chat->uuid()], ['action' => 'delete', 'uuid' => $chat->uuid()],
]);
check($results === [['ok' => TRUE], ['ok' => TRUE]] && !exists($chat) && !exists($initial),
  'Two contending deletes both acknowledge one complete deletion');

foreach ([FALSE, TRUE] as $reverse) {
  $initial = message();
  $incoming = message();
  $chat = conversation([$initial]);
  $jobs = [['action' => 'delete', 'uuid' => $chat->uuid()],
    ['action' => 'append', 'uuid' => $chat->uuid(), 'message' => $incoming->uuid()]];
  if ($reverse) $jobs = array_reverse($jobs);
  $results = race($uid, (int) $chat->id(), $jobs);
  if ($reverse) $results = array_reverse($results);
  check($results[0] === ['ok' => TRUE] && !exists($chat) && !exists($initial),
    'Deletion serializes with an append and removes the original message');
  check(isset($results[1]['ok']) ? !exists($incoming) :
    ($results[1]['error'] === \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class && exists($incoming)),
    'Append either commits before deletion or fails without deleting an unlinked message');
}

foreach ([FALSE, TRUE] as $reverse) {
  $target = message();
  $a = conversation([$target]);
  $b = conversation([]);
  $jobs = [['action' => 'delete', 'uuid' => $a->uuid()],
    ['action' => 'link', 'nid' => $b->id(), 'messageId' => $target->id()]];
  if ($reverse) $jobs = array_reverse($jobs);
  $results = race($uid, (int) $target->id(), $jobs);
  if ($reverse) $results = array_reverse($results);
  $storage->resetCache();
  $linked = Node::load($b->id())->get('sessions')->getValue();
  check($results[0] === ['ok' => TRUE] && !exists($a), 'Aggregate deletion finishes during a cross-conversation link');
  check(isset($results[1]['ok']) ? (exists($target) && count($linked) === 1) :
    (($results[1]['status'] ?? NULL) === 409 && $linked === []),
    'A direct entity save either shares a live message or rolls back with a retryable conflict');
}

$target = message();
$a = conversation([$target]);
$b = conversation([]);
$results = race($uid, (int) $target->id(), [['action' => 'delete', 'uuid' => $a->uuid()]],
  static function () use ($b, $target): void {
    $b->set('sessions', [['target_id' => $target->id()]]);
    $b->save();
  }, static function () use ($b): void {
    // The fixture writer must take its own conversation lock before holding the message lock.
    \Drupal::database()->select('node', 'n')->fields('n', ['nid'])
      ->condition('nid', $b->id())->forUpdate()->execute()->fetchField();
    $original = \Drupal::entityTypeManager()->getStorage('node')->loadUnchanged($b->id());
    $b->setOriginal($original);
  });
check($results === [['ok' => TRUE]] && !exists($a) && exists($b) && exists($target),
  'A reference committed after deletion starts is still preserved by the locking read');
echo 'Chat MySQL concurrency checks passed: ' . $checks . PHP_EOL;
