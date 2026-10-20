<?php

declare(strict_types=1);

namespace Drupal\Tests\xinshi_ai_usage\Unit;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\UserInterface;
use Drupal\xinshi_ai_usage\Service\UsageUserLabelService;
use PHPUnit\Framework\TestCase;

/** Covers display names, stable IDs, batching and user-label access. */
final class UsageUserLabelTest extends TestCase {

  public function testOperationAndAttemptLabelsUseOneBatchAndPreserveIds(): void {
    $viewer = $this->createMock(AccountInterface::class);
    $user = $this->createMock(UserInterface::class);
    $user->expects($this->once())->method('access')->with('view label', $viewer)->willReturn(TRUE);
    $user->method('getDisplayName')->willReturn('林晓');
    $service = $this->service(['7'], [7 => $user]);
    $report = $service->enrich([
      'actor_user_id' => '7',
      'attempt_list' => [['actor_user_id' => '7'], ['actor_user_id' => NULL]],
    ], $viewer);
    $this->assertSame('7', $report['actor_user_id']);
    $this->assertSame('林晓', $report['actor_user_name']);
    $this->assertSame('林晓', $report['attempt_list'][0]['actor_user_name']);
    $this->assertNull($report['attempt_list'][1]['actor_user_name']);
  }

  public function testUserDimensionsPreserveMissingDeniedAndExternalActors(): void {
    $viewer = $this->createMock(AccountInterface::class);
    $user = $this->createMock(UserInterface::class);
    $user->method('access')->with('view label', $viewer)->willReturn(TRUE);
    $user->method('getDisplayName')->willReturn('editor');
    $denied = $this->createMock(UserInterface::class);
    $denied->method('access')->with('view label', $viewer)->willReturn(FALSE);
    $denied->expects($this->never())->method('getDisplayName');
    $service = $this->service(['7', '8', '9'], [7 => $user, 8 => $denied]);
    $ids = ['7', '8', '9', 'external-user', 'none', '999999999999999999999'];
    $report = $service->enrich([
      'filter' => ['dimension' => 'users'],
      'rows' => array_map(static fn(string $id): array => ['dimension' => $id], $ids),
    ], $viewer);
    $this->assertSame($ids, array_column($report['rows'], 'dimension'));
    $this->assertSame(['editor', NULL, NULL, NULL, NULL, NULL], array_column($report['rows'], 'dimension_label'));
  }

  public function testListsAndQualityRowsCarryNamesWithoutChangingOtherDimensions(): void {
    $viewer = $this->createMock(AccountInterface::class);
    $user = $this->createMock(UserInterface::class);
    $user->method('access')->willReturn(TRUE);
    $user->method('getDisplayName')->willReturn('editor');
    $service = $this->service(['7'], [7 => $user]);
    $report = $service->enrich([
      'filter' => ['dimension' => 'models'],
      'operations' => [['actor_user_id' => '7']],
      'rows' => [['actor_user_id' => '7'], ['dimension' => '7']],
    ], $viewer);
    $this->assertSame('editor', $report['operations'][0]['actor_user_name']);
    $this->assertSame('editor', $report['rows'][0]['actor_user_name']);
    $this->assertSame(['dimension' => '7'], $report['rows'][1]);
  }

  public function testReportsWithoutUsersDoNotLoadUserStorage(): void {
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->expects($this->never())->method('getStorage');
    $service = new UsageUserLabelService($entities);
    $report = ['totals' => ['operations' => 2], 'rows' => [['dimension' => 'model-a']]];
    $this->assertSame($report, $service->enrich($report, $this->createMock(AccountInterface::class)));
  }

  private function service(array $ids, array $users): UsageUserLabelService {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects($this->once())->method('loadMultiple')->with($ids)->willReturn($users);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->expects($this->once())->method('getStorage')->with('user')->willReturn($storage);
    return new UsageUserLabelService($entities);
  }

}
