<?php

namespace Drupal\Tests\commerce_price\Kernel;

use Drupal\Tests\commerce\Kernel\CommerceKernelTestBase;

/**
 * Tests the Current Currency.
 *
 * @coversDefaultClass \Drupal\commerce_price\CurrentCurrency
 *
 * @group commerce
 */
class CurrentCurrencyTest extends CommerceKernelTestBase {

  /**
   * The store.
   *
   * @var \Drupal\commerce_store\Entity\StoreInterface
   */
  protected $storeEur;

  /**
   * The current currency.
   *
   * @var \Drupal\commerce_price\CurrentCurrencyInterface
   */
  protected $currentCurrency;

  /**
   * The current store.
   *
   * @var \Drupal\commerce_store\CurrentStoreInterface
   */
  protected $currentStore;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $currency_importer = $this->container->get('commerce_price.currency_importer');
    $currency_importer->import('EUR');

    $this->storeEur = $this->createStore('Europe store', 'admin@example.com');
    $this->storeEur->setDefaultCurrencyCode('EUR');
    $this->storeEur->save();

    $this->currentStore = $this->container->get('commerce_store.current_store');
    $this->currentCurrency = $this->container->get('commerce_price.current_currency');

  }

  /**
   * @covers ::getCurrency
   */
  public function testDefaultStore() {
    $this->assertFalse($this->store->isDefault());
    $this->assertTrue($this->storeEur->isDefault());
    $this->assertEquals('Europe store', $this->currentStore->getStore()->getName());
    $this->assertEquals('EUR', $this->currentStore->getStore()->getDefaultCurrencyCode());
    $this->assertEquals('EUR', $this->currentCurrency->getCurrency()->getCurrencyCode());
  }

  /**
   * @covers ::getCurrency
   */
  public function testSwitchDefaultStore() {
    $this->store->setDefault(TRUE)->save();
    $this->storeEur->setDefault(FALSE)->save();
    $this->assertTrue($this->store->isDefault());
    $this->assertFalse($this->storeEur->isDefault());
    $this->assertEquals('Default store', $this->currentStore->getStore()->getName());
    $this->assertEquals('USD', $this->currentStore->getStore()->getDefaultCurrencyCode());
    $this->assertEquals('USD', $this->currentCurrency->getCurrency()->getCurrencyCode());
  }

  /**
   * @covers ::getCurrency
   */
  public function testDeleteDefaultStore() {
    $this->assertFalse($this->store->isDefault());
    $this->assertTrue($this->storeEur->isDefault());
    $this->storeEur->delete();

    $this->assertEquals('USD', $this->currentStore->getStore()->getDefaultCurrencyCode());
    $this->assertEquals('USD', $this->currentCurrency->getCurrency()->getCurrencyCode());
  }

  /**
   * @covers ::getCurrency
   */
  public function testNoStore() {
    $this->storeEur->delete();
    $this->store->delete();
    $this->assertNull($this->currentStore->getStore());
    $this->assertNull($this->currentCurrency->getCurrency());
  }

}
