<?php

declare(strict_types=1);

// cspell:ignore ipproto uncatchable
namespace Drupal\Tests\advancedqueue\Kernel;

use Drupal\advancedqueue\Entity\Queue;
use Drupal\advancedqueue\Entity\QueueInterface;
use Drupal\advancedqueue\Job;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Database;
use Drupal\KernelTests\KernelTestBase;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Tests that concurrent cleanup of expired jobs cannot deadlock.
 *
 * Before #3619249, cleanupQueue() reset expired jobs for every queue with a
 * single UPDATE ranging over the "expires" column, with no queue_id scope
 * and no fixed row order. That created two distinct ways to deadlock
 * against a concurrent onSuccess()/onFailure()/updateJob() write, which
 * also touches "expires": across different queues (worker A locks its own
 * queue's just-completed job, then its unscoped cleanupQueue() call tries
 * to lock worker B's queue's expired job, while worker B does the mirror
 * image), and within the same queue (two workers on one queue legitimately
 * both want to touch each other's expired jobs, so scoping alone doesn't
 * help there). The fix addresses both: scoping cleanupQueue() to the
 * current queue removes the cross-queue case entirely, and updating
 * matched rows by primary key in a fixed order (instead of via a single
 * range UPDATE) removes the same-queue case.
 *
 * Each test forks a second process so the two sides genuinely run at the
 * same time, and uses a socket pair as a barrier: both sides lock their own
 * row, signal, wait for the peer's signal, and only then attempt the
 * conflicting update - guaranteeing the two locks are held in opposite
 * order regardless of how fast either side runs.
 *
 * @coversDefaultClass \Drupal\advancedqueue\Plugin\AdvancedQueue\Backend\Database
 * @group advancedqueue
 */
class ConcurrentCleanupDeadlockTest extends KernelTestBase {

  use ProphecyTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'advancedqueue',
    'advancedqueue_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    if (!extension_loaded('pcntl') || !extension_loaded('posix')) {
      $this->markTestSkipped('This test requires the pcntl and posix extensions to fork a second process.');
    }
    $this->installSchema('advancedqueue', ['advancedqueue']);
  }

  /**
   * Skips the calling test on SQLite.
   *
   * SQLite serializes all writes behind a single, whole-database lock:
   * only one write transaction can be open at a time, database-wide.
   * The deadlock tests below need both processes to hold their own row
   * lock at the same time before either proceeds - on SQLite the second
   * process's transaction just blocks until the first one ends, so the
   * two sides can never both reach that point. That's not a bug this
   * module can fix; it means the multi-session lock-order deadlock those
   * tests reproduce cannot happen on SQLite in the first place.
   */
  protected function skipOnSqlite(): void {
    if ($this->container->get('database')->driver() === 'sqlite') {
      $this->markTestSkipped('SQLite has no concurrent write transactions, so this deadlock cannot occur there.');
    }
  }

  /**
   * @covers ::cleanupQueue
   */
  public function testScopedCleanupDoesNotDeadlock(): void {
    $this->skipOnSqlite();
    [$queue_a, $job_a, $job_b, $now] = $this->prepareTwoExpiredJobs();

    // Both sides run the real, current cleanupQueue() for their own queue.
    // Its queue_id condition means it can never match the other side's
    // job, so it can never attempt to lock it.
    $real_cleanup = function (string $queue_id): void {
      $backend = $this->container->get('plugin.manager.advancedqueue_backend')
        ->createInstance('database', [
          'lease_time' => 5,
          '_entity_id' => $queue_id,
        ]);
      $backend->cleanupQueue();
    };

    [$parent_result, $child_result] = $this->raceLockOrder(
      $queue_a, $job_a, 'queue_b', $job_b->getId(), $now, $real_cleanup,
    );

    $this->assertSame('OK', $parent_result, "The parent process's cleanupQueue() call failed: $parent_result");
    $this->assertSame('OK', $child_result, "The child process's cleanupQueue() call failed: $child_result");
  }

  /**
   * @covers ::cleanupQueue
   */
  public function testScopedCleanupDoesNotDeadlockInSameQueue(): void {
    $this->skipOnSqlite();
    [$queue, $job_x, $job_y, $now] = $this->prepareTwoHeldJobsInSameQueue(300);

    $real_cleanup = function (string $queue_id): void {
      $backend = $this->container->get('plugin.manager.advancedqueue_backend')
        ->createInstance('database', [
          'lease_time' => 5,
          '_entity_id' => $queue_id,
        ]);
      $backend->cleanupQueue();
    };

    [$parent_result, $child_result] = $this->raceLockOrder(
      $queue, $job_x, $queue->id(), $job_y->getId(), $now, $real_cleanup,
    );

    $this->assertSame('OK', $parent_result, "The parent process's cleanupQueue() call failed: $parent_result");
    $this->assertSame('OK', $child_result, "The child process's cleanupQueue() call failed: $child_result");
  }

  /**
   * Confirms cleanupQueue() retries and recovers from a locked SQLite database.
   *
   * SQLite can't deadlock (see skipOnSqlite()), so this doesn't need two
   * lock-holding transactions racing in opposite order like the tests
   * above. It only needs one write already holding the whole-database
   * lock while cleanupQueue() tries to write concurrently, which SQLite
   * reports as SQLITE_BUSY - confirming isRetryableLockException()'s
   * SQLite branch is reachable in practice, not just in theory.
   *
   * @covers ::cleanupQueue
   * @covers ::isRetryableLockException
   */
  public function testSqliteBusyDatabaseIsRetried(): void {
    $connection = $this->container->get('database');
    if ($connection->driver() !== 'sqlite') {
      $this->markTestSkipped('This test is specific to SQLite lock contention.');
    }

    $mock_time = $this->prophesize(TimeInterface::class);
    $mock_time->getCurrentTime()->willReturn(635814000);
    $this->container->set('datetime.time', $mock_time->reveal());

    $queue = Queue::create([
      'id' => 'queue_a',
      'label' => 'Queue A',
      'backend' => 'database',
      'backend_configuration' => ['lease_time' => 5],
    ]);
    $queue->save();
    $job = Job::create('simple', ['test' => '1']);
    $queue->getBackend()->enqueueJob($job);
    $queue->getBackend()->claimJob();

    $now = 635814000 + 6;
    $mock_time = $this->prophesize(TimeInterface::class);
    $mock_time->getCurrentTime()->willReturn($now);
    $this->container->set('datetime.time', $mock_time->reveal());
    $this->container->get('entity_type.manager')->getStorage('advancedqueue_queue')->resetCache(['queue_a']);

    $connection_options = $connection->getConnectionOptions();
    $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
    if ($sockets === FALSE) {
      $this->fail('Could not create a socket pair to synchronize the two processes.');
    }
    [$parent_socket, $child_socket] = $sockets;

    $pid = pcntl_fork();
    if ($pid === -1) {
      $this->fail('Could not fork a child process.');
    }

    if ($pid === 0) {
      // Child process: holds the write lock briefly, then releases it.
      // Never returns.
      fclose($parent_socket);
      Database::addConnectionInfo('locker', 'default', $connection_options);
      $locker_connection = Database::getConnection('default', 'locker');
      // startTransaction() on SQLite issues BEGIN IMMEDIATE TRANSACTION,
      // which acquires the write lock immediately rather than waiting for
      // a write statement.
      $transaction = $locker_connection->startTransaction();
      fwrite($child_socket, 'x');
      fflush($child_socket);
      // Hold the lock briefly: long enough that the parent's write
      // attempt, made with a near-zero busy timeout, fails immediately
      // with SQLITE_BUSY, but short enough that the lock is released well
      // within the retry loop's own backoff, so the retry succeeds.
      usleep(30000);
      // Transaction::commitOrRelease() does not exist on Drupal 10; there,
      // a transaction only commits when its Transaction object is
      // destroyed. Prefer the explicit method where it exists, since
      // committing via scope exit/unset is deprecated on Drupal 11. If the
      // minimum version of Drupal supported is 11 or greater remove the dead
      // code and check for other occurrences of commitOrRelease() in this file.
      if (method_exists($transaction, 'commitOrRelease')) {
        $transaction->commitOrRelease();
      }
      else {
        unset($transaction);
      }
      // Terminate immediately via an uncatchable signal, bypassing PHP's
      // normal shutdown sequence. That sequence would destruct (and so
      // gracefully close) the database connection this process inherited
      // from the parent, sending a QUIT packet on its socket - fork()
      // duplicates the file descriptor rather than opening a new
      // connection, so that socket is still the parent's live connection,
      // and closing it here would sever the parent mid-test.
      posix_kill(posix_getpid(), SIGKILL);
    }

    // Parent process: opens its own connection with a near-zero busy
    // timeout, so lock contention fails fast into this backend's own
    // retry loop instead of PDO silently blocking for its default (60
    // second) busy timeout first.
    fclose($child_socket);
    $result = 'OK';
    try {
      $connection_options['pdo'][\PDO::ATTR_TIMEOUT] = 0;
      Database::addConnectionInfo('short_timeout', 'default', $connection_options);
      $this->container->set('database', Database::getConnection('default', 'short_timeout'));

      fread($parent_socket, 1);
      $backend = $this->container->get('plugin.manager.advancedqueue_backend')
        ->createInstance('database', [
          'lease_time' => 5,
          '_entity_id' => 'queue_a',
        ]);
      $backend->cleanupQueue();
    }
    catch (\Exception $e) {
      $result = 'FAIL ' . $e->getMessage();
    }
    fclose($parent_socket);
    pcntl_waitpid($pid, $status);

    $this->assertSame('OK', $result, "cleanupQueue() did not recover from SQLite lock contention: $result");
    $this->assertTrue(isset($backend), 'The backend was created.');
    $this->assertNotNull($backend->claimJob(), 'The job was reset by cleanupQueue() and is claimable again.');
  }

  /**
   * Creates one queue with expired jobs, plus two more for raceLockOrder().
   *
   * The two extras (job_x, job_y) are for raceLockOrder() to lock one each.
   *
   * The background jobs make each side's cleanup update take long enough
   * (many rows, not just the one contended row) that the two processes are
   * reliably still mid-update, holding job_x/job_y, when they reach for
   * each other's row - without it, both updates finish before the other
   * side gets there, and no contention happens at all.
   *
   * @param int $background_count
   *   The number of extra expired jobs to add as background load.
   *
   * @return array{0: \Drupal\advancedqueue\Entity\QueueInterface, 1: \Drupal\advancedqueue\Job, 2: \Drupal\advancedqueue\Job, 3: int}
   *   The queue, job_x, job_y, and the "current"
   *   time at which all of these jobs are expired.
   */
  protected function prepareTwoHeldJobsInSameQueue(int $background_count): array {
    $mock_time = $this->prophesize(TimeInterface::class);
    $mock_time->getCurrentTime()->willReturn(635814000);
    $this->container->set('datetime.time', $mock_time->reveal());

    $queue = Queue::create([
      'id' => 'queue_a',
      'label' => 'Queue A',
      'backend' => 'database',
      'backend_configuration' => ['lease_time' => 5],
    ]);
    $queue->save();

    $jobs = [];
    for ($i = 0; $i < $background_count; $i++) {
      $jobs[] = Job::create('simple', ['i' => $i]);
    }
    $queue->getBackend()->enqueueJobs($jobs);
    for ($i = 0; $i < $background_count; $i++) {
      $queue->getBackend()->claimJob();
    }

    $job_x = Job::create('simple', ['test' => 'x']);
    $queue->getBackend()->enqueueJob($job_x);
    $job_x = $queue->getBackend()->claimJob();
    $job_y = Job::create('simple', ['test' => 'y']);
    $queue->getBackend()->enqueueJob($job_y);
    $job_y = $queue->getBackend()->claimJob();

    $now = 635814000 + 6;
    $mock_time = $this->prophesize(TimeInterface::class);
    $mock_time->getCurrentTime()->willReturn($now);
    $this->container->set('datetime.time', $mock_time->reveal());
    $this->container->get('entity_type.manager')->getStorage('advancedqueue_queue')->resetCache(['queue_a']);
    $queue = Queue::load('queue_a');

    return [$queue, $job_x, $job_y, $now];
  }

  /**
   * Creates two queues with one expired, claimed job each.
   *
   * @return array{0: \Drupal\advancedqueue\Entity\QueueInterface, 1: \Drupal\advancedqueue\Job, 2: \Drupal\advancedqueue\Job, 3: int}
   *   queue_a, queue_a's job, queue_b's job, and the "current" time at which
   *   both jobs are expired.
   */
  protected function prepareTwoExpiredJobs(): array {
    $mock_time = $this->prophesize(TimeInterface::class);
    $mock_time->getCurrentTime()->willReturn(635814000);
    $this->container->set('datetime.time', $mock_time->reveal());

    $queue_a = Queue::create([
      'id' => 'queue_a',
      'label' => 'Queue A',
      'backend' => 'database',
      'backend_configuration' => ['lease_time' => 5],
    ]);
    $queue_a->save();
    $queue_b = Queue::create([
      'id' => 'queue_b',
      'label' => 'Queue B',
      'backend' => 'database',
      'backend_configuration' => ['lease_time' => 5],
    ]);
    $queue_b->save();

    $job_a = Job::create('simple', ['test' => '1']);
    $queue_a->getBackend()->enqueueJob($job_a);
    $job_a = $queue_a->getBackend()->claimJob();
    $job_b = Job::create('simple', ['test' => '1']);
    $queue_b->getBackend()->enqueueJob($job_b);
    $job_b = $queue_b->getBackend()->claimJob();

    $now = 635814000 + 6;
    $mock_time = $this->prophesize(TimeInterface::class);
    $mock_time->getCurrentTime()->willReturn($now);
    $this->container->set('datetime.time', $mock_time->reveal());
    $storage = $this->container->get('entity_type.manager')->getStorage('advancedqueue_queue');
    $storage->resetCache(['queue_a', 'queue_b']);
    $queue_a = Queue::load('queue_a');

    return [$queue_a, $job_a, $job_b, $now];
  }

  /**
   * Forces two operations to attempt conflicting row locks in opposite order.
   *
   * Both processes lock their own job's row (via the real onSuccess()),
   * then rendezvous over a socket pair, then both run $update against the
   * *other* process's queue_id/job. Because each side already holds its
   * own row when it attempts the other, the two locks are always acquired
   * in opposite order, regardless of scheduling.
   *
   * @param \Drupal\advancedqueue\Entity\QueueInterface $queue_a
   *   The parent process's queue.
   * @param \Drupal\advancedqueue\Job $job_a
   *   The parent process's already-claimed, expired job.
   * @param string $queue_b_id
   *   The child process's queue ID.
   * @param string $job_b_id
   *   The child process's already-claimed, expired job ID.
   * @param int $now
   *   The "current" time at which both jobs are expired.
   * @param \Closure $update
   *   Callback run by both processes once the rendezvous completes, with
   *   the process's own queue_id ('queue_a' in the parent, $queue_b_id in
   *   the child): fn(Connection $connection, int $now, string $queue_id):
   *   void.
   *
   * @return string[]
   *   [$parent_result, $child_result]. Each is 'OK' on success, or a
   *   'FAIL ...'/exception message string on failure.
   */
  protected function raceLockOrder(QueueInterface $queue_a, Job $job_a, string $queue_b_id, string $job_b_id, int $now, \Closure $update): array {
    $connection_options = $this->container->get('database')->getConnectionOptions();
    $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
    if ($sockets === FALSE) {
      $this->fail('Could not create a socket pair to synchronize the two processes.');
    }
    [$parent_socket, $child_socket] = $sockets;

    $pid = pcntl_fork();
    if ($pid === -1) {
      $this->fail('Could not fork a child process.');
    }

    if ($pid === 0) {
      // Child process. Never returns.
      fclose($parent_socket);
      $result = 'OK';
      try {
        // Opens a fresh, independent database connection for this process.
        // The connection inherited from the parent must never be used
        // here: both processes writing to the same underlying socket
        // would corrupt the connection for both of them.
        Database::addConnectionInfo('child', 'default', $connection_options);
        $connection = Database::getConnection('default', 'child');
        $this->container->set('database', $connection);

        $backend = $this->container->get('plugin.manager.advancedqueue_backend')
          ->createInstance('database', [
            'lease_time' => 5,
            '_entity_id' => $queue_b_id,
          ]);
        $job_b = $backend->loadJob($job_b_id);

        $transaction = $connection->startTransaction();
        $job_b->setState(Job::STATE_SUCCESS);
        $backend->onSuccess($job_b);
        fwrite($child_socket, 'x');
        fread($child_socket, 1);
        $update($queue_b_id);
        if (method_exists($transaction, 'commitOrRelease')) {
          $transaction->commitOrRelease();
        }
        else {
          unset($transaction);
        }
      }
      catch (\Exception $e) {
        $result = 'FAIL ' . $e->getMessage();
      }
      fwrite($child_socket, $result . "\n");
      fflush($child_socket);
      // Terminate immediately via an uncatchable signal, bypassing PHP's
      // normal shutdown sequence. That sequence would destruct (and so
      // gracefully close) the database connection this process inherited
      // from the parent, sending a QUIT packet on its socket - fork()
      // duplicates the file descriptor rather than opening a new
      // connection, so that socket is still the parent's live connection,
      // and closing it here would sever the parent mid-test.
      posix_kill(posix_getpid(), SIGKILL);
    }

    // Parent process.
    fclose($child_socket);
    $parent_result = 'OK';
    try {
      $connection = $this->container->get('database');
      $backend = $queue_a->getBackend();

      $transaction = $connection->startTransaction();
      $job_a->setState(Job::STATE_SUCCESS);
      $backend->onSuccess($job_a);
      fwrite($parent_socket, 'x');
      fread($parent_socket, 1);
      $update($queue_a->id());
      if (method_exists($transaction, 'commitOrRelease')) {
        $transaction->commitOrRelease();
      }
      else {
        unset($transaction);
      }
    }
    catch (\Exception $e) {
      $parent_result = 'FAIL ' . $e->getMessage();
    }

    $child_result = '';
    while (($chunk = fread($parent_socket, 1024)) !== '' && $chunk !== FALSE) {
      $child_result .= $chunk;
    }
    fclose($parent_socket);
    pcntl_waitpid($pid, $status);

    return [$parent_result, trim($child_result)];
  }

}
