<?php

declare(strict_types=1);

namespace Drupal\Tests\tourauto\Unit;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\tour\TourHelper;
use Drupal\tourauto\TourautoManager;
use Drupal\user\UserDataInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the TourautoManager service.
 */
#[CoversClass(TourautoManager::class)]
#[Group('tourauto')]
class TourautoManagerTest extends UnitTestCase {

  /**
   * The tourauto manager under test.
   *
   * @var \Drupal\tourauto\TourautoManager
   */
  protected TourautoManager $manager;

  /**
   * The mocked account.
   *
   * @var \Drupal\Core\Session\AccountInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $account;

  /**
   * The mocked user data service.
   *
   * @var \Drupal\user\UserDataInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $userData;

  /**
   * The mocked route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $routeMatch;

  /**
   * The mocked entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeManager;

  /**
   * The mocked tour helper.
   *
   * @var \Drupal\tour\TourHelper|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $tourHelper;

  /**
   * The mocked string translation service.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $stringTranslation;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->account = $this->createMock(AccountInterface::class);
    $this->userData = $this->createMock(UserDataInterface::class);
    $this->routeMatch = $this->createMock(RouteMatchInterface::class);
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->tourHelper = $this->createMock(TourHelper::class);
    $this->stringTranslation = $this->createMock(TranslationInterface::class);

    // Mock the tour storage and query.
    $tourStorage = $this->createMock(EntityStorageInterface::class);
    $tourQuery = $this->createMock(QueryInterface::class);

    $tourQuery->method('accessCheck')->willReturnSelf();
    $tourStorage->method('getQuery')->willReturn($tourQuery);
    $this->entityTypeManager->method('getStorage')->with('tour')->willReturn($tourStorage);

    $this->manager = new TourautoManager(
      $this->account,
      $this->userData,
      $this->routeMatch,
      $this->entityTypeManager,
      $this->tourHelper,
      $this->stringTranslation
    );
  }

  /**
   * Tests tourautoEnabled() default behavior.
   */
  public function testTourautoEnabledDefaultTrue(): void {
    $this->account->method('id')->willReturn(1);
    $this->userData->method('get')->willReturn(NULL);

    $this->assertTrue($this->manager->tourautoEnabled());
  }

  /**
   * Tests tourautoEnabled() honoring user preference.
   */
  public function testTourautoEnabledUserPreference(): void {
    $this->account->method('id')->willReturn(1);
    $this->userData->method('get')->willReturn(FALSE);

    $this->assertFalse($this->manager->tourautoEnabled());
  }

  /**
   * Tests tourautoEnabled() for anonymous users.
   */
  public function testTourautoEnabledAnonymousUser(): void {
    $this->account->method('id')->willReturn(0);

    $this->assertFalse($this->manager->tourautoEnabled());
  }

  /**
   * Tests setTourautoPreference().
   */
  public function testSetTourautoPreference(): void {
    $this->account->method('id')->willReturn(1);
    $this->userData->expects($this->once())
      ->method('set')
      ->with('tourauto', 1, 'tourauto_enable', TRUE);

    $this->manager->setTourautoPreference(TRUE);
  }

  /**
   * Tests setTourautoPreference() for anonymous users.
   */
  public function testSetTourautoPreferenceAnonymousUser(): void {
    $this->account->method('id')->willReturn(0);
    $this->userData->expects($this->never())->method('set');

    $this->manager->setTourautoPreference(TRUE);
  }

  /**
   * Tests getSeenTours().
   */
  public function testGetSeenTours(): void {
    $this->account->method('id')->willReturn(1);
    $this->userData->method('get')->willReturn('{"seen": {"tour1": true, "tour2": true}}');

    $expected = ['tour1', 'tour2'];
    $this->assertEquals($expected, $this->manager->getSeenTours());
  }

  /**
   * Tests getSeenTours() with empty state.
   */
  public function testGetSeenToursEmptyState(): void {
    $this->account->method('id')->willReturn(1);
    $this->userData->method('get')->willReturn(NULL);

    $this->assertEquals([], $this->manager->getSeenTours());
  }

  /**
   * Tests markToursSeen().
   */
  public function testMarkToursSeen(): void {
    $this->account->method('id')->willReturn(1);
    $this->userData->method('get')->willReturn('{"seen": {"tour1": true}}');
    $this->userData->expects($this->once())
      ->method('set')
      ->with('tourauto', 1, 'tourauto_state', '{"seen":{"tour1":true,"tour2":true,"tour3":true}}');

    $this->manager->markToursSeen(['tour2', 'tour3']);
  }

  /**
   * Tests clearState().
   */
  public function testClearState(): void {
    $this->account->method('id')->willReturn(1);
    $this->userData->expects($this->once())
      ->method('delete')
      ->with('tourauto', 1, 'tourauto_state');

    $this->manager->clearState();
  }

  /**
   * Tests getManagerForAccount().
   */
  public function testGetManagerForAccount(): void {
    $newAccount = $this->createMock(AccountInterface::class);
    $newAccount->method('id')->willReturn(2);

    $newManager = $this->manager->getManagerForAccount($newAccount);

    $this->assertInstanceOf(TourautoManager::class, $newManager);
    $this->assertNotSame($this->manager, $newManager);
  }

  /**
   * Tests translate().
   */
  public function testTranslate(): void {
    $result = $this->manager->translate('Test string');
    $this->assertInstanceOf(TranslatableMarkup::class, $result);
  }

}
