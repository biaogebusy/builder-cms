<?php

namespace Drupal\Tests\commerce_order\Unit;

use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\commerce\Context;
use Drupal\commerce_order\AvailabilityCheckerInterface;
use Drupal\commerce_order\AvailabilityManager;
use Drupal\commerce_order\AvailabilityResult;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_store\Entity\StoreInterface;

/**
 * @coversDefaultClass \Drupal\commerce_order\AvailabilityManager
 * @group commerce
 */
class AvailabilityManagerTest extends UnitTestCase {

  /**
   * The availability manager.
   *
   * @var \Drupal\commerce_order\AvailabilityManager
   */
  protected $availabilityManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->availabilityManager = new AvailabilityManager();
  }

  /**
   * ::covers addChecker
   * ::covers check.
   */
  public function testCheck() {
    $mock_builder = $this->getMockBuilder(AvailabilityCheckerInterface::class)
      ->disableOriginalConstructor();
    $order_item = $this->createMock(OrderItemInterface::class);

    $first_checker = $mock_builder->getMock();
    $first_checker->expects($this->any())
      ->method('applies')
      ->with($order_item)
      ->willReturn(TRUE);
    $first_checker->expects($this->any())
      ->method('check')
      ->with($order_item)
      ->willReturn(NULL);

    $second_checker = $mock_builder->getMock();
    $second_checker->expects($this->any())
      ->method('applies')
      ->with($order_item)
      ->willReturn(TRUE);
    $second_checker->expects($this->any())
      ->method('check')
      ->with($order_item)
      ->willReturn(AvailabilityResult::neutral());

    $third_checker = $mock_builder->getMock();
    $third_checker->expects($this->any())
      ->method('applies')
      ->with($order_item)
      ->willReturn(FALSE);
    $third_checker->expects($this->any())
      ->method('check')
      ->with($order_item)
      ->willReturn(AvailabilityResult::unavailable());

    $fourth_checker = $mock_builder->getMock();
    $fourth_checker->expects($this->any())
      ->method('applies')
      ->with($order_item)
      ->willReturn(TRUE);
    $fourth_checker->expects($this->any())
      ->method('check')
      ->with($order_item)
      ->willReturn(AvailabilityResult::unavailable('The product is not available'));

    $user = $this->createMock(AccountInterface::class);
    $store = $this->createMock(StoreInterface::class);
    $context = new Context($user, $store);

    // Test the new availability checkers first.
    $this->availabilityManager->addChecker($first_checker);
    $result = $this->availabilityManager->check($order_item, $context);
    $this->assertEquals(AvailabilityResult::neutral(), $result, 'The checked order item is available when a checker returns NULL.');

    $this->availabilityManager->addChecker($second_checker);
    $result = $this->availabilityManager->check($order_item, $context);
    $this->assertEquals(AvailabilityResult::neutral(), $result, 'The checked order item is available when a checker returns a "neutral" availability result.');

    $this->availabilityManager->addChecker($third_checker);
    $result = $this->availabilityManager->check($order_item, $context);
    $this->assertEquals(AvailabilityResult::neutral(), $result, 'The checked order item is available when a checker that would return an "unavailable" availability result does not apply.');

    $this->availabilityManager->addChecker($fourth_checker);
    $result = $this->availabilityManager->check($order_item, $context);
    $this->assertEquals(AvailabilityResult::unavailable('The product is not available'), $result, 'The checked order item is not available when a checker returning an"unavailable" availability result applies.');
  }

}
