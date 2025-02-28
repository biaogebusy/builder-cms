<?php

namespace Drupal\Tests\commerce_tax\Kernel\Plugin\Commerce\TaxType;

use Drupal\commerce_tax\Entity\TaxType;

/**
 * @coversDefaultClass \Drupal\commerce_tax\Plugin\Commerce\TaxType\SwissVat
 * @group commerce
 */
class SwissVatTest extends EuropeanUnionVatTest {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->taxType = TaxType::create([
      'id' => 'swiss_vat',
      'label' => 'Swiss VAT',
      'plugin' => 'swiss_vat',
      'configuration' => [
        'display_inclusive' => TRUE,
      ],
      // Don't allow the tax type to apply automatically.
      'status' => FALSE,
    ]);
    $this->taxType->save();
  }

  /**
   * @covers ::applies
   * @covers ::apply
   */
  public function testApplication() {
    $plugin = $this->taxType->getPlugin();
    // Swiss customer, Swiss store, standard VAT.
    $order = $this->buildOrder('CH', 'CH', '', ['CH']);
    $this->assertTrue($plugin->applies($order));
    $plugin->apply($order);
    $adjustments = $order->collectAdjustments();
    $adjustment = reset($adjustments);
    $this->assertCount(1, $adjustments);
    $this->assertEquals('swiss_vat|ch|standard', $adjustment->getSourceId());

    // Liechtenstein customer, Swiss store, standard VAT.
    $order = $this->buildOrder('LI', 'CH', '', ['CH']);
    $this->assertTrue($plugin->applies($order));
    $plugin->apply($order);
    $adjustments = $order->collectAdjustments();
    $adjustment = reset($adjustments);
    $this->assertCount(1, $adjustments);
    $this->assertEquals('swiss_vat|ch|standard', $adjustment->getSourceId());

    // Serbian customer, Swiss store, no VAT.
    $order = $this->buildOrder('RS', 'CH', '', ['CH']);
    $this->assertTrue($plugin->applies($order));
    $plugin->apply($order);
    $adjustments = $order->collectAdjustments();
    $this->assertCount(0, $adjustments);

    // German customer in Büsingen, CH store registered in CH,
    // physical product.
    $order = $this->buildOrder('DE', 'CH', '', ['CH']);
    $billing_profile = $order->getBillingProfile();
    $billing_profile->set('address', [
      'country_code' => 'DE',
      'postal_code' => '78266',
    ]);
    $billing_profile->save();
    $plugin->apply($order);
    $adjustments = $order->collectAdjustments();
    $adjustment = reset($adjustments);
    $this->assertCount(1, $adjustments);
    $this->assertEquals('swiss_vat|ch|standard', $adjustment->getSourceId());
  }

  /**
   * @covers ::getZones
   */
  public function testGetZones() {
    /** @var \Drupal\commerce_tax\Plugin\Commerce\TaxType\LocalTaxTypeInterface $plugin */
    $plugin = $this->taxType->getPlugin();
    $zones = $plugin->getZones();
    $this->assertArrayHasKey('ch', $zones);
  }

}
