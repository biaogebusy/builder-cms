<?php

namespace Drupal\Tests\field_permissions\Unit\Plugin\FieldPermissionType;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\field\FieldStorageConfigInterface;
use Drupal\field_permissions\FieldPermissionsService;
use Drupal\field_permissions\Plugin\FieldPermissionType\CustomAccess;
use Drupal\Tests\UnitTestCase;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\UserInterface;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Tests for the custom access permission type plugin.
 *
 * @coversDefaultClass \Drupal\field_permissions\Plugin\FieldPermissionType\CustomAccess
 *
 * @group field_permissions
 */
class CustomAccessTest extends UnitTestCase {

  use ProphecyTrait;
  /**
   * The custom access plugin.
   *
   * @var \Drupal\field_permissions\Plugin\FieldPermissionType\CustomAccess
   */
  protected $plugin;

  /**
   * {@inheritdoc}
   */
  public function setUp():void {
    parent::setUp();
    $field_permissions = $this->prophesize(FieldPermissionsService::class);
    $storage = $this->prophesize(FieldStorageConfigInterface::class);
    $storage->getName()->willReturn('foo_field');

    $this->plugin = new CustomAccess([], 'custom', [], $storage->reveal(), $field_permissions->reveal());
  }

  /**
   * Test for `hasFieldAccess`.
   *
   * @covers ::hasFieldAccess
   *
   * @dataProvider providerTestHasFieldAccess
   */

  /**
   * Helper function to assert hasFieldAccess tests.
   *
   * @param string $operation
   *   The Permission operation.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity the permission is acting on.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Account the permission is valid for.
   * @param bool $access
   *   The expected access result.
   */
  private function hasFieldAccess($operation, EntityInterface $entity, AccountInterface $account, $access) {
    $this->assertEquals($access, $this->plugin->hasFieldAccess($operation, $entity, $account));
  }

  /**
   * Test an invalid operation.
   *
   * @covers ::hasFieldAccess
   */
  public function testInvalidOperation() {
    // Edit|view access allowed.
    $account = $this->prophesize(AccountInterface::class);
    $entity = $this->prophesize(EntityInterface::class);
    $this->expectException(\AssertionError::class, 'The operation is either "edit" or "view", "bad operation" given instead.');
    $this->plugin->hasFieldAccess('bad operation', $entity->reveal(), $account->reveal());
  }

  /**
   * Tests for create access allowed.
   *
   * @covers ::hasFieldAccess
   */
  public function testCreateAccessAllowed() {
    $account = $this->prophesize(AccountInterface::class);
    $account->hasPermission('create foo_field')->willReturn(TRUE);
    $entity = $this->prophesize(EntityInterface::class);
    $entity->isNew()->willReturn(TRUE);
    $this->hasFieldAccess('edit', $entity->reveal(), $account->reveal(), TRUE);
  }

  /**
   * Tests for create access denied.
   *
   * @covers ::hasFieldAccess
   */
  public function testCreateAccessDenied() {
    $account = $this->prophesize(AccountInterface::class);
    $account->hasPermission('create foo_field')->willReturn(FALSE);
    $entity = $this->prophesize(EntityInterface::class);
    $entity->isNew()->willReturn(TRUE);
    $this->hasFieldAccess('edit', $entity->reveal(), $account->reveal(), FALSE);
  }

  /**
   * Tests view/edit operations for access granted/denied.
   *
   * @param string $operation
   *   Operation to test against.
   * @param bool $access
   *   Permission and associated Result (they should be the same).
   *
   * @covers ::hasFieldAccess
   * @dataProvider providerTestHasFieldAccess
   */
  public function testEditViewAccess($operation, $access) {
    $account = $this->prophesize(AccountInterface::class);
    $account->hasPermission($operation . ' foo_field')->willReturn($access);
    $entity = $this->prophesize(EntityInterface::class);
    $this->hasFieldAccess($operation, $entity->reveal(), $account->reveal(), $access);
  }

  /**
   * Matrix of view/edit grant and deny on global permissions.
   *
   * Data provider for ::testEditViewAccess.
   *
   * @return \Generator
   *   The data.
   */
  public static function providerTestHasFieldAccess(): \Generator {
    yield 'Edit field allowed.' => ['edit', TRUE];
    yield 'View field allowed.' => ['view', TRUE];
    yield 'Edit field denied.' => ['edit', FALSE];
    yield 'View field denied.' => ['view', FALSE];
  }

  /**
   * Matrix of tests for Edit/View own permissions.
   *
   * @param string $entity_type
   *   The entity type to check permissions against.
   * @param int $entity_id
   *   The mocked entity ID.
   * @param int $account_id
   *   The mocked user id.
   * @param int $owner_id
   *   The id of the over of the entity.
   * @param bool $perm_access
   *   The site access permission check.
   * @param bool $own_access
   *   The owner access permission check.
   * @param bool $access
   *   The expected access result.
   *
   * @covers ::hasFieldAccess
   * @dataProvider providerEditViewOwnAccess
   */
  public function testEditViewOwnAccess($entity_type, $entity_id, $account_id, $owner_id, $perm_access, $own_access, $access) {
    foreach (['edit', 'view'] as $operation) {
      $account = $this->prophesize(AccountInterface::class);
      $account->hasPermission($operation . ' foo_field')
        ->willReturn($perm_access);
      $account->hasPermission($operation . ' own foo_field')
        ->willReturn($own_access);
      $account->id()->willReturn($account_id);
      $entity = $this->prophesize($entity_type);
      if ($entity_type === EntityInterface::class) {
        $entity->willImplement(EntityOwnerInterface::class);
        $entity->getOwnerId()->willReturn($owner_id);
      }
      $entity->id()->willReturn($entity_id);
      $entity->isNew()->willReturn(FALSE);
      $this->hasFieldAccess($operation, $entity->reveal(), $account->reveal(), $access);
    }
  }

  /**
   * Matrix of view/edit grant and deny data.
   *
   * Data provider for ::testEditViewOwnAccess.
   *
   * @return \Generator
   *   The data.
   */
  public static function providerEditViewOwnAccess(): \Generator {
    yield 'User entity, edit|view own allowed.' => [
      UserInterface::class,
      42,
      42,
      42,
      FALSE,
      TRUE,
      TRUE,
    ];
    yield 'User entity, edit|view own denied.' => [
      UserInterface::class,
      42,
      42,
      42,
      FALSE,
      FALSE,
      FALSE,
    ];
    yield 'User entity, edit|view own allowed, non-matching entity.' => [
      UserInterface::class,
      27,
      42,
      27,
      FALSE,
      TRUE,
      FALSE,
    ];
    yield 'Entity implementing EntityOwnerInterface, edit|view own allowed.' => [
      EntityInterface::class,
      27,
      42,
      42,
      FALSE,
      TRUE,
      TRUE,
    ];
    yield 'Entity implementing EntityOwnerInterface, edit|view own denied.' => [
      EntityInterface::class,
      27,
      42,
      42,
      FALSE,
      FALSE,
      FALSE,
    ];
    yield 'Entity implementing EntityOwnerInterface, edit|view own allowed, but non-matching entity owner.' => [
      EntityInterface::class,
      27,
      42,
      27,
      FALSE,
      TRUE,
      FALSE,
    ];
  }

}
