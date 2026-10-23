<?php

declare(strict_types=1);

namespace Drupal\Tests\xinshi_ai_usage\Unit;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\file\FileInterface;
use Drupal\user\UserInterface;
use Drupal\xinshi_ai_usage\Service\UsageUserLabelService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

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
    $this->assertSame(array_fill(0, count($ids), NULL), array_column($report['rows'], 'dimension_avatar'));
  }

  #[DataProvider('avatarAccessCases')]
  public function testAvatarsRequireCurrentUserFieldAndFileAccess(
    bool $userAccess, bool $fieldAccess, bool $empty, bool $fileExists,
    bool $fileAccess, bool $uriAccess, ?string $expected,
  ): void {
    $viewer = $this->createMock(AccountInterface::class);
    $picture = $this->createMock(FieldItemListInterface::class);
    $picture->method('access')->with('view', $viewer)->willReturn($fieldAccess);
    $picture->method('isEmpty')->willReturn($empty);
    $picture->method('getValue')->willReturn([['target_id' => '21']]);
    $user = $this->createMock(UserInterface::class);
    $user->method('hasField')->with('user_picture')->willReturn(TRUE);
    $user->method('access')->willReturnCallback(
      static fn(string $operation): bool => $operation === 'view label' || $userAccess,
    );
    $user->method('getDisplayName')->willReturn('editor');
    $user->method('get')->with('user_picture')->willReturn($picture);
    $uri = $this->createMock(FieldItemListInterface::class);
    $uri->method('access')->with('view', $viewer)->willReturn($uriAccess);
    $file = $this->createMock(FileInterface::class);
    $file->method('access')->with('view', $viewer)->willReturn($fileAccess);
    $file->method('get')->with('uri')->willReturn($uri);
    $file->expects($expected ? $this->once() : $this->never())->method('createFileUrl')
      ->with(FALSE)->willReturn('https://example.test/avatar.png');
    $users = $this->createMock(EntityStorageInterface::class);
    $users->expects($this->once())->method('loadMultiple')->with(['7', '8'])
      ->willReturn([7 => $user, 8 => $user]);
    $files = $this->createMock(EntityStorageInterface::class);
    $loadFiles = $userAccess && $fieldAccess && !$empty;
    $files->expects($loadFiles ? $this->once() : $this->never())->method('loadMultiple')
      ->with(['21'])->willReturn($fileExists ? [21 => $file] : []);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->willReturnMap([['user', $users], ['file', $files]]);
    $service = new UsageUserLabelService($entities);
    $report = $service->enrich([
      'filter' => ['dimension' => 'users'],
      'rows' => [['dimension' => '7'], ['dimension' => '8']],
    ], $viewer);
    $this->assertSame([$expected, $expected], array_column($report['rows'], 'dimension_avatar'));
    $this->assertSame(['editor', 'editor'], array_column($report['rows'], 'dimension_label'));
  }

  public static function avatarAccessCases(): array {
    return [
      'accessible shared file' => [TRUE, TRUE, FALSE, TRUE, TRUE, TRUE, 'https://example.test/avatar.png'],
      'user denied' => [FALSE, TRUE, FALSE, TRUE, TRUE, TRUE, NULL],
      'picture field denied' => [TRUE, FALSE, FALSE, TRUE, TRUE, TRUE, NULL],
      'no picture' => [TRUE, TRUE, TRUE, TRUE, TRUE, TRUE, NULL],
      'missing file' => [TRUE, TRUE, FALSE, FALSE, TRUE, TRUE, NULL],
      'file denied' => [TRUE, TRUE, FALSE, TRUE, FALSE, TRUE, NULL],
      'file URI denied' => [TRUE, TRUE, FALSE, TRUE, TRUE, FALSE, NULL],
    ];
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
