<?php

namespace Drupal\Tests\commerce_tax\Kernel\Plugin\Commerce\TaxType;

use Drupal\commerce_tax\Entity\TaxType;

/**
 * @coversDefaultClass \Drupal\commerce_tax\Plugin\Commerce\TaxType\UnitedKingdomVat
 * @group commerce
 */
class UnitedKingdomVatTest extends EuropeanUnionVatTest {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->taxType = TaxType::create([
      'id' => 'united_kingdom_vat',
      'label' => 'UK VAT',
      'plugin' => 'united_kingdom_vat',
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
    // GB customer, GB store, standard VAT.
    $order = $this->buildOrder('GB', 'GB', '', ['GB']);
    $this->assertTrue($plugin->applies($order));
    $plugin->apply($order);
    $adjustments = $order->collectAdjustments();
    $adjustment = reset($adjustments);
    $this->assertCount(1, $adjustments);
    $this->assertEquals('united_kingdom_vat|gb|standard', $adjustment->getSourceId());

    // Customer from Isles of Man, GB store, standard VAT.
    $order = $this->buildOrder('IM', 'GB', '', ['GB']);
    $this->assertTrue($plugin->applies($order));
    $plugin->apply($order);
    $adjustments = $order->collectAdjustments();
    $adjustment = reset($adjustments);
    $this->assertCount(1, $adjustments);
    $this->assertEquals('united_kingdom_vat|gb|standard', $adjustment->getSourceId());

    // French customer, GB store, no VAT.
    $order = $this->buildOrder('FR', 'GB', '', ['GB']);
    $this->assertTrue($plugin->applies($order));
    $plugin->apply($order);
    $adjustments = $order->collectAdjustments();
    $this->assertCount(0, $adjustments);
  }

  /**
   * @covers ::getZones
   */
  public function testGetZones() {
    /** @var \Drupal\commerce_tax\Plugin\Commerce\TaxType\LocalTaxTypeInterface $plugin */
    $plugin = $this->taxType->getPlugin();
    $zones = $plugin->getZones();
    $this->assertArrayHasKey('gb', $zones);
  }

}
