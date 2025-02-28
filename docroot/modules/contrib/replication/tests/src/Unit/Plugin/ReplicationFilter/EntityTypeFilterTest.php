<?php

namespace Drupal\Tests\replication\Unit\Plugin\ReplicationFilter;

use Drupal\Core\Entity\EntityInterface;
use Drupal\replication\Plugin\ReplicationFilter\EntityTypeFilter;
use Drupal\Tests\UnitTestCase;

/**
 * Tests that the entity type filter parses parameters correctly.
 *
 * @group replication
 */
class EntityTypeFilterTest extends UnitTestCase {

  /**
   * Test filtering entity types.
   *
   * @param string $types
   *   The types filter parameter.
   * @param string $expected
   *   The expected return value from the filter method.
   *
   * @dataProvider filterTestProvider
   */
  public function testFilter($types, $expected) {
    // Use a mock builder for the class under test to eliminate the need to
    // mock all the dependencies. The method under test uses the $configuration
    // set by the constructor, but is retrieved via a get method we can stub.
    $filter = $this->getMockBuilder(EntityTypeFilter::class)
      ->disableOriginalConstructor()
      ->setMethods(['getConfiguration'])
      ->getMock();
    $configuration = [
      'types' => $types,
    ];
    $filter->method('getConfiguration')
      ->willReturn($configuration);
    $entity = $this->getMock(EntityInterface::class);
    $entity->method('getEntityTypeId')
      ->willReturn('node');
    $entity->method('bundle')
      ->willReturn('article');

    $value = $filter->filter($entity);

    $this->assertEquals($expected, $value);
  }

  /**
   * Provide test cases for the "entity_type_id" and "bundle" parameters.
   */
  public function filterTestProvider() {
    return [
      // Test singular parameter values.
      [['node'], TRUE],
      [['node.article'], TRUE],
      [['node.page'], FALSE],
      // Test multiple parameter values.
      [['block', 'node'], TRUE],
      [['node.article', 'node.page'], TRUE],
      [['node.page', 'node.article'], TRUE],
      [['node.test', 'node.page'], FALSE],
      // Test bad data that might be entered into the parameters:
      [[''], FALSE],
      [[','], FALSE],
      [[',node'], FALSE],
      [['..'], FALSE],
      [[NULL], FALSE],
      [[FALSE], FALSE],
      [[TRUE], FALSE],
      [[0], FALSE],
    ];
  }

}
