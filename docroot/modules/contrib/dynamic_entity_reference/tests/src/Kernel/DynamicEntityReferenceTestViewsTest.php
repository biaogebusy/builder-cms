<?php

namespace Drupal\Tests\dynamic_entity_reference\Kernel;

use Drupal\Tests\SchemaCheckTestTrait;
use Drupal\config_test\TestInstallStorage;
use Drupal\Core\Config\InstallStorage;
use Drupal\Core\Config\TypedConfigManager;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests that test views provided by dynamic_entity_reference match schema.
 *
 * @group config
 */
class DynamicEntityReferenceTestViewsTest extends KernelTestBase {

  use SchemaCheckTestTrait;

  /**
   * Tests default configuration data type.
   */
  public function testDefaultConfig() {
    // Create a typed config manager with access to configuration schema in
    // every module, profile and theme.
    $typed_config = new TypedConfigManager(
      \Drupal::service('config.storage'),
      new TestInstallStorage(InstallStorage::CONFIG_SCHEMA_DIRECTORY),
      \Drupal::service('cache.discovery'),
      \Drupal::service('module_handler'),
      \Drupal::service('class_resolver')
    );

    // Create a configuration storage with access to default configuration in
    // every module, profile and theme.
    $default_config_storage = new TestInstallStorage('test_views');

    foreach ($default_config_storage->listAll('views.view.test_dynamic_entity_reference') as $config_name) {
      $data = $default_config_storage->read($config_name);
      $this->assertConfigSchema($typed_config, $config_name, $data);
    }
  }

}
