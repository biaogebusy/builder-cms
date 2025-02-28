<?php

namespace Drupal\Tests\replication\Kernel\Plugin\ReplicationFilter;

use Drupal\KernelTests\KernelTestBase;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\node\Entity\NodeType;

/**
 * Tests that the published filter parses parameters correctly.
 *
 * @group replication
 */
class PublishedFilterTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'block',
    'block_content',
    'key_value',
    'multiversion',
    'node',
    'replication',
    'serialization',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $node_type = NodeType::create([
      'type' => 'test',
      'label' => 'Test',
    ]);
    $node_type->save();

    $bundle_type = BlockContentType::create([
      'id' => 'test',
      'label' => 'Test',
    ]);
    $bundle_type->save();
  }

  /**
   * Test filtering published entities.
   *
   * @param bool $include_unpublishable_entities
   *   The plugin configuration value for including unpublishable entities.
   * @param string $entity_type_id
   *   The entity type id of the entity to create for testing.
   * @param array $entity_values
   *   The values with which to create the entity.
   * @param bool $expected
   *   The expected return value from the filter function.
   *
   * @dataProvider filterTestProvider
   */
  public function testFilter($include_unpublishable_entities, $entity_type_id, $entity_values, $expected) {
    /** @var \Drupal\replication\Plugin\ReplicationFilterManagerInterface $filter_manager */
    $filter_manager = $this->container->get('plugin.manager.replication_filter');
    $configuration = [
      'include_unpublishable_entities' => $include_unpublishable_entities,
    ];
    $filter = $filter_manager->createInstance('published', $configuration);
    $entity = $this->container
        ->get('entity_type.manager')
        ->getStorage($entity_type_id)
        ->create($entity_values);

    $value = $filter->filter($entity);

    $this->assertSame($expected, $value);
  }

  /**
   * Test default configuration for published filter.
   */
  public function testDefaultConfig() {
    /** @var \Drupal\replication\Plugin\ReplicationFilterManagerInterface $filter_manager */
    $filter_manager = $this->container->get('plugin.manager.replication_filter');
    $filter = $filter_manager->createInstance('published');
    $entity = $this->container
        ->get('entity_type.manager')
        ->getStorage('block_content')
        ->create(['type' => 'test']);

    $value = $filter->filter($entity);

    // By default entities with no status entity key are filtered out.
    $this->assertFalse($value);
  }

  /**
   * Provide test cases for the "entity_type_id" and "bundle" parameters.
   *
   * Note: the only node bundle is 'test' and the only block content bundle is
   * 'test'.
   *
   * @return array
   *   An array of arrays, each array being the arguments to filterTest().
   */
  public function filterTestProvider() {
    $published_node = [
      'type' => 'test',
      'status' => TRUE,
    ];

    $unpublished_node = [
      'type' => 'test',
      'status' => FALSE,
    ];

    $block = [
      'type' => 'test',
    ];

    return [
      [FALSE, 'node', $published_node, TRUE],
      [FALSE, 'node', $unpublished_node, FALSE],
      [TRUE, 'block_content', $block, TRUE],
      [FALSE, 'block_content', $block, FALSE],
    ];
  }

}
