<?php

namespace Drupal\Tests\commerce\Unit;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Plugin\Commerce\Condition\CurrentUserRole;
use Prophecy\Prophecy\ObjectProphecy;

/**
 * @coversDefaultClass \Drupal\commerce_order\Plugin\Commerce\Condition\CurrentUserRole
 * @group commerce
 */
class CurrentUserRoleConditionTest extends UnitTestCase {

  /**
   * The current user.
   */
  protected AccountProxyInterface | ObjectProphecy $current_user;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->current_user = $this->prophesize(AccountProxyInterface::class);
  }

  /**
   * ::covers evaluate.
   */
  public function testEvaluate() {
    $order = $this->prophesize(OrderInterface::class);
    $order->getEntityTypeId()->willReturn('commerce_order');
    $order = $order->reveal();

    // Check "any" condition.
    $condition = new CurrentUserRole(
      [
        'matching_strategy' => 'any',
        'roles' => [
          'test_role_1',
          'test_role_2',
        ],
      ],
      'current_user_role',
      ['entity_type' => 'commerce_order'],
      $this->current_user->reveal()
    );
    $this->current_user->getRoles()->willReturn(['test_role_1']);
    $this->assertTrue($condition->evaluate($order));

    $this->current_user->getRoles()->willReturn(['test_role_2']);
    $this->assertTrue($condition->evaluate($order));

    $this->current_user->getRoles()->willReturn(['test_role_3']);
    $this->assertFalse($condition->evaluate($order));

    // Check "all" condition.
    $condition = new CurrentUserRole(
      [
        'matching_strategy' => 'all',
        'roles' => [
          'test_role_1',
          'test_role_2',
        ],
      ],
      'current_user_role',
      ['entity_type' => 'commerce_order'],
      $this->current_user->reveal()
    );

    $this->current_user->getRoles()->willReturn(['test_role_1', 'test_role_2']);
    $this->assertTrue($condition->evaluate($order));

    $this->current_user->getRoles()->willReturn(['test_role_2', 'test_role_3']);
    $this->assertFalse($condition->evaluate($order));

    $this->current_user->getRoles()->willReturn(['test_role_3', 'test_role_1']);
    $this->assertFalse($condition->evaluate($order));

    // Check "none" condition.
    $condition = new CurrentUserRole(
      [
        'matching_strategy' => 'none',
        'roles' => [
          'test_role_1',
        ],
      ],
      'current_user_role',
      ['entity_type' => 'commerce_order'],
      $this->current_user->reveal()
    );

    $this->current_user->getRoles()->willReturn(['test_role_1']);
    $this->assertFalse($condition->evaluate($order));

    $this->current_user->getRoles()->willReturn(['test_role_2']);
    $this->assertTrue($condition->evaluate($order));

    $this->current_user->getRoles()->willReturn(['test_role_3']);
    $this->assertTrue($condition->evaluate($order));
  }

}
