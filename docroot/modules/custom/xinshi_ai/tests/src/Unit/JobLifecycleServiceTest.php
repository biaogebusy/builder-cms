<?php

declare(strict_types=1);

namespace Drupal\Tests\xinshi_ai\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;
use Drupal\xinshi_ai\Exception\JobAlreadyTerminalException;
use Drupal\xinshi_ai\Service\JobLifecycleService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Terminal states of an image job are final; every transition reads the stored job (UB2.5).
 *
 * The worker keeps a node object that may be minutes old; the stored copy is
 * a second double that a concurrent cancel can change between two steps.
 */
final class JobLifecycleServiceTest extends TestCase {

  use ImageJobNodeTrait;

  private NodeInterface&MockObject $worker;
  private NodeInterface&MockObject $stored;
  private LockBackendInterface&MockObject $lock;
  private JobLifecycleService $lifecycle;
  /** @var list<string> */
  private array $saves = [];
  /** @var list<string> */
  private array $lockCalls = [];
  private int $created = 0;
  private bool $deleted = FALSE;

  protected function setUp(): void {
    parent::setUp();
    $this->worker = $this->jobNode('job-1', ['field_status' => 'running']);
    $this->stored = $this->jobNode('job-1', ['field_status' => 'running']);
    $this->stored->method('save')->willReturnCallback(function (): int {
      $this->saves[] = (string) $this->stored->get('field_status')->value;
      return 1;
    });
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with(1)->willReturnCallback(fn(): ?NodeInterface => $this->deleted ? NULL : $this->stored);
    $storage->method('create')->willReturnCallback(function (array $values): NodeInterface {
      $this->created++;
      $asset = $this->createMock(NodeInterface::class);
      $asset->method('id')->willReturn(100 + $this->created);
      $asset->method('uuid')->willReturn('asset-' . $this->created);
      $asset->method('save')->willReturn(1);
      $this->assertSame('image_asset', $values['type']);
      return $asset;
    });
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getStorage')->with('node')->willReturn($storage);
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1_700_000_100);
    $this->lock = $this->createMock(LockBackendInterface::class);
    $this->lock->method('acquire')->willReturnCallback(function (string $name): bool {
      $this->lockCalls[] = 'acquire ' . $name;
      return TRUE;
    });
    $this->lock->method('release')->willReturnCallback(function (string $name): void {
      $this->lockCalls[] = 'release ' . $name;
    });
    $this->lifecycle = new JobLifecycleService($manager, $time,
      $this->createMock(EntityRepositoryInterface::class), $this->lock);
  }

  public function testTransitionsRunUnderTheJobLockAndSyncTheWorkerCopy(): void {
    $this->assertTrue($this->lifecycle->markCompleted($this->worker));
    $this->assertSame(['failed'], $this->saves, 'completion without any image is a failure');
    $this->assertSame(['acquire xinshi_ai:image_job:job-1', 'release xinshi_ai:image_job:job-1'], $this->lockCalls);
    $this->assertSame('failed', $this->worker->get('field_status')->value);
    $this->assertSame(1_700_000_100, $this->worker->get('field_completed_at')->value);
  }

  public function testCompletionWithoutAnyAssetIsAFailure(): void {
    $this->assertTrue($this->lifecycle->markCompleted($this->worker));
    $this->assertSame('failed', $this->stored->get('field_status')->value);
  }

  public function testCompletionWithAssetsSucceeds(): void {
    $this->stored->set('field_n_succeeded', 2);
    $this->assertTrue($this->lifecycle->markCompleted($this->worker));
    $this->assertSame('succeeded', $this->stored->get('field_status')->value);
    $this->assertSame('succeeded', $this->worker->get('field_status')->value);
  }

  public function testACancelledJobIsNotCompletedFailedOrStartedByAStaleWorker(): void {
    // The web request cancelled the job while the worker's copy still says running.
    $this->stored->set('field_status', 'cancelled');

    $this->assertFalse($this->lifecycle->markCompleted($this->worker));
    $this->assertFalse($this->lifecycle->markFailed($this->worker, 'late error', 'provider_5xx'));
    $this->assertFalse($this->lifecycle->markStarted($this->worker));
    $this->assertFalse($this->lifecycle->finalizeInterrupted($this->worker, 'dead worker', 'process_interrupted'));

    $this->assertSame([], $this->saves, 'nothing is written over a terminal state');
    $this->assertSame('cancelled', $this->stored->get('field_status')->value);
    $this->assertSame('cancelled', $this->worker->get('field_status')->value, 'the worker copy learns the stored state');
  }

  public function testAFinishedJobCannotBeCancelledAfterwards(): void {
    $this->stored->set('field_status', 'succeeded');
    $this->assertFalse($this->lifecycle->markCancelled($this->worker));
    $this->assertSame([], $this->saves);
    $this->assertSame('succeeded', $this->worker->get('field_status')->value);
  }

  public function testCancelOfARunningJobIsRecorded(): void {
    $this->assertTrue($this->lifecycle->markCancelled($this->worker));
    $this->assertSame(['cancelled'], $this->saves);
    $this->assertSame('cancelled', $this->worker->get('field_status')->value);
  }

  public function testALateImageIsRefusedOnceTheJobEnded(): void {
    $media = $this->createMock(MediaInterface::class);
    $media->method('id')->willReturn(55);
    $this->stored->set('field_status', 'cancelled');

    try {
      $this->lifecycle->createAsset($this->worker, [], $media, 0);
      $this->fail('expected JobAlreadyTerminalException');
    }
    catch (JobAlreadyTerminalException $e) {
      $this->assertSame('job-1', $e->jobUuid);
      $this->assertSame('cancelled', $e->status);
    }
    $this->assertSame(0, $this->created, 'no asset entity is created for a late image');
    $this->assertSame([], $this->saves);
  }

  public function testAssetsCarryTheStableOutputIndexAndAdvanceTheJob(): void {
    $media = $this->createMock(MediaInterface::class);
    $media->method('id')->willReturn(55);
    $appended = [];
    $assets = $this->stored->get('field_assets');
    $assets->method('appendItem')->willReturnCallback(function (array $item) use (&$appended, $assets) {
      $appended[] = $item['target_id'];
      return $assets;
    });

    // The provider returned four images; image 0 was lost, image 2 is the
    // first one saved. Its index stays 2 rather than becoming "the first".
    $asset = $this->lifecycle->createAsset($this->worker, ['seed' => 9], $media, 2);

    $this->assertSame('asset-1', $asset->uuid());
    $this->assertSame([101], $appended);
    $this->assertSame(1, $this->stored->get('field_n_succeeded')->value);
    $this->assertSame('partial', $this->stored->get('field_status')->value);
    $this->assertSame('partial', $this->worker->get('field_status')->value);
    $this->assertSame(1, $this->worker->get('field_n_succeeded')->value);
  }

  public function testFinalizeInterruptedKeepsWhatWasSavedAndRecordsTheCause(): void {
    $this->stored->set('field_status', 'partial');
    $this->stored->set('field_n_succeeded', 1);
    $this->assertTrue($this->lifecycle->finalizeInterrupted($this->worker, 'worker died', 'process_interrupted'));
    $this->assertSame('succeeded', $this->stored->get('field_status')->value);
    $this->assertSame('worker died', $this->stored->get('field_status_reason')->value);
    $this->assertSame('process_interrupted', $this->stored->get('field_error_code')->value);

    $this->saves = [];
    $this->stored->set('field_status', 'running');
    $this->stored->set('field_n_succeeded', 0);
    $this->assertTrue($this->lifecycle->finalizeInterrupted($this->worker, 'worker died', 'process_interrupted'));
    $this->assertSame('failed', $this->stored->get('field_status')->value);
  }

  public function testProviderMetaIsRecordedEvenOnAFinishedJob(): void {
    $this->stored->set('field_status', 'cancelled');
    $this->lifecycle->recordProviderMeta($this->worker, 'req-1', 'a fluffy cat');
    $this->assertSame(['cancelled'], $this->saves);
    $this->assertSame('req-1', $this->stored->get('field_provider_request_id')->value);
    $this->assertSame('a fluffy cat', $this->stored->get('field_prompt_revised')->value);
    $this->assertSame('req-1', $this->worker->get('field_provider_request_id')->value);

    $this->saves = [];
    $this->lifecycle->recordProviderMeta($this->worker, NULL, '');
    $this->assertSame([], $this->saves, 'nothing to record, nothing written');
  }

  public function testRefreshStatusReportsTheStoredStateAndDeletion(): void {
    $this->stored->set('field_status', 'cancelled');
    $this->assertSame('cancelled', $this->lifecycle->refreshStatus($this->worker));
    $this->assertSame('cancelled', $this->worker->get('field_status')->value);

    $this->deleted = TRUE;
    $this->assertNull($this->lifecycle->refreshStatus($this->worker));
    $this->assertFalse($this->lifecycle->markCancelled($this->worker), 'a deleted job has no state to change');
    $this->assertSame([], $this->saves);
  }

  public function testALockHeldElsewhereIsWaitedForThenReported(): void {
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->method('acquire')->willReturn(FALSE);
    $lock->expects($this->once())->method('wait')->willReturn(TRUE);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $lifecycle = new JobLifecycleService($manager, $this->createMock(TimeInterface::class),
      $this->createMock(EntityRepositoryInterface::class), $lock);
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('locked by another status change');
    $lifecycle->markCancelled($this->worker);
  }

}
