<?php

namespace Drupal\Tests\commerce_price\Unit\Resolver;

use Drupal\commerce_price\Entity\CurrencyInterface;
use Drupal\commerce_price\Resolver\CurrencyResolverInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\commerce_price\Resolver\ChainCurrencyResolver;

/**
 * @coversDefaultClass \Drupal\commerce_price\Resolver\ChainCurrencyResolver
 * @group commerce_price
 */
class ChainCurrencyResolverTest extends UnitTestCase {

  /**
   * Tests the resolver and priority.
   *
   * ::covers resolve.
   */
  public function testResolver() {
    $mock_builder = $this->getMockBuilder(CurrencyResolverInterface::class)
      ->disableOriginalConstructor();

    $first_resolver = $mock_builder->getMock();
    $first_resolver->expects($this->once())
      ->method('resolve');

    $test_currency = $this->createMock(CurrencyInterface::class);
    $second_resolver = $mock_builder->getMock();
    $second_resolver->expects($this->once())
      ->method('resolve')
      ->willReturn($test_currency);

    $third_resolver = $mock_builder->getMock();
    $third_resolver->expects($this->never())
      ->method('resolve');

    $resolver = new ChainCurrencyResolver([
      $first_resolver,
      $second_resolver,
      $third_resolver,
    ]);

    $result = $resolver->resolve();
    $this->assertEquals($test_currency, $result);
  }

}
