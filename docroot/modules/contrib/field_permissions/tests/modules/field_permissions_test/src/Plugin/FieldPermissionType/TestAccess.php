<?php

namespace Drupal\field_permissions_test\Plugin\FieldPermissionType;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\field_permissions\Plugin\CustomPermissionsInterface;
use Drupal\field_permissions\Plugin\FieldPermissionType\Base;

/**
 * A test field permission type plugin.
 *
 * @FieldPermissionType(
 *   id = "test_access",
 *   title = "Test type",
 *   description = "Denies access with 'test_' and this is an edit operation.",
 *   weight = 42
 * )
 */
class TestAccess extends Base implements CustomPermissionsInterface {

  /**
   * {@inheritdoc}
   */
  public function hasFieldAccess($operation, EntityInterface $entity, AccountInterface $account) {
    // Flag that this method was called.
    // @see \Drupal\Tests\field_permissions\Kernel\Plugin\FieldPermissionType\ManagerTest::testAppliesToField()
    // @phpstan-ignore-next-line
    \Drupal::state()->set('field_permissions_test.called_TestAccess::hasFieldAccess', TRUE);

    if ($operation === 'view') {
      return $account->hasPermission('foo access');
    }
    return strpos($this->fieldStorage->getName(), 'test_') === FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function hasFieldViewAccessForEveryEntity(AccountInterface $account) {
    return $account->hasPermission('foo access');
  }

  /**
   * {@inheritdoc}
   */
  public function getPermissions() {
    return [
      'foo access' => [
        'title' => 'Test access custom permission',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function appliesToField(FieldDefinitionInterface $field_definition): bool {
    // The state variable is used in tests as a switch.
    // @see \Drupal\Tests\field_permissions\Functional\FieldPermissionsFieldConfigEditFormTest::testAppliesToField()
    // @phpstan-ignore-next-line
    return \Drupal::state()->get('field_permissions_test.applies_to_field', TRUE);
  }

}
