<?php

namespace Drupal\Tests\entity\Unit\QueryAccess;

use Drupal\entity\QueryAccess\Condition;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\entity\QueryAccess\Condition
 * @group entity
 */
class ConditionTest extends UnitTestCase {

  /**
   * ::covers __construct.
   */
  public function testInvalidOperator() {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Unrecognized operator "INVALID".');
    new Condition('uid', '1', 'INVALID');
  }

  /**
   * ::covers getField
   * ::covers getValue
   * ::covers getOperator
   * ::covers __toString.
   */
  public function testGetters() {
    $condition = new Condition('uid', '2');
    $this->assertEquals('uid', $condition->getField());
    $this->assertEquals('2', $condition->getValue());
    $this->assertEquals('=', $condition->getOperator());
    $this->assertEquals("uid = '2'", $condition->__toString());

    $condition = new Condition('type', ['article', 'page']);
    $this->assertEquals('type', $condition->getField());
    $this->assertEquals(['article', 'page'], $condition->getValue());
    $this->assertEquals('IN', $condition->getOperator());
    $this->assertEquals("type IN ['article', 'page']", $condition->__toString());

    $condition = new Condition('title', NULL, 'IS NULL');
    $this->assertEquals('title', $condition->getField());
    $this->assertEquals(NULL, $condition->getValue());
    $this->assertEquals('IS NULL', $condition->getOperator());
    $this->assertEquals("title IS NULL", $condition->__toString());
  }

}
