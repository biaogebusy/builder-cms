<?php

declare(strict_types=1);

namespace Drupal\Tests\tourauto\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\tourauto\TourautoManager;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the tourauto service functionality.
 */
#[Group('tourauto')]
class TourautoServiceTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['tour', 'tourauto', 'user'];

  /**
   * The tourauto manager service.
   *
   * @var \Drupal\tourauto\TourautoManager
   */
  protected TourautoManager $manager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('tour');
    $this->installSchema('user', ['users_data']);
    $this->installConfig(['tour']);

    $this->manager = $this->container->get('tourauto.manager');
  }

  /**
   * Tests that the tourauto manager service is available.
   */
  public function testServiceAvailable(): void {
    $this->assertInstanceOf(TourautoManager::class, $this->manager);
  }

  /**
   * Tests that the service can create managers for different accounts.
   */
  public function testGetManagerForAccount(): void {
    $account = $this->createUser();
    $manager_for_account = $this->manager->getManagerForAccount($account);

    $this->assertInstanceOf(TourautoManager::class, $manager_for_account);
    $this->assertNotSame($this->manager, $manager_for_account);
  }

  /**
   * Tests default tourauto preference for new users.
   */
  public function testDefaultTourautoPreference(): void {
    $account = $this->createUser();
    $manager = $this->manager->getManagerForAccount($account);

    // Default should be TRUE for users who haven't set a preference.
    $this->assertTrue($manager->tourautoEnabled());
  }

  /**
   * Tests setting and getting tourauto preference.
   */
  public function testTourautoPreference(): void {
    $account = $this->createUser();
    $manager = $this->manager->getManagerForAccount($account);

    // Set preference to FALSE.
    $manager->setTourautoPreference(FALSE);
    $this->assertFalse($manager->tourautoEnabled());

    // Set preference to TRUE.
    $manager->setTourautoPreference(TRUE);
    $this->assertTrue($manager->tourautoEnabled());
  }

  /**
   * Tests tour state management.
   */
  public function testTourStateManagement(): void {
    $account = $this->createUser();
    $manager = $this->manager->getManagerForAccount($account);

    // Initially no tours should be seen.
    $this->assertEquals([], $manager->getSeenTours());

    // Mark some tours as seen.
    $manager->markToursSeen(['tour1', 'tour2']);
    $this->assertEquals(['tour1', 'tour2'], $manager->getSeenTours());

    // Clear the state.
    $manager->clearState();
    $this->assertEquals([], $manager->getSeenTours());
  }

  /**
   * Tests that anonymous users cannot use tourauto.
   */
  public function testAnonymousUser(): void {
    $anonymous = $this->createUser(['uid' => 0]);
    $manager = $this->manager->getManagerForAccount($anonymous);

    $this->assertFalse($manager->tourautoEnabled());
  }

  /**
   * Helper method to create a test user.
   *
   * @param array $values
   *   User values.
   *
   * @return \Drupal\user\UserInterface
   *   The created user account.
   */
  protected function createUser(array $values = []): UserInterface {
    $values += [
      'name' => $this->randomMachineName(),
      'mail' => $this->randomMachineName() . '@example.com',
      'pass' => $this->randomMachineName(),
      'status' => 1,
    ];

    $user = \Drupal::entityTypeManager()
      ->getStorage('user')
      ->create($values);
    $user->save();

    return $user;
  }

}
