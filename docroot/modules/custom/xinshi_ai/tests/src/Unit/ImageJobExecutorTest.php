<?php

declare(strict_types=1);

namespace Drupal\Tests\xinshi_ai\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
use Drupal\node\NodeInterface;
use Drupal\xinshi_ai\AiTaskInterface;
use Drupal\xinshi_ai\AiTaskManagerInterface;
use Drupal\xinshi_ai\Exception\TaskExecutionException;
use Drupal\xinshi_ai\Service\EventStreamServiceInterface;
use Drupal\xinshi_ai\Service\ImageJobExecutor;
use Drupal\xinshi_ai\Service\ImageJobRun;
use Drupal\xinshi_ai\Service\ImageJobRuns;
use Drupal\xinshi_ai\Service\ImageUsageRecorder;
use Drupal\xinshi_ai\Service\JobLifecycleServiceInterface;
use Drupal\xinshi_ai\Service\ProviderErrorMapper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Queue decisions of image jobs: dedup, lease, recovery, retry and cancel (UB2.5).
 *
 * The lease table is real (memory SQLite); the lifecycle service is a double
 * whose stored status is scripted per test, standing in for the entity
 * storage that a concurrent cancel writes to.
 */
final class ImageJobExecutorTest extends TestCase {

  use ImageJobNodeTrait;

  private const JOB = 'job-1';

  private Connection $database;
  private ImageJobRuns $runs;
  private AiTaskInterface&MockObject $task;
  private JobLifecycleServiceInterface&MockObject $lifecycle;
  private QueueInterface&MockObject $queue;
  private EventStreamServiceInterface&MockObject $events;
  private ImageUsageRecorder&MockObject $usage;
  private LoggerInterface&MockObject $logger;
  private ImageJobExecutor $executor;
  private int $now = 1_700_000_000_000;
  /** What the lifecycle service reports as the stored status of any job. */
  private ?string $storedStatus = 'queued';
  /** Per-job stored status, taking precedence over $storedStatus. */
  private array $statusOf = [];
  /** @var list<array> */
  private array $queued = [];
  /** @var list<array{0:string,1:array}> */
  private array $published = [];

  protected function setUp(): void {
    parent::setUp();
    Database::addConnectionInfo('image_job_executor_test', 'default', [
      'driver' => 'sqlite', 'database' => ':memory:',
      'namespace' => 'Drupal\\sqlite\\Driver\\Database\\sqlite',
    ]);
    $this->database = Database::getConnection('default', 'image_job_executor_test');
    xinshi_ai_apply_schema_updates($this->database);
    $time = $this->createMock(TimeInterface::class);
    $time->method('getCurrentMicroTime')->willReturnCallback(fn(): float => $this->now / 1000);
    $uuid = $this->createMock(UuidInterface::class);
    $sequence = 0;
    $uuid->method('generate')->willReturnCallback(function () use (&$sequence): string {
      return sprintf('run-%d', ++$sequence);
    });
    $this->runs = new ImageJobRuns($this->database, $time, $uuid);

    $this->task = $this->createMock(AiTaskInterface::class);
    $manager = $this->createMock(AiTaskManagerInterface::class);
    $manager->method('getByKind')->with('text_to_image')->willReturn($this->task);
    $this->lifecycle = $this->createMock(JobLifecycleServiceInterface::class);
    $this->lifecycle->method('refreshStatus')->willReturnCallback(function (NodeInterface $job): ?string {
      $status = array_key_exists($job->uuid(), $this->statusOf) ? $this->statusOf[$job->uuid()] : $this->storedStatus;
      if ($status !== NULL) {
        $job->set('field_status', $status);
      }
      return $status;
    });
    $this->queue = $this->createMock(QueueInterface::class);
    $this->queue->method('createItem')->willReturnCallback(function (array $data): int {
      $this->queued[] = $data;
      return count($this->queued);
    });
    $queues = $this->createMock(QueueFactory::class);
    $queues->method('get')->with(ImageJobExecutor::QUEUE)->willReturn($this->queue);
    $this->events = $this->createMock(EventStreamServiceInterface::class);
    $this->events->method('publish')->willReturnCallback(function (string $uuid, string $event, array $data): void {
      $this->published[] = [$event, $data];
    });
    $this->usage = $this->createMock(ImageUsageRecorder::class);
    $this->logger = $this->createMock(LoggerInterface::class);
    $this->executor = new ImageJobExecutor($manager, $this->lifecycle, new ProviderErrorMapper(), $queues,
      $this->events, $this->runs, $this->usage, $this->logger);
  }

  protected function tearDown(): void {
    Database::removeConnection('image_job_executor_test');
    parent::tearDown();
  }

  public function testAQueuedJobRunsUnderALeaseThatIsReleasedAfterwards(): void {
    $job = $this->jobNode(self::JOB);
    $this->lifecycle->method('loadJobByUuid')->with(self::JOB)->willReturn($job);
    $this->task->expects($this->once())->method('execute')
      ->with($job, $this->callback(function (ImageJobRun $run): bool {
        $this->assertSame(self::JOB, $run->jobUuid);
        $this->assertNull($run->previous());
        $this->assertSame(ImageJobRuns::STATE_RUNNING, $this->runs->row(self::JOB)['state']);
        return TRUE;
      }));

    $this->executor->run(['jobUuid' => self::JOB]);

    $this->assertSame(ImageJobRuns::STATE_FINISHED, $this->runs->row(self::JOB)['state']);
    $this->assertSame([], $this->queued);
  }

  public function testARedeliveredItemOfATerminalJobNeverCallsTheModel(): void {
    $job = $this->jobNode(self::JOB, ['field_status' => 'succeeded']);
    $this->storedStatus = 'succeeded';
    $this->lifecycle->method('loadJobByUuid')->willReturn($job);
    $this->task->expects($this->never())->method('execute');
    $this->lifecycle->expects($this->never())->method('markFailed');

    $this->executor->run(['jobUuid' => self::JOB]);

    $this->assertNull($this->runs->row(self::JOB), 'no lease is taken for a finished job');
  }

  public function testAJobCancelledWhileQueuedIsDropped(): void {
    $job = $this->jobNode(self::JOB, ['field_status' => 'cancelled']);
    $this->storedStatus = 'cancelled';
    $this->lifecycle->method('loadJobByUuid')->willReturn($job);
    $this->task->expects($this->never())->method('execute');
    $this->executor->run(['jobUuid' => self::JOB]);
    $this->assertSame([], $this->published);
  }

  public function testADeletedOrMalformedItemIsIgnored(): void {
    $this->lifecycle->method('loadJobByUuid')->willReturn(NULL);
    $this->task->expects($this->never())->method('execute');
    $this->executor->run(['jobUuid' => 'gone']);
    $this->executor->run([]);
    $this->assertNull($this->runs->row('gone'));
  }

  public function testASecondConsumerDropsTheItemWhileTheFirstWorkerHoldsTheLease(): void {
    $job = $this->jobNode(self::JOB);
    $this->lifecycle->method('loadJobByUuid')->willReturn($job);
    $this->task->expects($this->once())->method('execute')
      ->willReturnCallback(function (NodeInterface $job, ImageJobRun $run): void {
        // The provider call is under way; a second delivery of the same item.
        $run->dispatched();
        $this->executor->run(['jobUuid' => self::JOB]);
        $run->responded();
      });

    $this->executor->run(['jobUuid' => self::JOB]);

    $row = $this->runs->row(self::JOB);
    $this->assertSame('run-1', $row['run_token']);
    $this->assertSame(ImageJobRuns::STATE_FINISHED, $row['state']);
    $this->assertNotNull($row['responded_at']);
  }

  public function testARetryableFailureIsRequeuedWithTheNextAttemptNumber(): void {
    $job = $this->jobNode(self::JOB, ['field_status' => 'running']);
    $this->storedStatus = 'running';
    $this->lifecycle->method('loadJobByUuid')->willReturn($job);
    $this->task->method('execute')->willThrowException(new TaskExecutionException('503', 'provider_5xx'));
    $this->lifecycle->expects($this->never())->method('markFailed');

    $this->executor->run(['jobUuid' => self::JOB, 'attempt' => 1]);

    $this->assertSame([['jobUuid' => self::JOB, 'attempt' => 2]], $this->queued);
    $this->assertSame(ImageJobRuns::STATE_FINISHED, $this->runs->row(self::JOB)['state']);
  }

  public function testTheLastRetryAndNonRetryableErrorsEndTheJob(): void {
    $job = $this->jobNode(self::JOB, ['field_status' => 'running']);
    $this->storedStatus = 'running';
    $this->lifecycle->method('loadJobByUuid')->willReturn($job);
    $this->task->method('execute')->willThrowException(new TaskExecutionException('quota', 'quota_exhausted'));
    $this->lifecycle->expects($this->once())->method('markFailed')->with($job, 'quota', 'quota_exhausted')
      ->willReturn(TRUE);

    $this->executor->run(['jobUuid' => self::JOB, 'attempt' => 0]);

    $this->assertSame([], $this->queued);
    $this->assertSame([['failed', ['statusReason' => 'quota', 'errorCode' => 'quota_exhausted']]], $this->published);
  }

  public function testAnErrorOutsideThePipelineIsNotRetried(): void {
    $job = $this->jobNode(self::JOB, ['field_status' => 'running']);
    $this->storedStatus = 'running';
    $this->lifecycle->method('loadJobByUuid')->willReturn($job);
    $this->task->method('execute')->willThrowException(new \LogicException('boom'));
    $this->lifecycle->expects($this->once())->method('markFailed')->with($job, 'boom', 'internal')->willReturn(TRUE);
    $this->executor->run(['jobUuid' => self::JOB]);
    $this->assertSame([], $this->queued);
  }

  public function testAFailureAfterACancelNeitherRetriesNorOverwritesTheCancel(): void {
    $job = $this->jobNode(self::JOB, ['field_status' => 'running']);
    $this->storedStatus = 'running';
    $this->lifecycle->method('loadJobByUuid')->willReturn($job);
    $this->task->method('execute')->willReturnCallback(function (): void {
      // The user cancelled while the provider call was running.
      $this->storedStatus = 'cancelled';
      throw new TaskExecutionException('503', 'provider_5xx');
    });
    $this->lifecycle->expects($this->never())->method('markFailed');

    $this->executor->run(['jobUuid' => self::JOB, 'attempt' => 0]);

    $this->assertSame([], $this->queued);
    $this->assertSame([], $this->published);
  }

  public function testADeadWorkerThatNeverSentTheRequestIsRunAgainOnRedelivery(): void {
    $job = $this->jobNode(self::JOB, ['field_status' => 'running']);
    $this->storedStatus = 'running';
    $this->lifecycle->method('loadJobByUuid')->willReturn($job);
    $dead = $this->runs->claim(self::JOB, 0);
    $dead->usageAttempt('att_1');
    $this->now += ImageJobRuns::LEASE_MS + 1;
    $this->usage->expects($this->once())->method('abandonOpenAttempts')->with(self::JOB, 'not_sent')
      ->willReturn(['att_1']);
    $this->task->expects($this->once())->method('execute')
      ->with($job, $this->callback(fn(ImageJobRun $run): bool => $run->previous()['run_token'] === 'run-1'));
    $this->lifecycle->expects($this->never())->method('markFailed');

    $this->executor->run(['jobUuid' => self::JOB]);

    $this->assertSame(ImageJobRuns::STATE_FINISHED, $this->runs->row(self::JOB)['state']);
  }

  public function testADeadWorkerWithTheRequestInFlightLeavesTheJobUnknownNotRerun(): void {
    $job = $this->jobNode(self::JOB, ['field_status' => 'running']);
    $this->storedStatus = 'running';
    $this->lifecycle->method('loadJobByUuid')->willReturn($job);
    $dead = $this->runs->claim(self::JOB, 0);
    $dead->dispatched();
    $this->now += ImageJobRuns::LEASE_MS + 1;
    $this->usage->expects($this->once())->method('abandonOpenAttempts')->with(self::JOB, 'unknown')->willReturn([]);
    $this->task->expects($this->never())->method('execute');
    $this->lifecycle->expects($this->once())->method('markFailed')
      ->with($job, $this->stringContains('in flight'), ImageJobExecutor::RESULT_UNKNOWN)->willReturn(TRUE);

    $this->executor->run(['jobUuid' => self::JOB]);

    $this->assertSame([], $this->queued);
    $this->assertSame('failed', $this->published[0][0]);
    $this->assertSame(ImageJobExecutor::RESULT_UNKNOWN, $this->published[0][1]['errorCode']);
    $this->assertSame(ImageJobRuns::STATE_FINISHED, $this->runs->row(self::JOB)['state']);
  }

  public function testADeadWorkerThatGotTheResponseFinalizesWithWhatWasSaved(): void {
    $job = $this->jobNode(self::JOB, ['field_status' => 'partial', 'field_n_succeeded' => 2]);
    $this->storedStatus = 'partial';
    $this->lifecycle->method('loadJobByUuid')->willReturn($job);
    $dead = $this->runs->claim(self::JOB, 0);
    $dead->dispatched();
    $dead->responded();
    $this->now += ImageJobRuns::LEASE_MS + 1;
    $this->usage->expects($this->once())->method('abandonOpenAttempts')->with(self::JOB, 'sent')->willReturn([]);
    $this->task->expects($this->never())->method('execute');
    $this->lifecycle->expects($this->once())->method('finalizeInterrupted')
      ->with($job, $this->stringContains('2 of 4'), ImageJobExecutor::PROCESS_INTERRUPTED)
      ->willReturnCallback(function (NodeInterface $job): bool {
        $job->set('field_status', 'succeeded');
        $job->set('field_completed_at', 1_700_000_100);
        return TRUE;
      });

    $this->executor->run(['jobUuid' => self::JOB]);

    $this->assertSame([['completed', ['nSucceeded' => 2, 'completedAt' => 1_700_000_100]]], $this->published);
    $this->assertSame([], $this->queued);
  }

  public function testCronRecoversExpiredLeasesAndRequeuesOnlyUnsentJobs(): void {
    $unsent = $this->jobNode('job-unsent', ['field_status' => 'running']);
    $inFlight = $this->jobNode('job-in-flight', ['field_status' => 'running']);
    $finished = $this->jobNode('job-finished', ['field_status' => 'succeeded']);
    $this->storedStatus = NULL;
    $this->lifecycle->method('loadJobByUuid')->willReturnMap([
      ['job-unsent', $unsent], ['job-in-flight', $inFlight], ['job-finished', $finished], ['job-gone', NULL],
    ]);
    $this->statusOf = ['job-unsent' => 'running', 'job-in-flight' => 'running', 'job-finished' => 'succeeded'];
    $lifecycle = $this->lifecycle;
    $this->runs->claim('job-unsent', 1);
    $this->runs->claim('job-in-flight', 0)->dispatched();
    // Finished by its worker right before it died: the lease expired but the
    // job is terminal, nothing to do.
    $this->runs->claim('job-finished', 0)->responded();
    $this->runs->claim('job-gone', 0);
    // A live run is never touched.
    $this->now += ImageJobRuns::LEASE_MS + 1;
    $this->runs->claim('job-live', 0);
    $this->usage->method('abandonOpenAttempts')->willReturn([]);
    $lifecycle->expects($this->once())->method('markFailed')
      ->with($inFlight, $this->anything(), ImageJobExecutor::RESULT_UNKNOWN)->willReturn(TRUE);
    $this->task->expects($this->never())->method('execute');

    $this->assertSame(4, $this->executor->recoverExpired());

    $this->assertSame([['jobUuid' => 'job-unsent', 'attempt' => 1]], $this->queued);
    foreach (['job-unsent', 'job-in-flight', 'job-finished', 'job-gone'] as $uuid) {
      $this->assertSame(ImageJobRuns::STATE_FINISHED, $this->runs->row($uuid)['state'], $uuid);
    }
    $this->assertSame(ImageJobRuns::STATE_RUNNING, $this->runs->row('job-live')['state']);
    $this->assertSame(0, $this->executor->recoverExpired(), 'a second pass finds nothing');
  }

}
