<?php

namespace Drupal\Tests\commerce_price\Kernel;

use CommerceGuys\Intl\Currency\Currency;
use CommerceGuys\Intl\Exception\UnknownCurrencyException;
use Drupal\Tests\commerce\Kernel\CommerceKernelTestBase;

/**
 * Tests the currency repository.
 *
 * @coversDefaultClass \Drupal\commerce_price\Repository\CurrencyRepository
 * @group commerce
 */
class CurrencyRepositoryTest extends CommerceKernelTestBase {

  /**
   * The currency repository.
   *
   * @var \Drupal\commerce_price\Repository\CurrencyRepository
   */
  protected $currencyRepository;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // The parent has already imported USD.
    $currency_importer = $this->container->get('commerce_price.currency_importer');
    $currency_importer->import('EUR');

    $this->currencyRepository = $this->container->get('commerce_price.currency_repository');
  }

  /**
   * @covers ::get
   */
  public function testUnknownGet() {
    $this->expectException(UnknownCurrencyException::class);
    $this->currencyRepository->get('RSD');
  }

  /**
   * @covers ::get
   */
  public function testGet() {
    $expected_eur = new Currency([
      'currency_code' => 'EUR',
      'name' => 'Euro',
      'numeric_code' => '978',
      'symbol' => '€',
      'fraction_digits' => 2,
      'locale' => 'en',
    ]);
    $expected_usd = new Currency([
      'currency_code' => 'USD',
      'name' => 'US Dollar',
      'numeric_code' => '840',
      'symbol' => '$',
      'fraction_digits' => 2,
      'locale' => 'en',
    ]);

    $this->assertEquals($expected_eur, $this->currencyRepository->get('EUR'));
    $this->assertEquals($expected_usd, $this->currencyRepository->get('USD'));
  }

  /**
   * @covers ::getAll
   */
  public function testGetAll() {
    $expected = [
      'EUR' => $this->currencyRepository->get('EUR'),
      'USD' => $this->currencyRepository->get('USD'),
    ];
    $this->assertEquals($expected, $this->currencyRepository->getAll());
  }

  /**
   * @covers ::getList
   */
  public function testGetList() {
    $expected_list = [
      'EUR' => 'Euro',
      'USD' => 'US Dollar',
    ];
    $this->assertEquals($expected_list, $this->currencyRepository->getList());
  }

  /**
   * Tests getting the currency default fraction digits.
   *
   * @param string $currency_code
   *   The currency code.
   * @param int $expected_fraction_digits
   *   The expected fraction digits.
   *
   * @covers ::getDefaultFractionDigits
   * @dataProvider fractionDigitsData
   */
  public function testGetDefaultFractionDigits(string $currency_code, int $expected_fraction_digits) {
    $this->assertEquals($this->currencyRepository->getDefaultFractionDigits($currency_code), $expected_fraction_digits);
  }

  /**
   * Data provider for ::testGetDefaultFractionDigits.
   *
   * @return array
   *   The test data.
   */
  public function fractionDigitsData() {
    return [
      ['BHD', 3],
      ['UGX', 0],
      ['USD', 2],
      ['UYU', 2],
      ['UYW', 4],
    ];
  }

}
