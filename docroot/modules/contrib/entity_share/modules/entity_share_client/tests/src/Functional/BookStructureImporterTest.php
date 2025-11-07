<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_share_client\Functional;

use Drupal\book\BookManagerInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;

/**
 * Functional test class to test import plugin 'Book structure'.
 *
 * @group entity_share
 * @group entity_share_client
 */
class BookStructureImporterTest extends EntityShareClientFunctionalTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'json_api_book',
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
   * The book manager service.
   *
   * @var \Drupal\book\BookManagerInterface
   */
  protected BookManagerInterface $bookManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->bookManager = $this->container->get('book.manager');

    $this->postSetupFixture();
  }

  /**
   * {@inheritdoc}
   */
  protected function getImportConfigProcessorSettings() {
    $processors = parent::getImportConfigProcessorSettings();
    $processors['book_structure_importer'] = [
      'weights' => [
        'prepare_importable_entity_data' => 20,
        'post_entity_save' => 20,
      ],
      'max_recursion_depth' => -1,
    ];

    return $processors;
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareContent() {
    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = $this->entityTypeManager->getStorage('node');

    $book_root = $node_storage->create([
      'uuid' => 'book_root',
      'type' => static::$entityBundleId,
      'title' => $this->randomString(),
      'status' => NodeInterface::PUBLISHED,
    ]);
    $book_root->book['bid'] = 'new';
    $book_root->book['weight'] = '0';
    $book_root->save();

    $book_child_1 = $node_storage->create([
      'uuid' => 'book_child_1',
      'type' => static::$entityBundleId,
      'title' => $this->randomString(),
      'status' => NodeInterface::PUBLISHED,
    ]);
    $book_child_1->book['bid'] = $book_root->id();
    $book_child_1->book['pid'] = $book_root->id();
    $book_child_1->book['weight'] = '0';
    $book_child_1->save();

    $book_child_2 = $node_storage->create([
      'uuid' => 'book_child_2',
      'type' => static::$entityBundleId,
      'title' => $this->randomString(),
      'status' => NodeInterface::PUBLISHED,
    ]);
    $book_child_2->book['bid'] = $book_root->id();
    $book_child_2->book['pid'] = $book_child_1->id();
    $book_child_2->book['weight'] = '0';
    $book_child_2->save();

    $this->entities = [
      'node' => [
        'book_root' => $book_root,
        'book_child_1' => $book_child_1,
        'book_child_2' => $book_child_2,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntitiesDataArray() {
    return [];
  }

  /**
   * Test the 'Book structure' Import Processor plugin.
   */
  public function testBookStructureImportedPlugin() {
    // Select only the top-level referencing entity.
    $selected_entities = [
      'book_child_2',
    ];
    $this->importSelectedEntities($selected_entities);

    $book_root = $this->loadEntity('node', 'book_root');
    $this->assertNotNull($book_root);
    $this->assertEquals([
      'nid' => $book_root->id(),
      'bid' => $book_root->id(),
      'pid' => '0',
      'has_children' => '1',
      'weight' => '0',
      'depth' => '1',
      'p1' => $book_root->id(),
      'p2' => '0',
      'p3' => '0',
      'p4' => '0',
      'p5' => '0',
      'p6' => '0',
      'p7' => '0',
      'p8' => '0',
      'p9' => '0',
      'access' => TRUE,
      'title' => $book_root->label(),
      'options' => [],
    ], $this->bookManager->loadBookLink($book_root->id()));

    $book_child_1 = $this->loadEntity('node', 'book_child_1');
    $this->assertNotNull($book_child_1);
    $this->assertEquals([
      'nid' => $book_child_1->id(),
      'bid' => $book_root->id(),
      'pid' => $book_root->id(),
      'has_children' => '1',
      'weight' => '0',
      'depth' => '2',
      'p1' => $book_root->id(),
      'p2' => $book_child_1->id(),
      'p3' => '0',
      'p4' => '0',
      'p5' => '0',
      'p6' => '0',
      'p7' => '0',
      'p8' => '0',
      'p9' => '0',
      'access' => TRUE,
      'title' => $book_child_1->label(),
      'options' => [],
    ], $this->bookManager->loadBookLink($book_child_1->id()));

    $book_child_2 = $this->loadEntity('node', 'book_child_2');
    $this->assertNotNull($book_child_2);
    $this->assertEquals([
      'nid' => $book_child_2->id(),
      'bid' => $book_root->id(),
      'pid' => $book_child_1->id(),
      'has_children' => '0',
      'weight' => '0',
      'depth' => '3',
      'p1' => $book_root->id(),
      'p2' => $book_child_1->id(),
      'p3' => $book_child_2->id(),
      'p4' => '0',
      'p5' => '0',
      'p6' => '0',
      'p7' => '0',
      'p8' => '0',
      'p9' => '0',
      'access' => TRUE,
      'title' => $book_child_2->label(),
      'options' => [],
    ], $this->bookManager->loadBookLink($book_child_2->id()));
  }

  /**
   * {@inheritdoc}
   */
  protected function populateRequestService() {
    parent::populateRequestService();

    // Needs to make the requests when only the referencing content will be
    // required.
    $selected_entities = [
      'book_child_2',
    ];
    $prepared_url = $this->prepareUrlFilteredOnUuids($selected_entities, 'node_es_test_en');
    $this->discoverJsonApiEndpoints($prepared_url);

    $uuids = [
      'book_root',
      'book_child_1',
    ];
    $route_name = sprintf('jsonapi.%s--%s.individual', 'node', 'es_test');
    foreach ($uuids as $uuid) {
      $url = Url::fromRoute($route_name, [
        'entity' => $uuid,
      ])
        ->setOption('language', $this->container->get('language_manager')->getLanguage('en'))
        ->setOption('absolute', TRUE);
      $this->remoteManager->jsonApiRequest($this->remote, 'GET', $url->toString());
    }
  }

}
