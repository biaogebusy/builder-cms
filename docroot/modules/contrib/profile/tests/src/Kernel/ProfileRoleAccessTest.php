<?php

namespace Drupal\Tests\profile\Kernel;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\profile\ProfileTestTrait;
use Drupal\user\Entity\Role;

/**
 * Tests profile role access handling.
 *
 * @group profile
 */
class ProfileRoleAccessTest extends EntityKernelTestBase {

  use ProfileTestTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'entity',
    'profile',
    'views',
  ];

  /**
   * Randomly generated profile type entity.
   *
   * No roles.
   *
   * @var \Drupal\profile\Entity\ProfileType
   */
  protected $type1;

  /**
   * Randomly generated profile type entity.
   *
   * Requires some, but not all roles.
   *
   * @var \Drupal\profile\Entity\ProfileType
   */
  protected $type2;

  /**
   * Randomly generated profile type entity.
   *
   * Requires all profile roles.
   *
   * @var \Drupal\profile\Entity\ProfileType
   */
  protected $type3;

  /**
   * Randomly generated user role entity.
   *
   * @var \Drupal\user\Entity\Role
   */
  protected $role1;

  /**
   * Randomly generated user role entity.
   *
   * @var \Drupal\user\Entity\Role
   */
  protected $role2;

  /**
   * The profile access handler.
   *
   * @var \Drupal\profile\ProfileAccessControlHandler
   */
  protected $accessHandler;

  /**
   * The access manager.
   *
   * @var \Drupal\Core\Access\AccessManagerInterface
   */
  protected $accessManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('profile');
    $this->role1 = Role::create([
      'id' => strtolower($this->randomMachineName(8)),
      'label' => $this->randomMachineName(8),
    ]);
    $this->role1->save();
    $this->role2 = Role::create([
      'id' => strtolower($this->randomMachineName(8)),
      'label' => $this->randomMachineName(8),
    ]);
    $this->role2->save();
    $this->type1 = $this->createProfileType(NULL, NULL, FALSE, []);
    $this->type2 = $this->createProfileType(NULL, NULL, FALSE, [$this->role2->id()]);
    $this->type3 = $this->createProfileType(NULL, NULL, FALSE, [
      $this->role1->id(),
      $this->role2->id(),
    ]);

    $this->accessHandler = $this->container->get('entity_type.manager')
      ->getAccessControlHandler('profile');
    $this->accessManager = $this->container->get('access_manager');

    // Do not allow uid == 1 to skew tests.
    $this->createUser();
  }

  /**
   * Tests profile create role access checks.
   */
  public function testProfileCreate() {
    $user = $this->createUser([
      "create {$this->type1->id()} profile",
      "create {$this->type2->id()} profile",
      "create {$this->type3->id()} profile",
    ]);
    // The user initially has no roles, so they can only access the first
    // profile type, which isn't restricted by role.
    $this->assertTrue($this->accessHandler->createAccess($this->type1->id(), $user, ['profile_owner' => $user]));
    $this->assertFalse($this->accessHandler->createAccess($this->type2->id(), $user, ['profile_owner' => $user]));
    $this->assertFalse($this->accessHandler->createAccess($this->type3->id(), $user, ['profile_owner' => $user]));
    // No role check is performed when the profile_owner isn't passed.
    $this->accessHandler->resetCache();
    $this->assertTrue($this->accessHandler->createAccess($this->type1->id(), $user));
    $this->assertTrue($this->accessHandler->createAccess($this->type2->id(), $user));
    $this->assertTrue($this->accessHandler->createAccess($this->type3->id(), $user));

    // With role1, the user can access the first and the third profile type.
    $this->accessHandler->resetCache();
    $user->addRole($this->role1->id());
    $user->save();
    $this->assertTrue($this->accessHandler->createAccess($this->type1->id(), $user, ['profile_owner' => $user]));
    $this->assertFalse($this->accessHandler->createAccess($this->type2->id(), $user, ['profile_owner' => $user]));
    $this->assertTrue($this->accessHandler->createAccess($this->type3->id(), $user, ['profile_owner' => $user]));

    // With role2, the user can access all three profile types.
    $this->accessHandler->resetCache();
    $user->addRole($this->role2->id());
    $user->save();
    $this->assertTrue($this->accessHandler->createAccess($this->type1->id(), $user, ['profile_owner' => $user]));
    $this->assertTrue($this->accessHandler->createAccess($this->type2->id(), $user, ['profile_owner' => $user]));
    $this->assertTrue($this->accessHandler->createAccess($this->type3->id(), $user, ['profile_owner' => $user]));
  }

  /**
   * Tests profile operations role access checks.
   */
  public function testProfileOperations() {
    $user = $this->createUser([
      "update own {$this->type1->id()} profile",
      "update own {$this->type2->id()} profile",
    ]);
    $profile1 = $this->createProfile($this->type1, $user);
    // Test access to a profile type with no role requirement.
    $this->assertTrue($this->accessHandler->access($profile1, 'update', $user));

    $profile2 = $this->createProfile($this->type2, $user);
    $this->assertFalse($this->accessHandler->access($profile2, 'update', $user));
    $this->accessHandler->resetCache();

    $user->addRole($this->role2->id());
    $user->save();
    $profile2 = $this->reloadEntity($profile2);
    $this->assertTrue($this->accessHandler->access($profile2, 'update', $user));

    $operations = ['view', 'update', 'delete'];
    $user2 = $this->createUser([
      "view any {$this->type2->id()} profile",
      "update any {$this->type2->id()} profile",
      "delete any {$this->type2->id()} profile",
    ]);
    foreach ($operations as $operation) {
      $this->assertTrue($this->accessHandler->access($profile2, $operation, $user2));
    }

    $user->removeRole($this->role2->id());
    $user->save();
    $this->accessHandler->resetCache();
    $profile2 = $this->reloadEntity($profile2);
    // Assert that each operation is denied if the profile owner doesn't have
    // one of the allowed roles.
    foreach ($operations as $operation) {
      $this->assertFalse($this->accessHandler->access($profile2, $operation, $user2));
    }

    $user3 = $this->createUser([
      "view own {$this->type3->id()} profile",
      "update own {$this->type3->id()} profile",
      "delete own {$this->type3->id()} profile",
    ]);
    $profile3 = $this->createProfile($this->type3, $user3);
    // Test the operations without the role affected.
    foreach ($operations as $operation) {
      $this->assertFalse($this->accessHandler->access($profile3, $operation, $user3));
    }
    $user3->addRole($this->role1->id());
    $user3->save();
    $this->accessHandler->resetCache();
    $profile3 = $this->reloadEntity($profile3);
    foreach ($operations as $operation) {
      $this->assertTrue($this->accessHandler->access($profile3, $operation, $user3));
    }
  }

}
