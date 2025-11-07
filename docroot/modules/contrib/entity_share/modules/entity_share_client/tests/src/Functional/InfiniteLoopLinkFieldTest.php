<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_share_client\Functional;

use Drupal\Core\Url;
use Drupal\node\NodeInterface;

/**
 * Functional test class for infinite loop in link field.
 *
 * @group entity_share
 * @group entity_share_client
 */
class InfiniteLoopLinkFieldTest extends InfiniteLoopTestBase {

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
  protected function getImportConfigProcessorSettings() {
    $processors = parent::getImportConfigProcessorSettings();
    $processors['link_internal_content_importer'] = [
      'max_recursion_depth' => -1,
      'weights' => [
        'prepare_importable_entity_data' => 20,
      ],
    ];
    return $processors;
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareContent() {
    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = $this->entityTypeManager->getStorage('node');

    // Create two nodes referencing each other.
    $node_1 = $node_storage->create([
      'uuid' => 'es_test_content_reference_one',
      'type' => static::$entityBundleId,
      'title' => $this->randomString(),
      'status' => NodeInterface::PUBLISHED,
    ]);
    $node_1->save();

    $node_2 = $node_storage->create([
      'uuid' => 'es_test_content_reference_two',
      'type' => static::$entityBundleId,
      'title' => $this->randomString(),
      'status' => NodeInterface::PUBLISHED,
      'field_es_test_link' => [
        [
          'uri' => 'entity:node/' . $node_1->id(),
          'title' => $this->randomString(),
        ],
      ],
    ]);
    $node_2->save();

    $node_1->set('field_es_test_link', [
      [
        'uri' => 'entity:node/' . $node_2->id(),
        'title' => $this->randomString(),
      ],
    ]);
    $node_1->save();

    $this->entities = [
      'node' => [
        'es_test_content_reference_one' => $node_1,
        'es_test_content_reference_two' => $node_2,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function populateRequestService() {
    parent::populateRequestService();

    // Prepare the request on the linked content.
    $route_name = sprintf('jsonapi.%s--%s.individual', 'node', 'es_test');
    $linked_content_url = Url::fromRoute($route_name, [
      'entity' => 'es_test_content_reference_one',
    ])
      ->setOption('language', $this->container->get('language_manager')->getLanguage('en'))
      ->setOption('absolute', TRUE);
    $this->discoverJsonApiEndpoints($linked_content_url->toString());

    // Prepare the request on the linked content.
    $route_name = sprintf('jsonapi.%s--%s.individual', 'node', 'es_test');
    $linked_content_url = Url::fromRoute($route_name, [
      'entity' => 'es_test_content_reference_two',
    ])
      ->setOption('language', $this->container->get('language_manager')->getLanguage('en'))
      ->setOption('absolute', TRUE);
    $this->discoverJsonApiEndpoints($linked_content_url->toString());
  }

}
