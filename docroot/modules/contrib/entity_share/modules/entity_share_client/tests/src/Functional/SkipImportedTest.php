<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_share_client\Functional;

use Drupal\node\NodeInterface;

/**
 * Functional test class to test import plugin "Skip imported".
 *
 * @group entity_share
 * @group entity_share_client
 */
class SkipImportedTest extends EntityShareClientFunctionalTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $entityTypeId = 'node';

  /**
   * {@inheritdoc}
   */
  protected static $entityBundleId = 'es_test';

  /**
   * {@inheritdoc}
   */
  protected static $entityLangcode = 'en';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager->getStorage('jsonapi_resource_config')->create([
      'id' => 'node--es_test',
      'disabled' => FALSE,
      'path' => 'node/es_test',
      'resourceType' => 'node--es_test',
      'resourceFields' => [
        'changed' => [
          'fieldName' => 'changed',
          'publicName' => 'changed',
          'enhancer' => [
            'id' => '',
          ],
          // Field 'changed' must appear as JSON data attribute.
          'disabled' => FALSE,
        ],
      ],
    ])->save();

    $this->postSetupFixture();
  }

  /**
   * {@inheritdoc}
   */
  protected function getImportConfigProcessorSettings() {
    $processors = parent::getImportConfigProcessorSettings();
    $processors['skip_imported'] = [
      'weights' => [
        'is_entity_importable' => -5,
      ],
    ];
    return $processors;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntitiesDataArray() {
    return [
      'node' => [
        'en' => [
          'es_test' => $this->getCompleteNodeInfos([
            'status' => [
              'value' => NodeInterface::PUBLISHED,
              'checker_callback' => 'getValue',
            ],
          ]),
        ],
      ],
    ];
  }

  /**
   * Test the "Skip imported" Import Processor plugin.
   *
   * Test in enabled and disabled state.
   */
  public function testSkipImportedPlugin() {
    // Test that entities can be pulled with plugin "Skip imported" enabled.
    $this->pullEveryChannels();
    $this->checkCreatedEntities();

    // Clean up imported content.
    $this->importService->getRuntimeImportContext()->clearImportedEntities();
    $recreated_node = $this->loadEntity('node', 'es_test');
    $recreated_node->delete();

    // Test if plugin "Skip imported" skips entities not modified on remote.
    // Initial pull should import all entities (ie. one entity).
    $this->pullChannel('node_es_test_en');
    $imported_entities = $this->importService->getRuntimeImportContext()->getImportedEntities();
    $imported_entities_en = $imported_entities['en'] ?? [];
    $this->importService->getRuntimeImportContext()->clearImportedEntities();
    $this->assertEquals(1, count($imported_entities_en));

    // The repeated pull (without any modifications on remote) should
    // import no entities.
    $this->pullChannel('node_es_test_en');
    $imported_entities = $this->importService->getRuntimeImportContext()->getImportedEntities();
    $imported_entities_en = $imported_entities['en'] ?? [];
    $this->assertEquals(0, count($imported_entities_en));

    // Clean up imported content.
    $this->importService->getRuntimeImportContext()->clearImportedEntities();
    $recreated_node = $this->loadEntity('node', 'es_test');
    $recreated_node->delete();

    // Test behavior when plugin "Skip imported" is not enabled.
    $this->removePluginFromImportConfig('skip_imported');

    // Initial pull should import all entities (ie. one entity).
    $this->pullChannel('node_es_test_en');
    $imported_entities = $this->importService->getRuntimeImportContext()->getImportedEntities();
    $imported_entities_en = $imported_entities['en'] ?? [];
    $this->importService->getRuntimeImportContext()->clearImportedEntities();
    $this->assertEquals(1, count($imported_entities_en));

    // The repeated pull should import all entities (ie. one entity) as the
    // skip imported plugin is disabled.
    $this->pullChannel('node_es_test_en');
    $imported_entities = $this->importService->getRuntimeImportContext()->getImportedEntities();
    $imported_entities_en = $imported_entities['en'] ?? [];
    $this->assertEquals(1, count($imported_entities_en));
  }

}
