<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_share_client\Functional;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\node\NodeInterface;

/**
 * Functional test class for content entity reference field.
 *
 * @group entity_share
 * @group entity_share_client
 */
class ContentEntityReferenceTest extends EntityShareClientFunctionalTestBase {

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
          // Used for internal reference.
          'es_test_level_3' => $this->getCompleteNodeInfos([
            'status' => [
              'value' => NodeInterface::PUBLISHED,
              'checker_callback' => 'getValue',
            ],
          ]),
          // Content reference.
          'es_test_level_2' => $this->getCompleteNodeInfos([
            'field_es_test_content_reference' => [
              'value_callback' => function () {
                return [
                  [
                    'target_id' => $this->getEntityId('node', 'es_test_level_3'),
                  ],
                ];
              },
              'checker_callback' => 'getExpectedContentReferenceValue',
            ],
          ]),
          // Content reference.
          'es_test_level_1' => $this->getCompleteNodeInfos([
            'field_es_test_content_reference' => [
              'value_callback' => function () {
                return [
                  [
                    'target_id' => $this->getEntityId('node', 'es_test_level_2'),
                  ],
                ];
              },
              'checker_callback' => 'getExpectedContentReferenceValue',
            ],
          ]),
          // Content reference.
          'es_test_level_0' => $this->getCompleteNodeInfos([
            'field_es_test_content_reference' => [
              'value_callback' => function () {
                return [
                  [
                    'target_id' => $this->getEntityId('node', 'es_test_level_1'),
                  ],
                ];
              },
              'checker_callback' => 'getExpectedContentReferenceValue',
            ],
          ]),
        ],
      ],
    ];
  }

  /**
   * Test that entity reference values are good, and that entities are created.
   */
  public function testEntityReference() {
    // Test that a reference entity value is maintained.
    $this->pullEveryChannels();
    $this->checkCreatedEntities();

    // Test that a referenced entity is pulled even if not selected.
    // Need to remove all imported content prior to that.
    $this->resetImportedContent();

    // Select only the top-level referencing entity.
    $selected_entities = [
      'es_test_level_0',
    ];
    $this->importSelectedEntities($selected_entities);

    $this->checkCreatedEntities();

    // Test that only certain referenced entities are pulled when not selected.
    // Set recursion depth or import config plugin to 2.
    $new_plugin_configurations = [
      'entity_reference' => [
        'max_recursion_depth' => 2,
      ],
    ];
    $this->mergePluginsToImportConfig($new_plugin_configurations);
    $this->resetImportedContent();

    // Select only the top-level referencing entity.
    $selected_entities = [
      'es_test_level_0',
    ];
    $this->importSelectedEntities($selected_entities);

    $recreated_entities = $this->loadEntity('node', 'es_test_level_1');
    $this->assertTrue(!empty($recreated_entities), 'The node with UUID es_test_level_1 has been recreated.');
    $recreated_entities = $this->loadEntity('node', 'es_test_level_2');
    $this->assertTrue(!empty($recreated_entities), 'The node with UUID es_test_level_2 has been recreated.');
    $recreated_entities = $this->loadEntity('node', 'es_test_level_3');
    $this->assertFalse(!empty($recreated_entities), 'The node with UUID es_test_level_3 has not been recreated.');

    // Test that only certain referenced entities are pulled when not selected.
    // Set recursion depth or import config plugin to 1.
    $new_plugin_configurations = [
      'entity_reference' => [
        'max_recursion_depth' => 1,
      ],
    ];
    $this->mergePluginsToImportConfig($new_plugin_configurations);
    $this->resetImportedContent();

    // Select only the top-level referencing entity.
    $selected_entities = [
      'es_test_level_0',
    ];
    $this->importSelectedEntities($selected_entities);

    $recreated_entities = $this->loadEntity('node', 'es_test_level_1');
    $this->assertTrue(!empty($recreated_entities), 'The node with UUID es_test_level_1 has been recreated.');
    $recreated_entities = $this->loadEntity('node', 'es_test_level_2');
    $this->assertFalse(!empty($recreated_entities), 'The node with UUID es_test_level_2 has not been recreated.');
    $recreated_entities = $this->loadEntity('node', 'es_test_level_3');
    $this->assertFalse(!empty($recreated_entities), 'The node with UUID es_test_level_3 has not been recreated.');

    // Test that only certain referenced entities are pulled when not selected.
    // Set recursion depth or import config plugin to 0.
    $new_plugin_configurations = [
      'entity_reference' => [
        'max_recursion_depth' => 0,
      ],
    ];
    $this->mergePluginsToImportConfig($new_plugin_configurations);
    $this->resetImportedContent();

    // Select only the top-level referencing entity.
    $selected_entities = [
      'es_test_level_0',
    ];
    $this->importSelectedEntities($selected_entities);

    $recreated_entities = $this->loadEntity('node', 'es_test_level_1');
    $this->assertFalse(!empty($recreated_entities), 'The node with UUID es_test_level_1 has not been recreated.');
    $recreated_entities = $this->loadEntity('node', 'es_test_level_2');
    $this->assertFalse(!empty($recreated_entities), 'The node with UUID es_test_level_2 has not been recreated.');
    $recreated_entities = $this->loadEntity('node', 'es_test_level_3');
    $this->assertFalse(!empty($recreated_entities), 'The node with UUID es_test_level_3 has not been recreated.');

    // Test that only certain referenced entities are pulled when not selected.
    // Activate "Skip imported" with default settings.
    $new_plugin_configurations = [
      'skip_imported' => [
        'weights' => [
          'is_entity_importable' => -5,
        ],
      ],
      // Let's test with default recursion level (ie. unlimited).
      'entity_reference' => [
        'max_recursion_depth' => 2,
      ],
    ];
    $this->mergePluginsToImportConfig($new_plugin_configurations);
    $this->resetImportedContent();

    // Scenario 1: first import the referencing entity and then, again,
    // the target.
    // Select only the referencing entity.
    $selected_entities = [
      'es_test_level_2',
    ];
    $this->importSelectedEntities($selected_entities);

    $target_entity = $this->loadEntity('node', 'es_test_level_3');
    $this->assertNotNull($target_entity, 'The target node has been created in the first import.');
    $referencing_entity = $this->loadEntity('node', 'es_test_level_2');
    $this->assertNotNull($referencing_entity, 'The referencing node has been created in the first import.');
    $this->importService->getRuntimeImportContext()->clearImportedEntities();

    // Select only the target entity.
    $selected_entities = [
      'es_test_level_3',
    ];
    $this->importSelectedEntities($selected_entities);

    $target_entity = $this->loadEntity('node', 'es_test_level_3');
    $this->assertNotNull($target_entity, 'The target node still exists after the second import.');

    // Test if the relation between the entities is maintained.
    $expected_value = $target_entity->id();
    $actual_value = $referencing_entity->field_es_test_content_reference->target_id;
    $this->assertEquals($expected_value, $actual_value, 'The referencing node references the target node after second import.');

    // Delete created entities before the second turn.
    $this->resetImportedContent();

    // Scenario 2: first import the target and then the referencing entity.
    // Select only the target entity.
    $selected_entities = [
      'es_test_level_3',
    ];
    $this->importSelectedEntities($selected_entities);

    $target_entity = $this->loadEntity('node', 'es_test_level_3');
    $this->assertNotNull($target_entity, 'The target node has been created in the first import.');
    $this->importService->getRuntimeImportContext()->clearImportedEntities();

    // Select only the referencing entity.
    $selected_entities = [
      'es_test_level_2',
    ];
    $this->importSelectedEntities($selected_entities);

    $target_entity = $this->loadEntity('node', 'es_test_level_3');
    $this->assertNotNull($target_entity, 'The target node still exists after the second import.');
    $referencing_entity = $this->loadEntity('node', 'es_test_level_2');
    $this->assertNotNull($referencing_entity, 'The referencing node has been created in the second import.');

    // Test if the relation between the entities is maintained.
    $expected_value = $target_entity->id();
    $actual_value = $referencing_entity->field_es_test_content_reference->target_id;
    $this->assertEquals($expected_value, $actual_value, 'The referencing node references the target node after second import.');
  }

  /**
   * Helper function.
   *
   * After the value_callback is re-evaluated, the nid will be changed. So need
   * a specific checker_callback.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity.
   * @param string $field_name
   *   The field to retrieve the value.
   *
   * @return array
   *   The expected value after import.
   */
  protected function getExpectedContentReferenceValue(ContentEntityInterface $entity, string $field_name) {
    // A little trick to dynamically get the correct value of referenced
    // entity, because our mock content UUID's respect this rule.
    // Otherwise we would need to add a new parameter to 'checker_callback'.
    $level = (int) str_replace('es_test_level_', '', $entity->uuid());
    $target_uuid = 'es_test_level_' . ($level + 1);
    return [
      [
        'target_id' => $this->getEntityId('node', $target_uuid),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function populateRequestService() {
    parent::populateRequestService();

    // Needs to make the requests when only the referencing content will be
    // required.
    $selected_entities = [
      'es_test_level_0',
    ];
    $prepared_url = $this->prepareUrlFilteredOnUuids($selected_entities, 'node_es_test_en');
    $this->discoverJsonApiEndpoints($prepared_url);

    // Do the same for the cases when some other nodes are filtered by.
    $selected_entities = [
      'es_test_level_2',
    ];
    $prepared_url = $this->prepareUrlFilteredOnUuids($selected_entities, 'node_es_test_en');
    $this->discoverJsonApiEndpoints($prepared_url);
    $selected_entities = [
      'es_test_level_3',
    ];
    $prepared_url = $this->prepareUrlFilteredOnUuids($selected_entities, 'node_es_test_en');
    $this->discoverJsonApiEndpoints($prepared_url);
  }

}
