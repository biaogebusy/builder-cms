<?php

namespace Drupal\Tests\commerce_order\Kernel;

use Drupal\Core\Entity\Sql\DefaultTableMapping;

/**
 * Tests the Views data built for orders.
 *
 * @coversDefaultClass \Drupal\commerce\CommerceEntityViewsData
 *
 * @group commerce
 */
class CommerceEntityViewsDataTest extends OrderKernelTestBase {

  /**
   * Tests that a base field without installed table storage is skipped.
   *
   * A module can declare an entity reference base field in code before its
   * storage schema has been installed, for example mid-way through a module
   * install. Building the Views data for the entity type must not fatally
   * error in that transient state, because Views data is rebuilt from
   * unrelated code paths such as block plugin discovery.
   *
   * @covers ::addReverseRelationships
   *
   * @see https://www.drupal.org/project/commerce/issues/3157342
   */
  public function testReverseRelationshipWithUninstalledFieldStorage() {
    // Enable the module after the order schema has been installed, so that
    // the base field it declares exists in code but has no table storage.
    $this->enableModules(['commerce_order_views_data_test']);

    $field_definitions = $this->container->get('entity_field.manager')->getBaseFieldDefinitions('commerce_order');
    $this->assertArrayHasKey('test_uninstalled_references', $field_definitions);

    $table_mapping = $this->container->get('entity_type.manager')->getStorage('commerce_order')->getTableMapping();
    $this->assertInstanceOf(DefaultTableMapping::class, $table_mapping);
    // The field has no table storage, so getFieldTableName() would throw a
    // SqlContentEntityStorageException for it.
    $this->assertEmpty($table_mapping->getAllFieldTableNames('test_uninstalled_references'));

    // Building the Views data must not throw.
    $views_data = $this->container->get('views.views_data');
    $order_data = $views_data->get('commerce_order');
    $this->assertNotEmpty($order_data);

    // Reverse relationships for installed base fields are still added, but
    // not for the field without table storage.
    $user_data = $views_data->get('users_field_data');
    $this->assertArrayHasKey('reverse__commerce_order__uid', $user_data);
    $this->assertArrayNotHasKey('reverse__commerce_order__test_uninstalled_references', $user_data);
  }

}
