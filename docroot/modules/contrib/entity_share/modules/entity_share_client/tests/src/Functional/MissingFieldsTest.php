<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_share_client\Functional;

use Drupal\entity_share_test\FakeDataGenerator;
use Drupal\field\Entity\FieldConfig;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;

/**
 * Functional test class for non-existing fields on client website.
 *
 * @group entity_share
 * @group entity_share_client
 */
class MissingFieldsTest extends EntityShareClientFunctionalTestBase {

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
    // Need to populate field mappings now.
    $this->remoteManager->getfieldMappings($this->remote);
    $this->postSetupFixture();

    // Delete fields.
    FieldConfig::loadByName('node', 'es_test', 'field_es_test_text_plain_long')->delete();
    FieldConfig::loadByName('node', 'es_test', 'field_es_test_taxonomy')->delete();
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
        ],
      ],
      'node' => [
        'en' => [
          'es_test_missing_fields' => $this->getCompleteNodeInfos([
            // Basic field.
            'field_es_test_text_plain_long' => [
              'value' => FakeDataGenerator::text(1000),
              'checker_callback' => 'getValue',
            ],
            // Entity reference field.
            'field_es_test_taxonomy' => [
              'value_callback' => function () {
                return [
                  [
                    'target_id' => $this->getEntityId('taxonomy_term', 'es_test_tag'),
                  ],
                ];
              },
            ],
          ]),
        ],
        'fr' => [
          'es_test_missing_fields' => $this->getCompleteNodeInfos([
            // Text: plain, long.
            'field_es_test_text_plain_long' => [
              'value' => FakeDataGenerator::text(1000),
              'checker_callback' => 'getValue',
            ],
            // Multiple value field.
            'field_es_test_taxonomy' => [
              'value_callback' => function () {
                return [
                  [
                    'target_id' => $this->getEntityId('taxonomy_term', 'es_test_tag'),
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
   * Test that import does not fail when client does not have ome server fields.
   */
  public function testMissingFieldsImport() {
    // Test node with entity reference.
    $this->pullChannel('node_es_test_en');
    $this->importService->getRuntimeImportContext()->clearImportedEntities();

    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->loadEntity('node', 'es_test_missing_fields');
    $this->assertTrue($node instanceof NodeInterface, 'The node had been imported');

    // Test new translation.
    $this->pullChannel('node_es_test_fr');
    $this->importService->getRuntimeImportContext()->clearImportedEntities();
    $node = $this->loadEntity('node', 'es_test_missing_fields');
    $node_translation = $node->getTranslation('fr');
    $this->assertFalse($node_translation->isDefaultTranslation(), 'The French translation had been created.');

    // Test translation update.
    $this->pullChannel('node_es_test_fr');
    $node = $this->loadEntity('node', 'es_test_missing_fields');
    $node_translation = $node->getTranslation('fr');
    $this->assertFalse($node_translation->isDefaultTranslation(), 'The French translation had been updated.');
  }

  /**
   * {@inheritdoc}
   */
  protected function createChannel(UserInterface $user) {
    parent::createChannel($user);

    // Add a channel for the node in French.
    $channel_storage = $this->entityTypeManager->getStorage('channel');
    $channel = $channel_storage->create([
      'id' => 'node_es_test_fr',
      'label' => $this->randomString(),
      'channel_maxsize' => 50,
      'channel_entity_type' => 'node',
      'channel_bundle' => 'es_test',
      'channel_langcode' => 'fr',
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
