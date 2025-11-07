<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_share_client\Functional;

use Drupal\user\UserInterface;

/**
 * Functional test class for entity reference field with missing entity.
 *
 * @group entity_share
 * @group entity_share_client
 */
class MissingEntityReferenceTest extends EntityShareClientFunctionalTestBase {

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

    $node_storage = $this->entityTypeManager->getStorage('node');
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    // Create a node that will be referenced.
    $node_to_be_referenced = $node_storage->create([
      'uuid' => 'es_test_to_be_deleted',
      'langcode' => 'en',
      'type' => 'es_test',
      'title' => $this->randomString(),
    ]);
    $node_to_be_referenced->save();
    // Create a term that will be referenced.
    $term_to_be_referenced = $term_storage->create([
      'uuid' => 'es_test_tag_deleted',
      'langcode' => 'en',
      'vid' => 'es_test',
      'name' => $this->randomString(),
    ]);
    $term_to_be_referenced->save();

    $this->prepareContent();

    // Delete the node and term before populating the request service otherwise
    // the remote website emulation won't be ok.
    $node_to_be_referenced->delete();
    $term_to_be_referenced->delete();

    $this->populateRequestService();
    $this->deleteContent();
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntitiesDataArray() {
    return [
      'taxonomy_term' => [
        'en' => [
          'es_test_tag' => $this->getCompleteTaxonomyTermInfos([
            'vid' => [
              'value' => 'es_test',
            ],
          ]),
          'es_test_tag_2' => $this->getCompleteTaxonomyTermInfos([
            'vid' => [
              'value' => 'es_test',
            ],
          ]),
        ],
      ],
      'node' => [
        'en' => [
          'es_test_content_reference' => $this->getCompleteNodeInfos([
            // Single value field.
            'field_es_test_content_reference' => [
              'value_callback' => function () {
                return [
                  [
                    'target_id' => $this->getEntityId('node', 'es_test_to_be_deleted'),
                  ],
                ];
              },
            ],
            // Multiple value field.
            'field_es_test_taxonomy' => [
              'value_callback' => function () {
                return [
                  [
                    'target_id' => $this->getEntityId('taxonomy_term', 'es_test_tag'),
                  ],
                  [
                    'target_id' => $this->getEntityId('taxonomy_term', 'es_test_tag_deleted'),
                  ],
                  [
                    'target_id' => $this->getEntityId('taxonomy_term', 'es_test_tag_2'),
                  ],
                ];
              },
            ],
          ]),
        ],
      ],
    ];
  }

  /**
   * Test that a missing reference entity value is removed.
   *
   * It shows that a missing entity reference does not fail the import.
   */
  public function testMissingReferenceEntityValue() {
    $this->pullEveryChannels();

    $existing_entities = $this->entityTypeManager->getStorage('node')->loadByProperties(['uuid' => 'es_test_content_reference']);

    $this->assertNotEmpty($existing_entities, 'The content has been imported');
    if (!empty($existing_entities)) {
      $node = array_shift($existing_entities);
      $content_reference_value = $node->get('field_es_test_content_reference')->getValue();
      $expected_content_reference_value = [];
      $this->assertEquals($expected_content_reference_value, $content_reference_value, 'The content reference field is empty.');

      $term_reference_value = $node->get('field_es_test_taxonomy')->getValue();
      $expected_term_reference_value = [
        [
          'target_id' => $this->getEntityId('taxonomy_term', 'es_test_tag'),
        ],
        [
          'target_id' => $this->getEntityId('taxonomy_term', 'es_test_tag_2'),
        ],
      ];
      $this->assertEquals($expected_term_reference_value, $term_reference_value, 'The term reference field does not have the missing entity value and only reference 2 entities.');
    }

    // Check that the deleted tag and node had not been created.
    $deleted_taxonomy_term_id = $this->getEntityId('taxonomy_term', 'es_test_tag_deleted');
    $this->assertEmpty($deleted_taxonomy_term_id, 'The deleted taxonomy term has not been recreated.');
    $deleted_node_id = $this->getEntityId('node', 'es_test_to_be_deleted');
    $this->assertEmpty($deleted_node_id, 'The deleted node has not been recreated.');
  }

  /**
   * {@inheritdoc}
   */
  protected function createChannel(UserInterface $user) {
    parent::createChannel($user);

    // Add a channel for the node.
    $channel_storage = $this->entityTypeManager->getStorage('channel');
    $channel = $channel_storage->create([
      'id' => 'taxonomy_term_es_test_en',
      'label' => $this->randomString(),
      'channel_maxsize' => 50,
      'channel_entity_type' => 'taxonomy_term',
      'channel_bundle' => 'es_test',
      'channel_langcode' => static::$entityLangcode,
      'access_by_permission' => FALSE,
      'authorized_roles' => [],
      'authorized_users' => [
        $user->uuid(),
      ],
    ]);
    $channel->save();
    $this->channels[$channel->id()] = $channel;
  }

}
