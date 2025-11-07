<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_share_client\Functional;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\entity_share_test\FakeDataGenerator;

/**
 * Functional test class for paragraphs.
 *
 * Tests entity references to paragraphs, as well as the JSON:API field
 * enhancer "Serialized Data" which is used in paragraph entities.
 *
 * @group entity_share
 * @group entity_share_client
 */
class ParagraphTest extends EntityShareClientFunctionalTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'jsonapi_extras',
    'paragraphs_test',
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
   * The tested paragraph behavior settings.
   *
   * @var array
   */
  protected static $behaviorSettings = [
    'test_bold_text' => [
      'bold_text' => 1,
    ],
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->entityTypeManager->getStorage('jsonapi_resource_config')->create([
      'id' => 'paragraph--es_test',
      'disabled' => FALSE,
      'path' => 'paragraph/es_test',
      'resourceType' => 'paragraph--es_test',
      'resourceFields' => [
        'behavior_settings' => [
          'fieldName' => 'behavior_settings',
          'publicName' => 'behavior_settings',
          'enhancer' => [
            'id' => 'entity_share_serialized_data',
          ],
          'disabled' => FALSE,
        ],
      ],
    ])->save();
    $this->postSetupFixture();
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntitiesDataArray() {
    return [
      'paragraph' => [
        'en' => [
          'es_test_paragraph' => $this->getCompleteParagraphInfos([
            'field_es_test_text_plain' => [
              'value' => FakeDataGenerator::text(255),
              'checker_callback' => 'getValue',
            ],
            // We are testing the JSON:API field enhancer "Serialized Data" by
            // exposing this paragraph property.
            'behavior_settings' => [
              'value' => serialize(static::$behaviorSettings),
              'checker_callback' => 'getExpectedBehaviorSettingsValue',
            ],
          ]),
        ],
      ],
      'node' => [
        'en' => [
          // Paragraph reference.
          'es_test_paragraph_reference' => $this->getCompleteNodeInfos([
            'field_es_test_paragraphs' => [
              'value_callback' => function () {
                return [
                  [
                    'target_id' => $this->getEntityId('paragraph', 'es_test_paragraph'),
                    'target_revision_id' => $this->getEntityRevisionId('paragraph', 'es_test_paragraph'),
                  ],
                ];
              },
              'checker_callback' => 'getExpectedParagraphReferenceValue',
            ],
          ]),
        ],
      ],
    ];
  }

  /**
   * Test that a reference entity value is still maintained.
   */
  public function testReferenceEntityValue() {
    $this->pullEveryChannels();
    $this->checkCreatedEntities();
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
  protected function getExpectedParagraphReferenceValue(ContentEntityInterface $entity, string $field_name) {
    return [
      [
        'target_id' => $this->getEntityId('paragraph', 'es_test_paragraph'),
        'target_revision_id' => $this->getEntityRevisionId('paragraph', 'es_test_paragraph'),
      ],
    ];
  }

  /**
   * Helper function to check behavior settings value.
   *
   * @return string
   *   The expected value after import.
   */
  protected function getExpectedBehaviorSettingsValue(ContentEntityInterface $entity, string $field_name) {
    return serialize(static::$behaviorSettings);
  }

}
