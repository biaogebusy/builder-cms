<?php

declare(strict_types=1);

namespace Drupal\Tests\xinshi_ai\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\xinshi_ai\Service\ImageJobRuns;
use PHPUnit\Framework\TestCase;

/**
 * One worker at a time per image job; a dead worker's lease keeps its evidence (UB2.5).
 */
final class ImageJobRunsTest extends TestCase {

  private Connection $database;
  private ImageJobRuns $runs;
  private int $now = 1_700_000_000_000;
  private int $sequence = 0;

  protected function setUp(): void {
    parent::setUp();
    Database::addConnectionInfo('image_job_runs_test', 'default', [
      'driver' => 'sqlite', 'database' => ':memory:',
      'namespace' => 'Drupal\\sqlite\\Driver\\Database\\sqlite',
    ]);
    $this->database = Database::getConnection('default', 'image_job_runs_test');
    xinshi_ai_apply_schema_updates($this->database);
    $time = $this->createMock(TimeInterface::class);
    $time->method('getCurrentMicroTime')->willReturnCallback(fn(): float => $this->now / 1000);
    $uuid = $this->createMock(UuidInterface::class);
    $uuid->method('generate')->willReturnCallback(fn(): string => sprintf('run-%d', ++$this->sequence));
    $this->runs = new ImageJobRuns($this->database, $time, $uuid);
  }

  protected function tearDown(): void {
    Database::removeConnection('image_job_runs_test');
    parent::tearDown();
  }

  public function testTheSchemaUpdateIsIdempotent(): void {
    xinshi_ai_apply_schema_updates($this->database);
    $this->assertTrue($this->database->schema()->tableExists(ImageJobRuns::TABLE));
  }

  public function testOnlyOneWorkerHoldsALiveLease(): void {
    $first = $this->runs->claim('job-1', 0);
    $this->assertNotNull($first);
    $this->assertSame('run-1', $first->token);
    $this->assertNull($first->previous());

    // A second consumer delivering the same item while the first still runs.
    $this->now += 60_000;
    $this->assertNull($this->runs->claim('job-1', 0));
    // Another job is independent.
    $this->assertNotNull($this->runs->claim('job-2', 0));

    $row = $this->runs->row('job-1');
    $this->assertSame(ImageJobRuns::STATE_RUNNING, $row['state']);
    $this->assertSame(1_700_000_000_000 + ImageJobRuns::LEASE_MS, (int) $row['lease_until']);
    $this->assertNull($row['dispatched_at']);
    $this->assertNull($row['responded_at']);
  }

  public function testAFinishedRunIsClaimableAgainForARetryWithoutEvidence(): void {
    $first = $this->runs->claim('job-1', 0);
    $first->dispatched();
    $first->responded();
    $first->release();
    $row = $this->runs->row('job-1');
    $this->assertSame(ImageJobRuns::STATE_FINISHED, $row['state']);
    $this->assertSame($this->now, (int) $row['finished_at']);

    $retry = $this->runs->claim('job-1', 1);
    $this->assertNotNull($retry);
    $this->assertSame('run-2', $retry->token);
    // A handled failure was closed by its worker; nothing to recover.
    $this->assertNull($retry->previous());
    $row = $this->runs->row('job-1');
    $this->assertSame(1, (int) $row['queue_attempt']);
    $this->assertNull($row['dispatched_at']);
    $this->assertNull($row['responded_at']);
    $this->assertNull($row['finished_at']);
  }

  public function testAnExpiredLeaseIsTakenOverWithTheDeadWorkersEvidence(): void {
    $dead = $this->runs->claim('job-1', 0);
    $dead->usageAttempt('att_1');
    $dead->dispatched();
    $dispatchedAt = $this->now;
    // The worker died here: no response, no release.
    $this->now += ImageJobRuns::LEASE_MS - 1;
    $this->assertNull($this->runs->claim('job-1', 0), 'the lease is still live');
    $this->assertSame([], $this->runs->expired());

    $this->now += 1;
    $this->assertSame(['job-1'], $this->runs->expired());
    $recovery = $this->runs->claim('job-1', 0);
    $this->assertNotNull($recovery);
    $previous = $recovery->previous();
    $this->assertSame('run-1', $previous['run_token']);
    $this->assertSame('att_1', $previous['usage_attempt_id']);
    $this->assertSame($dispatchedAt, (int) $previous['dispatched_at']);
    $this->assertNull($previous['responded_at']);
    $this->assertSame([], $this->runs->expired(), 'the recovery holds a fresh lease');

    // The dead worker cannot touch the row any more, even if it comes back.
    $this->assertFalse($dead->responded());
    $this->assertFalse($dead->dispatched());
    $dead->release();
    $row = $this->runs->row('job-1');
    $this->assertSame($recovery->token, $row['run_token']);
    $this->assertNotSame('run-1', $row['run_token']);
    $this->assertSame(ImageJobRuns::STATE_RUNNING, $row['state']);
    $this->assertNull($row['responded_at']);
  }

  public function testTheHeartbeatRenewsTheLeaseAtMostOncePerInterval(): void {
    $run = $this->runs->claim('job-1', 0);
    $claimedUntil = (int) $this->runs->row('job-1')['lease_until'];

    $this->now += 1_000;
    $this->assertTrue($run->heartbeat());
    $this->assertSame($this->now + ImageJobRuns::LEASE_MS, (int) $this->runs->row('job-1')['lease_until']);
    $renewedUntil = (int) $this->runs->row('job-1')['lease_until'];
    $this->assertGreaterThan($claimedUntil, $renewedUntil);

    // Progress callbacks fire many times per second; only one write per interval.
    $this->now += ImageJobRuns::HEARTBEAT_MS - 1;
    $this->assertTrue($run->heartbeat());
    $this->assertSame($renewedUntil, (int) $this->runs->row('job-1')['lease_until']);
    $this->now += 1;
    $this->assertTrue($run->heartbeat());
    $this->assertSame($this->now + ImageJobRuns::LEASE_MS, (int) $this->runs->row('job-1')['lease_until']);
  }

  public function testAHeartbeatReportsALostLease(): void {
    $run = $this->runs->claim('job-1', 0);
    $this->now += ImageJobRuns::LEASE_MS;
    $this->assertNotNull($this->runs->claim('job-1', 0), 'recovered by another worker');
    $this->now += ImageJobRuns::HEARTBEAT_MS;
    $this->assertFalse($run->heartbeat());
  }

  public function testDispatchAndResponseMarksExtendTheLease(): void {
    $run = $this->runs->claim('job-1', 0);
    $this->now += 100_000;
    $this->assertTrue($run->dispatched());
    $this->assertSame($this->now + ImageJobRuns::LEASE_MS, (int) $this->runs->row('job-1')['lease_until']);
    $this->now += 100_000;
    $this->assertTrue($run->responded());
    $row = $this->runs->row('job-1');
    $this->assertSame($this->now + ImageJobRuns::LEASE_MS, (int) $row['lease_until']);
    $this->assertSame($this->now - 100_000, (int) $row['dispatched_at']);
    $this->assertSame($this->now, (int) $row['responded_at']);
  }

  public function testExpiredRunsAreListedOldestFirstAndFinishedOnesNever(): void {
    $this->runs->claim('job-late', 0);
    $this->now += 10;
    $this->runs->claim('job-early', 0)->release();
    $this->now += 10;
    $this->runs->claim('job-latest', 0);
    $this->now += ImageJobRuns::LEASE_MS + 100;
    $this->assertSame(['job-late', 'job-latest'], $this->runs->expired());
    $this->assertSame(['job-late'], $this->runs->expired(1));
  }

}
