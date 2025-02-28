<?php

namespace Drupal\Tests\commerce_price\Unit;

use CommerceGuys\Intl\Exception\UnknownCurrencyException;
use Drupal\commerce_price\Repository\CurrencyRepository;
use Drupal\commerce_price\MinorUnitsConverter;
use Drupal\commerce_price\Price;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the MinorUnitsConverter class.
 *
 * @coversDefaultClass \Drupal\commerce_price\MinorUnitsConverter
 * @group commerce
 */
class MinorUnitsConverterTest extends UnitTestCase {

  /**
   * The minor units converter.
   *
   * @var \Drupal\commerce_price\MinorUnitsConverterInterface
   */
  protected $minorUnitsConverter;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $storage = $this->prophesize(EntityStorageInterface::class);
    $entity_type_manager = $this->prophesize(EntityTypeManagerInterface::class);
    $entity_type_manager->getStorage('commerce_currency')->willReturn($storage->reveal());
    $this->minorUnitsConverter = new MinorUnitsConverter(new CurrencyRepository($entity_type_manager->reveal()));
  }

  /**
   * Tests converting a price with an unknown currency.
   *
   * @covers ::toMinorUnits
   */
  public function testUnknownCurrency() {
    $this->expectException(UnknownCurrencyException::class);
    $this->minorUnitsConverter->toMinorUnits(new Price('10', 'XYZ'));
  }

  /**
   * Tests if price can be converted to minor unit and then back to decimal.
   *
   * @dataProvider currencyConversionData
   */
  public function testConversion(Price $price, $expected_amount) {
    $amount = $this->minorUnitsConverter->toMinorUnits($price);
    $this->assertEquals($expected_amount, $amount);
    $this->assertTrue($this->minorUnitsConverter->fromMinorUnits($amount, $price->getCurrencyCode())->equals($price));
  }

  /**
   * Data provider for ::testConversion.
   *
   * @return array
   *   The test data.
   */
  public function currencyConversionData() {
    return [
      [new Price(10, 'EUR'), 1000],
      [new Price(1.23, 'USD'), 123],
      [new Price(0.99, 'NOK'), 99],
      [new Price(99, 'JPY'), 99],
      [new Price(99, 'KWD'), 99000],
    ];
  }

}
