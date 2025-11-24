<?php

namespace Drupal\Tests\elasticsearch_connector\Functional;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Config\Config;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\ConfigTestTrait;

/**
 * Test that we only clear the index when certain changes are imported.
 *
 * @group elasticsearch_connector
 */
class ConfigImportIndexTest extends BrowserTestBase {
  use ConfigTestTrait;
  use IndexConfigFunctionalTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'elasticsearch_connector_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The name of the ElasticSearch index to use for this test.
   *
   * @var string
   *
   * @see tests/modules/elasticsearch_connector_test/config/install/search_api.index.test_elasticsearch_index.yml
   */
  protected string $indexId = 'test_elasticsearch_index';

  /**
   * The name of the ElasticSearch server to use for this test.
   *
   * @var string
   *
   * @see tests/modules/elasticsearch_connector_test/config/install/search_api.server.elasticsearch_server.yml
   */
  protected string $serverId = 'elasticsearch_server';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->setUpIndex();

    $this->drupalLogin($this->drupalCreateUser([
      'administer search_api',
    ]));
  }

  /**
   * Test that the index is cleared when a field is deleted.
   */
  public function testIndexClearedAfterDeletingField(): void {
    $this->assertEquals(5, $this->getNumberOfItemsInIndex());

    $config = $this->getTestIndexConfig();
    $config->clear('field_settings.width');
    $this->importConfigYaml($this->exportConfigYaml($config));

    $this->assertEquals(0, $this->getNumberOfItemsInIndex(), 'Index cleared after deleting a field.');
  }

  /**
   * Test that the index is cleared after the field mapping is changed.
   */
  public function testIndexClearedAfterFieldMappingChanged(): void {
    $this->assertEquals(5, $this->getNumberOfItemsInIndex());

    $config = $this->getTestIndexConfig();
    $config->set('field_settings.category.type', 'text');
    $config->set('field_settings.category.boost', '1.00');
    $this->importConfigYaml($this->exportConfigYaml($config));

    $this->assertEquals(0, $this->getNumberOfItemsInIndex(), 'Index cleared after changing field mapping');
  }

  /**
   * Changing field properties shouldn't clear the index.
   */
  public function testIndexIntactAfterFieldPropertiesChanged(): void {
    $this->assertEquals(5, $this->getNumberOfItemsInIndex());

    $config = $this->getTestIndexConfig();
    $config->set('field_settings.body.boost', '1.10');
    $this->importConfigYaml($this->exportConfigYaml($config));

    $this->assertEquals(5, $this->getNumberOfItemsInIndex());
  }

  /**
   * Adding a field to the index configuration shouldn't clear the index.
   */
  public function testIndexIntactAfterFieldsAdded(): void {
    $this->assertEquals(5, $this->getNumberOfItemsInIndex());

    $config = $this->getTestIndexConfig();
    $config->set('field_settings.langcode.label', 'Language');
    $config->set('field_settings.langcode.datasource_id', 'entity:entity_test_mulrev_changed');
    $config->set('field_settings.langcode.property_path', 'langcode');
    $config->set('field_settings.langcode.type', 'text');
    $config->set('field_settings.langcode.dependencies.config', ['field.storage.entity_test_mulrev_changed.langcode']);
    $this->importConfigYaml($this->exportConfigYaml($config));

    $this->assertEquals(5, $this->getNumberOfItemsInIndex());
  }

  /**
   * Saving the index configuration pages without changes shouldn't clear index.
   */
  public function testIndexIntactAfterNoChange(): void {
    $this->assertEquals(5, $this->getNumberOfItemsInIndex());

    $config = $this->getTestIndexConfig();
    $this->importConfigYaml($this->exportConfigYaml($config));

    $this->assertEquals(5, $this->getNumberOfItemsInIndex());
  }

  /**
   * Export a config object to YAML.
   *
   * @param \Drupal\Core\Config\Config $config
   *   The config object to export.
   *
   * @return string
   *   The given config object expressed as a YAML string.
   */
  protected function exportConfigYaml(Config $config): string {
    return Yaml::encode($config->getRawData());
  }

  /**
   * Get the fully qualified name for the test index.
   *
   * @return string
   *   A fully qualified name for the test index.
   */
  protected function getIndexConfigFqn(): string {
    return 'search_api.index.' . $this->indexId;
  }

  /**
   * Get a configuration object for the test index.
   *
   * @return \Drupal\Core\Config\Config
   *   The test index's configuration object.
   */
  protected function getTestIndexConfig(): Config {
    return $this->config($this->getIndexConfigFqn());
  }

  /**
   * Import configuration from a YAML string.
   *
   * @param string $configYaml
   *   The YAML string to import.
   */
  protected function importConfigYaml(string $configYaml): void {
    // Prepare the import by creating a copy of the active config in sync.
    /** @var \Drupal\Core\Config\StorageInterface $sync */
    $sync = $this->container->get('config.storage.sync');
    /** @var \Drupal\Core\Config\StorageInterface $active */
    $active = $this->container->get('config.storage');
    $this->copyConfig($active, $sync);

    // Import the configuration.
    $importConfig = Yaml::decode($configYaml);
    $sync->write($this->getIndexConfigFqn(), $importConfig);
    $this->configImporter()->import();
  }

}
