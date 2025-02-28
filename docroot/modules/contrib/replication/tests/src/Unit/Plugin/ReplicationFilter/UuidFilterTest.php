<?php

namespace Drupal\Tests\replication\Unit\Plugin\ReplicationFilter;

use Drupal\Core\Entity\EntityInterface;
use Drupal\replication\Plugin\ReplicationFilter\UuidFilter;
use Drupal\Tests\UnitTestCase;

/**
 * Tests that the uuid filter parses parameters correctly.
 *
 * @group replication
 */
class UuidFilterTest extends UnitTestCase {

  /**
   * Test filtering UUIDs.
   *
   * @dataProvider filterTestProvider
   */
  public function testFilter($uuid, $parameter_value, $expected) {
    // Use a mock builder for the class under test to eliminate the need to
    // mock all the dependencies. The method under test uses the $configuration
    // set by the constructor, but is retrieved via a get method we can stub.
    $filter = $this->getMockBuilder(UuidFilter::class)
      ->disableOriginalConstructor()
      ->setMethods(['getConfiguration'])
      ->getMock();
    $configuration = [
      'uuids' => $parameter_value,
    ];
    $filter->method('getConfiguration')
      ->willReturn($configuration);
    $entity = $this->getMock(EntityInterface::class);
    $entity->method('uuid')
      ->willReturn($uuid);

    $value = $filter->filter($entity);

    $this->assertEquals($expected, $value);
  }

  /**
   * Provide test cases for the "uuids" parameter.
   */
  public function filterTestProvider() {
    return [
      // Test singular parameter values.
      ['123', ['123'], TRUE],
      ['123', ['456'], FALSE],
      // Test multiple parameter values.
      ['123', ['123', '456'], TRUE],
      ['123', ['456', '789'], FALSE],
      // Test bad data that might be entered into the parameters:
      ['123', ['123 '], FALSE],
      ['123', [0], FALSE],
      ['123', [NULL], FALSE],
    ];
  }

  /**
   * Test filtering UUIDs with no parameter.
   */
  public function testFilterNoParameter() {
    // Use a mock builder for the class under test to eliminate the need to
    // mock all the dependencies. This is OK since the method under test is a
    // pure function, i.e. does not use the state createdy by the constructor.
    $filter = $this->getMockBuilder(UuidFilter::class)
      ->disableOriginalConstructor()
      ->setMethods(NULL)
      ->getMock();
    $entity = $this->getMock(EntityInterface::class);
    $entity->method('uuid')
      ->willReturn('123');

    $value = $filter->filter($entity, []);

    $this->assertEquals(FALSE, $value);
  }

}
