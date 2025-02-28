<?php

namespace Drupal\Tests\entity\Kernel\QueryAccess;

use Drupal\entity\QueryAccess\Condition;
use Drupal\entity\QueryAccess\ConditionGroup;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the condition group class.
 *
 * ConditionGroup uses \Drupal\Core\Cache\Cache internally, which makes it
 * impossible to use a unit test (due to Cache accessing the global container).
 *
 * @coversDefaultClass \Drupal\entity\QueryAccess\ConditionGroup
 * @group entity
 */
class ConditionGroupTest extends KernelTestBase {

  /**
   * ::covers getConjunction
   * ::covers addCondition
   * ::covers getConditions
   * ::covers count.
   */
  public function testGetters() {
    $condition_group = new ConditionGroup();
    $condition_group->addCondition('uid', '2');
    $this->assertEquals('AND', $condition_group->getConjunction());
    $expected_conditions = [
      new Condition('uid', '2'),
    ];
    $this->assertEquals($expected_conditions, $condition_group->getConditions());
    $this->assertEquals(1, $condition_group->count());
    $this->assertEquals("uid = '2'", $condition_group->__toString());

    $condition_group = new ConditionGroup('OR');
    $condition_group->addCondition('type', ['article', 'page']);
    $condition_group->addCondition('status', '1', '<>');
    $this->assertEquals('OR', $condition_group->getConjunction());
    $expected_conditions = [
      new Condition('type', ['article', 'page']),
      new Condition('status', '1', '<>'),
    ];
    $expected_lines = [
      "(",
      "  type IN ['article', 'page']",
      "    OR",
      "  status <> '1'",
      ")",
    ];
    $this->assertEquals($expected_conditions, $condition_group->getConditions());
    $this->assertEquals(2, $condition_group->count());
    $this->assertEquals(implode("\n", $expected_lines), $condition_group->__toString());

    // Nested condition group with a single condition.
    $condition_group = new ConditionGroup();
    $condition_group->addCondition('type', ['article', 'page']);
    $condition_group->addCondition((new ConditionGroup('AND'))
      ->addCondition('status', '1')
    );
    $expected_conditions = [
      new Condition('type', ['article', 'page']),
      new Condition('status', '1'),
    ];
    $expected_lines = [
      "(",
      "  type IN ['article', 'page']",
      "    AND",
      "  status = '1'",
      ")",
    ];
    $this->assertEquals($expected_conditions, $condition_group->getConditions());
    $this->assertEquals('AND', $condition_group->getConjunction());
    $this->assertEquals(2, $condition_group->count());
    $this->assertEquals(implode("\n", $expected_lines), $condition_group->__toString());

    // Nested condition group with multiple conditions.
    $condition_group = new ConditionGroup();
    $condition_group->addCondition('type', ['article', 'page']);
    $nested_condition_group = new ConditionGroup('OR');
    $nested_condition_group->addCondition('uid', '1');
    $nested_condition_group->addCondition('status', '1');
    $condition_group->addCondition($nested_condition_group);
    $expected_conditions = [
      new Condition('type', ['article', 'page']),
      $nested_condition_group,
    ];
    $expected_lines = [
      "(",
      "  type IN ['article', 'page']",
      "    AND",
      "  (",
      "    uid = '1'",
      "      OR",
      "    status = '1'",
      "  )",
      ")",
    ];
    $this->assertEquals($expected_conditions, $condition_group->getConditions());
    $this->assertEquals('AND', $condition_group->getConjunction());
    $this->assertEquals(2, $condition_group->count());
    $this->assertEquals(implode("\n", $expected_lines), $condition_group->__toString());
  }

}
