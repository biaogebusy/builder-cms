<?php

/**
 * @file
 * Independent PHP processes contend on the same InnoDB import receipt.
 */

function startImport(bool $pause): array {
  $suffix = $pause ? 'first' : 'second';
  $stdout = '/tmp/chat-test/' . $suffix . '.json';
  $stderr = '/tmp/chat-test/' . $suffix . '.err';
  $process = proc_open([PHP_BINARY, __DIR__ . '/integration.php', 'worker',
    (string) \Drupal::currentUser()->id()], [
      0 => ['file', '/dev/null', 'r'], 1 => ['file', $stdout, 'w'], 2 => ['file', $stderr, 'w'],
    ], $pipes, NULL, array_merge(getenv(), ['FIGMA_TEST_PAUSE' => $pause ? '1' : '0']));
  if (!is_resource($process)) { throw new RuntimeException('Cannot start isolated worker'); }
  return [$process, $stdout, $stderr];
}
function waitFor(callable $ready, string $message, int $interval = 20000): void {
  $deadline = microtime(TRUE) + 10;
  do {
    if ($ready()) { check(TRUE, $message); return; }
    usleep($interval);
  } while (microtime(TRUE) < $deadline);
  throw new RuntimeException('Timed out: ' . $message);
}
function finishImport(array $worker): array {
  $code = proc_close($worker[0]);
  $body = file_get_contents($worker[1]);
  if ($code !== 0 || !is_array($result = json_decode($body, TRUE))) {
    throw new RuntimeException('Worker failed: ' . $body . file_get_contents($worker[2]));
  }
  return $result;
}
file_put_contents('/tmp/chat-test/concurrent.png', png(0xABCDEF));
$before = counts();
$firstWorker = startImport(TRUE);
$failure = NULL;
$queries = [];
$secondWorker = NULL;
$transactions = [];
$processes = [];
$mariaDb = str_contains((string) \Drupal::database()->query('SELECT VERSION()')->fetchField(), 'MariaDB');
try {
  waitFor(static fn() => file_exists('/tmp/chat-test/import-started'), 'The first import pauses after media insertion');
  $secondWorker = startImport(FALSE);
  // InnoDB's information_schema lock snapshot needs more than 100 ms between reads to refresh.
  waitFor(static function () use (&$queries, $mariaDb): bool {
    // MySQL transaction summaries can omit an in-flight insert. Read the actual lock wait graph.
    $queries = \Drupal::database()->query($mariaDb
      ? 'SELECT l.lock_table FROM information_schema.INNODB_LOCK_WAITS w JOIN information_schema.INNODB_LOCKS l ON l.lock_id = w.requested_lock_id'
      : 'SELECT l.OBJECT_NAME FROM performance_schema.data_lock_waits w JOIN performance_schema.data_locks l ON l.ENGINE_LOCK_ID = w.REQUESTING_ENGINE_LOCK_ID')->fetchCol();
    return (bool) array_filter($queries, static fn($table) => str_contains((string) $table, 'key_value'));
  }, 'The second import waits on the receipt row in InnoDB', 200000);
}
catch (Throwable $error) {
  $failure = $error;
  $transactions = \Drupal::database()->query('SELECT trx_mysql_thread_id, trx_state, trx_query FROM information_schema.INNODB_TRX')->fetchAll();
  $processes = \Drupal::database()->query('SHOW PROCESSLIST')->fetchAll();
}
finally {
  file_put_contents('/tmp/chat-test/import-release', 'continue');
}
$firstResult = finishImport($firstWorker);
$secondResult = $secondWorker ? finishImport($secondWorker) : NULL;
if ($failure) {
  throw new RuntimeException($failure->getMessage() . '; waiting queries: ' . json_encode($queries) .
    '; transactions: ' . json_encode($transactions) . '; processes: ' . json_encode($processes) .
    '; workers: ' . json_encode(array_map(static fn($name) => @file_get_contents('/tmp/chat-test/worker-' . $name), ['first', 'second'])) .
    '; results: ' . json_encode([$firstResult, $secondResult]));
}
check($firstResult['status'] === 200, 'The first concurrent import commits');
if ($secondResult['status'] !== 200 && getenv('CHAT_TEST_REPEATABLE_READ') === '1') {
  check($secondResult['status'] === 503, 'A stale repeatable-read insert fails without a second import');
  $secondResult = finishImport(startImport(FALSE));
}
check($secondResult === $firstResult, 'The concurrent request or its explicit retry receives the same confirmed receipt');
check(counts() === [$before[0] + 1, $before[1] + 1], 'Concurrent retries create exactly one media and one managed file');
