<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_share_client\Functional;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Url;
use Drupal\entity_share_test\FakeDataGenerator;
use Drupal\node\NodeInterface;

/**
 * Functional test class for link field.
 *
 * Dedicated test class because of the setup.
 *
 * @group entity_share
 * @group entity_share_client
 */
class LinkFieldTest extends EntityShareClientFunctionalTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'jsonapi_extras',
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

    $this->entityTypeManager->getStorage('jsonapi_resource_config')->create([
      'id' => 'node--es_test',
      'disabled' => FALSE,
      'path' => 'node/es_test',
      'resourceType' => 'node--es_test',
      'resourceFields' => [
        'field_es_test_link' => [
          'fieldName' => 'field_es_test_link',
          'publicName' => 'field_es_test_link',
          'enhancer' => [
            'id' => 'entity_share_uuid_link',
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
      'node' => [
        'en' => [
          // Used for internal linked.
          'es_test' => $this->getCompleteNodeInfos([
            'status' => [
              'value' => NodeInterface::PUBLISHED,
              'checker_callback' => 'getValue',
            ],
          ]),
          // Link: external.
          'es_test_link_external' => $this->getCompleteNodeInfos([
            'field_es_test_link' => [
              'value' => [
                [
                  'uri' => 'http://www.example.com/strings_(string_within_parenthesis)',
                  'title' => FakeDataGenerator::text(255),
                ],
              ],
              'checker_callback' => 'getFilteredStructureValues',
            ],
          ]),
          // Link: external without text.
          'es_test_link_external_without_text' => $this->getCompleteNodeInfos([
            'field_es_test_link' => [
              'value' => [
                [
                  'uri' => 'http://www.example.com/numbers_(9999)',
                ],
              ],
              'checker_callback' => 'getFilteredStructureValues',
            ],
          ]),
          // Link: external with options.
          'es_test_link_external_with_options' => $this->getCompleteNodeInfos([
            'field_es_test_link' => [
              'value' => [
                [
                  'uri' => 'http://www.example.com/',
                  'title' => FakeDataGenerator::text(255),
                  'options' => [
                    'attributes' => [
                      'class' => [
                        FakeDataGenerator::text(20),
                        FakeDataGenerator::text(20),
                        FakeDataGenerator::text(20),
                      ],
                    ],
                  ],
                ],
              ],
              'checker_callback' => 'getFilteredStructureValues',
            ],
          ]),
          // Link: internal.
          'es_test_link_internal' => $this->getCompleteNodeInfos([
            'field_es_test_link' => [
              'value_callback' => function () {
                return [
                  [
                    'uri' => 'entity:node/' . $this->getEntityId('node', 'es_test'),
                  ],
                ];
              },
              'checker_callback' => 'getExpectedInternalLinkValue',
            ],
          ]),
        ],
      ],
    ];
  }

  /**
   * Test basic pull feature.
   */
  public function testBasicPull() {
    $this->pullEveryChannels();
    $this->checkCreatedEntities();

    // Test link_internal_content_importer plugin.
    // Need to remove all imported content prior to that.
    $this->resetImportedContent();
    // Import only one content.
    $selected_entities = [
      'es_test_link_internal',
    ];
    $this->importSelectedEntities($selected_entities);

    // Check that only the imported content had been pulled.
    $linking_entity = $this->loadEntity('node', 'es_test_link_internal');
    $this->assertNotNull($linking_entity, 'The linking node has been created.');
    $linked_entity = $this->loadEntity('node', 'es_test');
    $this->assertNull($linked_entity, 'The linked node has not been created.');

    // Enable the plugin.
    $this->mergePluginsToImportConfig([
      'link_internal_content_importer' => [
        'max_recursion_depth' => -1,
        'weights' => [
          'prepare_importable_entity_data' => 20,
        ],
      ],
    ]);

    $this->resetImportedContent();

    // Import only one content.
    $selected_entities = [
      'es_test_link_internal',
    ];
    $this->importSelectedEntities($selected_entities);

    // Check that both contents had been imported.
    $linking_entity = $this->loadEntity('node', 'es_test_link_internal');
    $this->assertNotNull($linking_entity, 'The linking node has been created.');
    $linked_entity = $this->loadEntity('node', 'es_test');
    $this->assertNotNull($linked_entity, 'The linked node has been created.');
  }

  /**
   * {@inheritdoc}
   */
  protected function populateRequestService() {
    parent::populateRequestService();

    // Needs to make the requests when only the linking content will be
    // required.
    $selected_entities = [
      'es_test_link_internal',
    ];
    $prepared_url = $this->prepareUrlFilteredOnUuids($selected_entities, 'node_es_test_en');
    $this->discoverJsonApiEndpoints($prepared_url);

    // Prepare the request on the linked content.
    $route_name = sprintf('jsonapi.%s--%s.individual', 'node', 'es_test');
    $linked_content_url = Url::fromRoute($route_name, [
      'entity' => 'es_test',
    ])
      ->setOption('language', $this->container->get('language_manager')->getLanguage('en'))
      ->setOption('absolute', TRUE);
    $this->discoverJsonApiEndpoints($linked_content_url->toString());
  }

  /**
   * Helper function.
   *
   * After the value_callback is re-evaluated, the nid will be changed. So need
   * a specific checker_callback.
   *
   * After recreation, the node wih UUID es_test will have nid 6.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity.
   * @param string $field_name
   *   The field to retrieve the value.
   *
   * @return array
   *   The expected value after import.
   */
  protected function getExpectedInternalLinkValue(ContentEntityInterface $entity, string $field_name) {
    return [
      [
        'uri' => 'entity:node/6',
      ],
    ];
  }

}
