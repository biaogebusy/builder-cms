<?php

namespace Drupal\Tests\field_permissions\Unit\Plugin\FieldPermissionType;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\field\FieldStorageConfigInterface;
use Drupal\field_permissions\FieldPermissionsService;
use Drupal\field_permissions\Plugin\FieldPermissionType\PrivateAccess;
use Drupal\Tests\UnitTestCase;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\UserInterface;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Tests the private access plugin.
 *
 * @coversDefaultClass \Drupal\field_permissions\Plugin\FieldPermissionType\PrivateAccess
 *
 * @group field_permissions
 */
class PrivateAccessTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * The private access plugin.
   *
   * @var \Drupal\field_permissions\Plugin\FieldPermissionType\PrivateAccess
   */
  protected $plugin;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $storage = $this->prophesize(FieldStorageConfigInterface::class);
    $field_permissions_service = $this->prophesize(FieldPermissionsService::class);

    $this->plugin = new PrivateAccess([], 'private', [], $storage->reveal(), $field_permissions_service->reveal());
  }

  /**
   * Test for `hasFieldAccess`.
   *
   * @covers ::hasFieldAccess
   *
   * @dataProvider providerTestHasFieldAccess
   */
  public function testHasFieldAccess($operation, $entity_type, $entity_id, $account_id, $owner_id, $perm_access, $is_new, $expected_access) {
    $account = $this->prophesize(AccountInterface::class);
    $entity = $this->prophesize($entity_type);
    if ($account_id !== NULL) {
      $account->id()->willReturn($account_id);
    }
    if ($perm_access !== NULL) {
      $account->hasPermission('access private fields')
        ->willReturn($perm_access);
    }
    if ($owner_id !== NULL) {
      $entity = $this->prophesize(EntityInterface::class)
        ->willImplement(EntityOwnerInterface::class);
      $entity->getOwnerId()->willReturn($owner_id);
    }
    if ($is_new !== NULL) {
      $entity->isNew()->willReturn($is_new);
    }
    if ($entity_id !== NULL) {
      $entity->id()->willReturn($entity_id);
    }
    $this->assertEquals($expected_access, $this->plugin->hasFieldAccess($operation, $entity->reveal(), $account->reveal()));
  }

  /**
   * Data provider for ::testHasFieldAccess.
   */
  public static function providerTestHasFieldAccess(): \Generator {
    yield "Has 'access private fields' permission." => [
      'operation' => ['view', 'edit'],
      'entity_type' => EntityInterface::class,
      'entity_id' => NULL,
      'account_id' => NULL,
      'owner_id' => NULL,
      'perm_access' => TRUE,
      'is_new' => NULL,
      'expected_access' => TRUE,
    ];
    yield "New entities always grant permission." => [
      'operation' => ['view', 'edit'],
      'entity_type' => EntityInterface::class,
      'entity_id' => NULL,
      'account_id' => NULL,
      'owner_id' => NULL,
      'perm_access' => NULL,
      'is_new' => TRUE,
      'expected_access' => TRUE,
    ];

    yield "User Entity: Account same as user entity." => [
      'operation' => ['view', 'edit'],
      'entity_type' => UserInterface::class,
      'entity_id' => 42,
      'account_id' => 42,
      'owner_id' => NULL,
      'perm_access' => FALSE,
      'is_new' => FALSE,
      'expected_access' => TRUE,
    ];
    yield "User Entity: Account not same as user entity." => [
      'operation' => ['view', 'edit'],
      'entity_type' => UserInterface::class,
      'entity_id' => 42,
      'account_id' => 27,
      'owner_id' => NULL,
      'perm_access' => FALSE,
      'is_new' => FALSE,
      'expected_access' => FALSE,
    ];

    yield "EntityOwnerInterface entities with access." => [
      'operation' => ['view', 'edit'],
      'entity_type' => EntityInterface::class,
      'entity_id' => NULL,
      'account_id' => 42,
      'owner_id' => 42,
      'perm_access' => FALSE,
      'is_new' => FALSE,
      'expected_access' => TRUE,
    ];

    yield "EntityOwnerInterface entities without access." => [
      'operation' => ['view', 'edit'],
      'entity_type' => EntityInterface::class,
      'entity_id' => NULL,
      'account_id' => 42,
      'owner_id' => 27,
      'perm_access' => FALSE,
      'is_new' => FALSE,
      'expected_access' => FALSE,
    ];

    yield "Non-user or none owner interface entity should always have access." => [
      'operation' => ['view', 'edit'],
      'entity_type' => EntityInterface::class,
      'entity_id' => NULL,
      'account_id' => NULL,
      'owner_id' => NULL,
      'perm_access' => NULL,
      'is_new' => NULL,
      'expected_access' => TRUE,
    ];
  }

}
