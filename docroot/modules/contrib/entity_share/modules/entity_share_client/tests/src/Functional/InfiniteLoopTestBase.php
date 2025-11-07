<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_share_client\Functional;

use Drupal\node\NodeInterface;

/**
 * Functional test base class for infinite loop.
 */
abstract class InfiniteLoopTestBase extends EntityShareClientFunctionalTestBase {

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
  protected function getEntitiesDataArray() {
    return [];
  }

  /**
   * Test that a referenced entity is pulled even if not selected.
   *
   * In a scenario of infinite loop.
   */
  public function testInfiniteLoop() {
    // Select only the first referencing entity.
    $selected_entities = [
      'es_test_content_reference_one',
    ];
    $this->infiniteLoopTestHelper($selected_entities);

    // Reset before starting again.
    $this->resetImportedContent();

    // Select only the second referencing entity.
    $selected_entities = [
      'es_test_content_reference_two',
    ];
    $this->infiniteLoopTestHelper($selected_entities);
  }

  /**
   * {@inheritdoc}
   */
  protected function populateRequestService() {
    parent::populateRequestService();

    // Needs to make the requests when only one referencing content will be
    // required.
    $selected_entities = [
      'es_test_content_reference_one',
    ];
    $prepared_url = $this->prepareUrlFilteredOnUuids($selected_entities, 'node_es_test_en');
    $this->discoverJsonApiEndpoints($prepared_url);

    // Needs to make the requests when only one referencing content will be
    // required.
    $selected_entities = [
      'es_test_content_reference_two',
    ];
    $prepared_url = $this->prepareUrlFilteredOnUuids($selected_entities, 'node_es_test_en');
    $this->discoverJsonApiEndpoints($prepared_url);
  }

  /**
   * Helper function.
   *
   * @param array $selected_entities
   *   The selected entities to pull.
   */
  protected function infiniteLoopTestHelper(array $selected_entities) {
    $this->importSelectedEntities($selected_entities);

    // Check that both entities had been created. If the process ends the
    // infinite loop has been avoided.
    $uuids = [
      'es_test_content_reference_one',
      'es_test_content_reference_two',
    ];
    foreach ($uuids as $uuid) {
      $node = $this->loadEntity('node', $uuid);
      $this->assertTrue($node instanceof NodeInterface, 'The node with the uuid ' . $uuid . ' has been recreated.');
    }
  }

}
