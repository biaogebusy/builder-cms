<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_share_client\Functional;

use Drupal\Core\Url;
use Drupal\node\NodeInterface;

/**
 * Functional test class for infinite loop and embedded entities.
 *
 * @group entity_share
 * @group entity_share_client
 */
class InfiniteLoopEmbeddedEntityTest extends InfiniteLoopTestBase {

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
        'field_es_test_text_formatted_lon' => [
          'fieldName' => 'field_es_test_text_formatted_lon',
          'publicName' => 'field_es_test_text_formatted_lon',
          'enhancer' => [
            'id' => 'entity_share_embedded_entities',
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
    $processors['embedded_entity_importer'] = [
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

    $node_2_rte = <<<EOT
<p>Test Linkit</p>

<p><a data-entity-substitution="canonical" data-entity-type="node" data-entity-uuid="es_test_content_reference_one" href="/node/666">Test Linkit</a></p>
EOT;

    $node_2 = $node_storage->create([
      'uuid' => 'es_test_content_reference_two',
      'type' => static::$entityBundleId,
      'title' => $this->randomString(),
      'status' => NodeInterface::PUBLISHED,
      'field_es_test_text_formatted_lon' => [
        [
          'value' => $node_2_rte,
          'format' => 'full_html',
        ],
      ],
    ]);
    $node_2->save();

    $node_1_rte = <<<EOT
<p>Test Entity Embed</p>

<drupal-entity data-align="right" data-caption="test" data-embed-button="node" data-entity-embed-display="view_mode:node.teaser" data-entity-type="node" data-entity-uuid="es_test_content_reference_two" data-langcode="en"></drupal-entity>
EOT;

    $node_1->set('field_es_test_text_formatted_lon', [
      [
        'value' => $node_1_rte,
        'format' => 'full_html',
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
