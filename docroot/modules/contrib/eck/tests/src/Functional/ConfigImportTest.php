<?php

namespace Drupal\Tests\eck\Functional;

use Drupal\Core\Url;

/**
 * Class ConfigImportTest.
 *
 * @group eck
 */
class ConfigImportTest extends FunctionalTestBase {

  /**
   * {@inheritdoc}
   */
  protected $profile = 'standard';

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $permissions = [
      'export configuration',
      'synchronize configuration',
      'administer eck entity types',
      'administer eck entities',
      'administer eck entity bundles',
      'bypass eck entity access',
    ];
    $this->drupalLogin($this->drupalCreateUser($permissions));

    // Export the current configuration.
    $configFactory = \Drupal::configFactory();
    /** @var \Drupal\Core\Config\StorageInterface $sync */
    $sync = $this->container->get('config.storage.sync');
    $config = $configFactory->loadMultiple($configFactory->listAll());
    foreach ($config as $name => $conf) {
      $sync->write($name, $conf->getRawData());
    }
  }

  /**
   * Tests the import of configuration.
   */
  public function testImport() {
    $defaultLanguage = \Drupal::languageManager()->getDefaultLanguage();

    $entityTypeConfigName = 'eck.eck_entity_type.test_entity';
    $entityBundleConfigName = 'eck.eck_type.test_entity.bundle';
    /** @var \Drupal\Core\Config\StorageInterface $storage */
    $storage = $this->container->get('config.storage');
    /** @var \Drupal\Core\Config\StorageInterface $sync */
    $sync = $this->container->get('config.storage.sync');

    // Verify the configuration to create does not exist yet.
    $this->assertFalse($storage->exists($entityTypeConfigName), 'Entity config absent as expected.');
    $this->assertFalse($storage->exists($entityBundleConfigName), 'Bundle config absent as expected');

    // Create entity type config entity.
    $entityTypeConfiguration = [
      'id' => 'test_entity',
      'label' => 'Test entity',
      'langcode' => $defaultLanguage->getId(),
      'dependencies' => [],
      'uuid' => '30df59bd-7b03-4cf7-bb35-d42fc49f0651',
      'status' => TRUE,
      'uid' => TRUE,
      'created' => TRUE,
      'changed' => TRUE,
      'title' => TRUE,
    ];
    $sync->write($entityTypeConfigName, $entityTypeConfiguration);

    // Create entity bundle config entity.
    $entityBundleConfiguration = [
      'uuid' => '44bb277a-8358-4bc4-b439-577b0cb96820',
      'langcode' => $defaultLanguage->getId(),
      'status' => TRUE,
      'dependencies' => [],
      'name' => 'Bundle',
      'type' => 'bundle',
      'description' => '',
    ];
    $sync->write($entityBundleConfigName, $entityBundleConfiguration);

    // Import the configuration.
    $this->drupalPostForm(Url::fromRoute('config.sync'), [], $this->t('Import all'));

    // Verify the values appeared.
    $config = $this->config($entityTypeConfigName);
    $this->assertEquals($config->getRawData(), $entityTypeConfiguration, 'Entity type configuration imported successfully.');
    // Verify the values appeared.
    $config = $this->config($entityBundleConfigName);
    $this->assertEquals($config->getRawData(), $entityBundleConfiguration, 'Entity bundle configuration imported successfully.');

    // Verify the entity type has been added.
    $this->drupalGet(Url::fromRoute('eck.entity_type.list'));
    $this->assertSession()->responseContains('Test entity');

    // Test if a new entity can be created.
    $edit = ['title[0][value]' => $this->randomMachineName()];
    $route = 'eck.entity.add';
    $routeArguments = [
      'eck_entity_type' => 'test_entity',
      'eck_entity_bundle' => 'bundle',
    ];
    $this->drupalPostForm(Url::fromRoute($route, $routeArguments), $edit, $this->t('Save'));
    $this->assertSession()->responseContains($edit['title[0][value]']);
  }

}
