<?php

namespace Drupal\Tests\address\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the address_plain theme.
 *
 * @group address
 */
class AddressPlainThemeTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'address',
  ];

  /**
   * Tests the address_plain theme output.
   */
  public function testAddressPlainTheme() {
    $address = [
      '#theme' => 'address_plain',
      '#given_name' => 'John',
      '#family_name' => 'Quincy',
      '#organization' => 'Acme, Inc.',
      '#address_line1' => '123 Main St.',
      '#postal_code' => '12345',
      '#locality' => [
        'code' => 'San Francisco',
      ],
      '#administrative_area' => [
        'code' => 'CA',
      ],
      '#country' => [
        'code' => 'US',
        'name' => 'United States',
      ],
    ];
    $this->render($address);

    $this->assertRaw('John');
    $this->assertRaw('Quincy');
    $this->assertRaw('Acme, Inc.');
    $this->assertRaw('123 Main St.');
    $this->assertRaw('12345');
    $this->assertRaw('CA');
    $this->assertRaw('San Francisco');
    $this->assertRaw('United States');
  }

}
