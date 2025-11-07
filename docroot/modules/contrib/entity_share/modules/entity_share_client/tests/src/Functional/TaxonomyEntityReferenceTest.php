<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_share_client\Functional;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\entity_share_client\ImportContext;
use Drupal\user\UserInterface;

/**
 * Functional test class for taxonomy entity reference field.
 *
 * @group entity_share
 * @group entity_share_client
 */
class TaxonomyEntityReferenceTest extends EntityShareClientFunctionalTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $entityTypeId = 'taxonomy_term';

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
      'taxonomy_term' => [
        'en' => [
          'parent_tag' => $this->getCompleteTaxonomyTermInfos([]),
          'child_tag' => $this->getCompleteTaxonomyTermInfos([
            'parent' => [
              'value_callback' => function () {
                return [
                  [
                    'target_id' => $this->getEntityId('taxonomy_term', 'parent_tag'),
                  ],
                ];
              },
              'checker_callback' => 'getExpectedTaxonomyParentReferenceValue',
            ],
          ]),
        ],
      ],
      'node' => [
        'en' => [
          'es_test_taxonomy_reference' => $this->getCompleteNodeInfos([
            'type' => [
              'value' => 'es_test',
              'checker_callback' => 'getTargetId',
            ],
            'field_es_test_taxonomy' => [
              'value_callback' => function () {
                return [
                  [
                    'target_id' => $this->getEntityId('taxonomy_term', 'child_tag'),
                  ],
                ];
              },
              'checker_callback' => 'getExpectedTaxonomyReferenceValue',
            ],
          ]),
        ],
      ],
    ];
  }

  /**
   * Test that a referenced entity is pulled even if not selected.
   *
   * This test that:
   *   - an taxonomy entity reference field is working
   *   - the parent base field on taxonomy entities is working.
   */
  public function testReferencedEntityCreated() {
    // Select only the referencing node entity.
    $selected_entities = [
      'es_test_taxonomy_reference',
    ];
    $prepared_url = $this->prepareUrlFilteredOnUuids($selected_entities, 'node_es_test_en');
    // Prepare import context.
    $import_context = new ImportContext($this->remote->id(), 'node_es_test_en', $this::IMPORT_CONFIG_ID);
    $this->importService->prepareImport($import_context);
    // Imports data from the remote URL.
    $this->importService->importFromUrl($prepared_url);

    $this->checkCreatedEntities();
  }

  /**
   * {@inheritdoc}
   */
  protected function populateRequestService() {
    parent::populateRequestService();
    // Needs to make the requests when only the referencing content will be
    // required.
    $selected_entities = [
      'es_test_taxonomy_reference',
    ];
    $prepared_url = $this->prepareUrlFilteredOnUuids($selected_entities, 'node_es_test_en');
    $this->discoverJsonApiEndpoints($prepared_url);
  }

  /**
   * {@inheritdoc}
   */
  protected function createChannel(UserInterface $user) {
    parent::createChannel($user);

    // Add a channel for the node.
    $channel_storage = $this->entityTypeManager->getStorage('channel');
    $channel = $channel_storage->create([
      'id' => 'node_es_test_en',
      'label' => $this->randomString(),
      'channel_maxsize' => 50,
      'channel_entity_type' => 'node',
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

  /**
   * Helper function.
   *
   * After the value_callback is re-evaluated, the entity id will be changed.
   * So need a specific checker_callback.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity.
   * @param string $field_name
   *   The field to retrieve the value.
   *
   * @return array
   *   The expected value after import.
   */
  protected function getExpectedTaxonomyReferenceValue(ContentEntityInterface $entity, string $field_name) {
    return [
      [
        'target_id' => $this->getEntityId('taxonomy_term', 'child_tag'),
      ],
    ];
  }

  /**
   * Helper function.
   *
   * After the value_callback is re-evaluated, the entity id will be changed.
   * So need a specific checker_callback.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity.
   * @param string $field_name
   *   The field to retrieve the value.
   *
   * @return array
   *   The expected value after import.
   */
  protected function getExpectedTaxonomyParentReferenceValue(ContentEntityInterface $entity, string $field_name) {
    return [
      [
        'target_id' => $this->getEntityId('taxonomy_term', 'parent_tag'),
      ],
    ];
  }

}
