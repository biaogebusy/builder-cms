<?php

namespace Drupal\advancedqueue\Plugin\AdvancedQueue\Backend;

use Drupal\advancedqueue\Attribute\AdvancedQueueBackend;
use Drupal\advancedqueue\Entity\Queue;
use Drupal\advancedqueue\Entity\QueueInterface;
use Drupal\advancedqueue\Job;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the database queue backend.
 */
#[AdvancedQueueBackend(
  id: "database",
  label: new TranslatableMarkup("Database"),
)]
class Database extends BackendBase implements SupportsDeletingJobsInterface, SupportsListingJobsInterface, SupportsReleasingJobsInterface, SupportsLoadingJobsInterface, SupportsDetectingDuplicateJobsInterface {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The database table.
   *
   * @var string
   */
  protected const TABLE = 'advancedqueue';

  /**
   * Constructs a new Database object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection to use.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, TimeInterface $time, Connection $connection) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $time);

    $this->connection = $connection;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('datetime.time'),
      $container->get('database')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function createQueue() {
    // No need to do anything, all database queues share the same table.
  }

  /**
   * {@inheritdoc}
   */
  public function deleteQueue() {
    // Delete all jobs in the current queue.
    $this->connection->delete(static::TABLE)
      ->condition('queue_id', $this->queueId)
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function cleanupQueue() {
    // Find expired jobs for this queue only, then update them by job_id.
    // A single UPDATE with a range condition on the expires column takes
    // gap locks across the matching index range, which can deadlock with
    // the single-row, primary-key updates performed by claimJob() and
    // updateJob(). Updating by job_id in ascending order instead uses the
    // same primary-key locking as those methods, and gives every writer a
    // consistent lock order.
    $job_ids = $this->connection->select(static::TABLE, 'a')
      ->fields('a', ['job_id'])
      ->condition('queue_id', $this->queueId)
      ->condition('expires', 0, '<>')
      ->condition('expires', $this->time->getCurrentTime(), '<')
      ->orderBy('job_id', 'ASC')
      ->execute()
      ->fetchCol();

    if ($job_ids) {
      $this->executeWithDeadlockRetry(function () use ($job_ids) {
        return $this->connection->update(static::TABLE)
          ->fields([
            'state' => Job::STATE_QUEUED,
            'expires' => 0,
          ])
          ->condition('job_id', $job_ids, 'IN')
          ->execute();
      });
    }

    // Cleanup old queue items.
    $this->cleanupQueueItems();
  }

  /**
   * Cleanup old queue items.
   */
  protected function cleanupQueueItems() {
    $queue = Queue::load($this->queueId);
    $threshold = $queue->getThreshold();

    if (empty($threshold['type']) || empty($threshold['limit'])) {
      return;
    }

    // We always clean successfully.
    // But we could as well failures.
    $states = $threshold['state'] === 'all' ? [
      Job::STATE_SUCCESS,
      Job::STATE_FAILURE,
    ] : [JOB::STATE_SUCCESS];

    // Get limits.
    $limit = $threshold['limit'];

    // Specifics for each type of cleanups. For date based, calculate
    // timestamp. For deletion based on count, get proper timestamp by querying.
    if ($threshold['type'] == QueueInterface::QUEUE_THRESHOLD_DAYS) {
      $limit = $threshold['limit'] * 60 * 60 * 24;
      $delete_before = $this->time->getCurrentTime() - $limit;

    }
    else {
      $delete_before = $this->connection
        ->select(static::TABLE, 'a')
        ->fields('a', ['processed'])
        ->condition('state', $states, 'IN')
        ->condition('queue_id', $this->queueId)
        ->orderBy('processed', 'DESC')
        ->range($limit - 1, 1)
        ->execute()
        ->fetchField();
    }

    if ($delete_before) {
      $this->connection->delete(static::TABLE)
        ->condition('queue_id', $this->queueId)
        ->condition('processed', $delete_before, '<')
        ->condition('state', $states, 'IN')
        ->execute();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function countJobs() {
    // Ensure each state gets a count, even if it's 0.
    $jobs = [
      Job::STATE_QUEUED => 0,
      Job::STATE_PROCESSING => 0,
      Job::STATE_SUCCESS => 0,
      Job::STATE_FAILURE => 0,
    ];
    $query = "SELECT state, COUNT(job_id) FROM {" . static::TABLE . "} WHERE queue_id = :queue_id GROUP BY state";
    $counts = $this->connection->query($query, [':queue_id' => $this->queueId])->fetchAllKeyed();
    foreach ($counts as $state => $count) {
      $jobs[$state] = $count;
    }

    return $jobs;
  }

  /**
   * {@inheritdoc}
   */
  public function enqueueJob(Job $job, $delay = 0) {
    $this->enqueueJobs([$job], $delay);
  }

  /**
   * {@inheritdoc}
   */
  public function enqueueJobs(array $jobs, $delay = 0) {
    if (count($jobs) > 1) {
      // Make the inserts atomic, and improve performance on certain engines.
      $transaction = $this->connection->startTransaction();
    }

    /** @var \Drupal\advancedqueue\Job $job */
    foreach ($jobs as $job) {
      $job->setQueueId($this->queueId);
      $job->setState(Job::STATE_QUEUED);
      if (!$job->getAvailableTime()) {
        $job->setAvailableTime($this->time->getCurrentTime() + $delay);
      }

      $fields = $job->toArray();
      unset($fields['id']);
      $fields['payload'] = json_encode($fields['payload']);
      // InsertQuery supports inserting multiple rows at once, which is faster,
      // but that doesn't give us the inserted job IDs.
      $query = $this->connection->insert(static::TABLE)->fields($fields);
      $job_id = $query->execute();
      $job->setId($job_id);
    }

    if (isset($transaction)) {
      // Commit the transaction.
      $transaction = NULL;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getDuplicateJobs(Job $job): array {
    $fingerprint = $job->getFingerprint();
    if (empty($fingerprint)) {
      throw new \InvalidArgumentException('Job must have its fingerprint set.');
    }

    $query = $this->connection->select(static::TABLE, 'aq')
      ->fields('aq')
      ->condition('queue_id', $this->queueId)
      ->condition('fingerprint', $fingerprint)
      ->condition('state', [Job::STATE_QUEUED, Job::STATE_PROCESSING], 'IN');

    if (!empty($job->getId())) {
      $query->condition('job_id', $job->getId(), '<>');
    }
    $result = $query->execute();
    $job_definitions = $result->fetchAllAssoc('job_id', \PDO::FETCH_ASSOC);

    $jobs = [];
    foreach ($job_definitions as $job_id => $job_definition) {
      $jobs[$job_id] = $this->constructJobFromDefinition($job_definition);
    }
    return $jobs;
  }

  /**
   * {@inheritdoc}
   */
  public function retryJob(Job $job, $delay = 0) {
    if ($job->getState() != Job::STATE_FAILURE) {
      throw new \InvalidArgumentException('Only failed jobs can be retried.');
    }

    $job->setNumRetries($job->getNumRetries() + 1);
    $job->setAvailableTime($this->time->getCurrentTime() + $delay);
    $job->setState(Job::STATE_QUEUED);
    $this->updateJob($job);
  }

  /**
   * {@inheritdoc}
   */
  public function claimJob() {
    // Claim a job by updating its expire fields. If the claim is not successful
    // another thread may have claimed the job in the meantime. Therefore loop
    // until a job is successfully claimed or we are reasonably sure there
    // are no unclaimed jobs left.
    while (TRUE) {
      $query = "SELECT * FROM {" . static::TABLE . "}
        WHERE queue_id = :queue_id AND state = :state AND available <= :now AND expires = 0
        ORDER BY available, job_id ASC";
      $params = [
        ':queue_id' => $this->queueId,
        ':state' => Job::STATE_QUEUED,
        ':now' => $this->time->getCurrentTime(),
      ];
      $job_definition = $this->connection->queryRange($query, 0, 1, $params)->fetchAssoc();
      if (!$job_definition) {
        // No jobs left to claim.
        return NULL;
      }

      // Try to update the item. Only one thread can succeed in updating the
      // same row. We cannot rely on the request time because items might be
      // claimed by a single consumer which runs longer than 1 second. If we
      // continue to use request time instead of current time, we steal
      // time from the lease, and will tend to reset items before the lease
      // should really expire.
      $state = Job::STATE_PROCESSING;
      $expires = $this->time->getCurrentTime() + $this->configuration['lease_time'];
      $update = $this->connection->update(static::TABLE)
        ->fields([
          'state' => $state,
          'expires' => $expires,
        ])
        ->condition('job_id', $job_definition['job_id'])
        ->condition('expires', 0);
      // If there are affected rows, the claim succeeded.
      if ($this->executeWithDeadlockRetry(fn () => $update->execute())) {
        $job_definition['state'] = $state;
        $job_definition['expires'] = $expires;
        return $this->constructJobFromDefinition($job_definition);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onSuccess(Job $job) {
    $job->setProcessedTime($this->time->getCurrentTime());
    $this->updateJob($job);
  }

  /**
   * {@inheritdoc}
   */
  public function onFailure(Job $job) {
    $job->setProcessedTime($this->time->getCurrentTime());
    $this->updateJob($job);
  }

  /**
   * {@inheritdoc}
   */
  public function releaseJob($job_id) {
    $this->connection->update(static::TABLE)
      ->fields([
        'state' => Job::STATE_QUEUED,
        'expires' => 0,
      ])
      ->condition('job_id', $job_id)
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function deleteJob($job_id) {
    $this->connection->delete(static::TABLE)
      ->condition('job_id', $job_id)
      ->execute();
  }

  /**
   * Updates the given job.
   *
   * @param \Drupal\advancedqueue\Job $job
   *   The job.
   */
  protected function updateJob(Job $job) {
    $this->connection->update(static::TABLE)
      ->fields([
        'payload' => json_encode($job->getPayload()),
        'state' => $job->getState(),
        'message' => $job->getMessage(),
        'num_retries' => $job->getNumRetries(),
        'available' => $job->getAvailableTime(),
        'processed' => $job->getProcessedTime(),
        'expires' => $job->getExpiresTime(),
      ])
      ->condition('job_id', $job->getId())
      ->execute();
  }

  /**
   * Executes a database write, retrying it if it hits lock contention.
   *
   * Concurrent writes to the queue table (job claims and queue cleanup)
   * can be picked by the database as a deadlock victim, or (on SQLite,
   * which has no row-level locking) simply find the database locked by
   * another write. Either is expected under concurrency, and the affected
   * write is safe to retry.
   *
   * @param callable $callback
   *   A callback that performs the write and returns its result.
   * @param int $max_attempts
   *   The maximum number of attempts before giving up.
   *
   * @return mixed
   *   The return value of the callback.
   */
  protected function executeWithDeadlockRetry(callable $callback, int $max_attempts = 3) {
    $attempt = 0;
    while (TRUE) {
      try {
        return $callback();
      }
      catch (DatabaseExceptionWrapper $e) {
        $attempt++;
        $previous = $e->getPrevious();
        $is_retryable = $previous instanceof \PDOException && $this->isRetryableLockException($previous);
        // On PostgreSQL, a failed statement leaves the whole transaction in
        // an aborted state: every subsequent statement is refused with
        // SQLSTATE 25P02 until an explicit ROLLBACK, even one that has
        // nothing to do with the original failure. This callback normally
        // runs outside of any transaction, in which case there is nothing
        // to roll back; only do it if a transaction (started by this
        // backend or, unusually, by a caller) is actually open. Do this
        // whether or not we are about to retry, so the connection is left
        // usable either way.
        if ($this->connection->inTransaction()) {
          // Roll back via the client connection directly (PDO::rollBack()),
          // not a raw ROLLBACK query: the latter resets the transaction at
          // the database level, but leaves PDO's own inTransaction() flag
          // (and so, later, Drupal's transaction manager) believing a
          // transaction is still open, causing a hard failure the next
          // time anything tries to commit or roll back.
          $this->connection->getClientConnection()->rollBack();
          // Drupal's own transaction bookkeeping still thinks it owns an
          // open transaction (or stack of them), even though the rollback
          // above already ended it at the database level. Void it, so a
          // Transaction object a caller is still holding safely no-ops on
          // commit/release instead of trying to act on a transaction that
          // no longer exists. On Drupal 10, transactionManager() can
          // return FALSE for a driver that doesn't implement one (core's
          // mysql/pgsql/sqlite drivers all do); on Drupal 11 it is always
          // available.
          if ($transaction_manager = $this->connection->transactionManager()) {
            $transaction_manager->voidClientTransaction();
          }
        }
        if (!$is_retryable || $attempt >= $max_attempts) {
          throw $e;
        }
        usleep(random_int(50000, 150000));
      }
    }
  }

  /**
   * Determines whether a database exception is retryable lock contention.
   *
   * The SQLSTATE (and, for SQLite, the driver-specific error code) that
   * signals this differs per database engine:
   * - MySQL: SQLSTATE 40001 is a detected deadlock.
   * - PostgreSQL: SQLSTATE 40P01 is a detected deadlock; 40001 is a
   *   serialization failure, which is likewise safe to retry.
   * - SQLite has no row-level locking, so it can't deadlock. Concurrent
   *   writers instead fail immediately with "database is locked" once
   *   the connection's own busy timeout is exhausted. PDO reports this as
   *   the generic SQLSTATE HY000, with the SQLite-specific SQLITE_BUSY (5)
   *   or SQLITE_LOCKED (6) code in errorInfo[1].
   *
   * @param \PDOException $exception
   *   The exception to check.
   *
   * @return bool
   *   TRUE if the write is safe to retry.
   */
  protected function isRetryableLockException(\PDOException $exception): bool {
    return match ($this->connection->driver()) {
      'mysql' => $exception->getCode() === '40001',
      'pgsql' => in_array($exception->getCode(), ['40001', '40P01'], TRUE),
      // Reachable from ordinary single-writer contention (e.g. cron and a
      // request both writing to the queue table at once), not only from
      // the two-transaction lock cycles the deadlock tests construct -
      // SQLite can't produce those (see ConcurrentCleanupDeadlockTest),
      // but it produces this just from one write already holding the
      // whole-database lock while another tries to write.
      'sqlite' => $exception->getCode() === 'HY000' && in_array($exception->errorInfo[1] ?? NULL, [5, 6], TRUE),
      default => FALSE,
    };
  }

  /**
   * {@inheritdoc}
   */
  public function loadJob($job_id) {
    $query = "SELECT * FROM {" . static::TABLE . "} WHERE queue_id = :queue_id AND job_id = :job_id";
    $params = [
      ':queue_id' => $this->queueId,
      ':job_id' => $job_id,
    ];
    $job_definition = $this->connection->query($query, $params)->fetchAssoc();
    if (!$job_definition) {
      throw new \InvalidArgumentException(sprintf("Job with id %s not found.", $job_id));
    }
    return $this->constructJobFromDefinition($job_definition);
  }

  /**
   * Constructs a job object from a stored job definition array.
   *
   * @param array $definition
   *   The job definition array retrieved from the database.
   *
   * @return \Drupal\advancedqueue\Job
   *   A new object representing the job.
   */
  protected function constructJobFromDefinition(array $definition) {
    $definition['id'] = $definition['job_id'];
    unset($definition['job_id']);
    $definition['payload'] = json_decode($definition['payload'], TRUE);
    return new Job($definition);
  }

}
