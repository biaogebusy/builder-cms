<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_share_client\Functional;

use Drupal\entity_share_client\Entity\EntityImportStatusInterface;
use Drupal\node\NodeInterface;

/**
 * Test import policy handling.
 *
 * @group entity_share
 * @group entity_share_client
 */
class ImportPolicyTest extends EntityShareClientFunctionalTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_share_entity_test',
    'jsonapi_extras',
    'entity_share_client_import_policies_test',
  ];

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
    $this->postSetupFixture();
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntitiesDataArray() {
    return [
      'node' => [
        'en' => [
          // Default.
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
   * Test the policy of import entity status.
   */
  public function testImportEntityStatusPolicy() {
    // By default, the policy should be the default one.
    $this->pullEveryChannels();
    $this->importService->getRuntimeImportContext()->clearImportedEntities();
    $import_status = $this->loadImportStatusEntity('es_test', 'en');
    $this->assertEquals(EntityImportStatusInterface::IMPORT_POLICY_DEFAULT, $import_status->getPolicy());

    // Change the import policy to test.
    $new_plugin_configurations = [
      'default_data_processor' => [
        'policy' => 'test',
      ],
    ];
    $this->mergePluginsToImportConfig($new_plugin_configurations);

    // As the import status entity already exists, the policy will not be
    // updated.
    $this->pullEveryChannels();
    $this->importService->getRuntimeImportContext()->clearImportedEntities();
    $import_status = $this->loadImportStatusEntity('es_test', 'en');
    $this->assertEquals(EntityImportStatusInterface::IMPORT_POLICY_DEFAULT, $import_status->getPolicy());

    // Update policy during import.
    $new_plugin_configurations = [
      'default_data_processor' => [
        'update_policy' => TRUE,
      ],
    ];
    $this->mergePluginsToImportConfig($new_plugin_configurations);

    $this->pullEveryChannels();
    $import_status = $this->loadImportStatusEntity('es_test', 'en');
    $this->assertEquals('test', $import_status->getPolicy());
  }

  /**
   * Test the 'create only' policy and associated plugin.
   */
  public function testCreateOnlyPolicy() {
    // Set policy to create_only.
    $new_plugin_configurations = [
      'default_data_processor' => [
        'policy' => 'create_only',
      ],
    ];
    $this->mergePluginsToImportConfig($new_plugin_configurations);

    $this->pullEveryChannels();
    $this->checkCreatedEntities();
    $this->importService->getRuntimeImportContext()->clearImportedEntities();

    // If a change is done on the entity it will not be preserved.
    $node = $this->loadEntity('node', 'es_test');

    $original_label = $node->label();
    $new_label = $original_label . ' changed';

    $node->set('title', $new_label);
    $node->save();

    $this->pullEveryChannels();
    $this->importService->getRuntimeImportContext()->clearImportedEntities();
    $node = $this->loadEntity('node', 'es_test');
    $this->assertEquals($original_label, $node->label());

    // Enable the prevent_update_processor plugin.
    $new_plugin_configurations = [
      'prevent_update_processor' => [
        'weights' => [
          'is_entity_importable' => -5,
        ],
      ],
    ];
    $this->mergePluginsToImportConfig($new_plugin_configurations);

    $this->pullEveryChannels();
    $this->checkCreatedEntities();
    $this->importService->getRuntimeImportContext()->clearImportedEntities();

    // Now if a change is done on the entity it will be preserved.
    $node = $this->loadEntity('node', 'es_test');
    $node->set('title', $new_label);
    $node->save();

    $this->pullEveryChannels();
    $node = $this->loadEntity('node', 'es_test');
    $this->assertEquals($new_label, $node->label());
  }

}
