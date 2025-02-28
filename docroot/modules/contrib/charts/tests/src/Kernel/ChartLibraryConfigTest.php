<?php

namespace Drupal\Tests\charts\Kernel;

use Drupal\Tests\charts\Traits\ConfigUpdateTrait;

/**
 * Tests the chart library configurations that are part of default config.
 *
 * @group charts
 */
class ChartLibraryConfigTest extends ChartsKernelTestBase {

  use ConfigUpdateTrait;


  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'charts',
    'charts_test',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->updateFooConfiguration('bar');
  }

  /**
   * Tests if library config is available after rendering.
   */
  public function testLibraryConfigIsAvailable() {
    $element = [
      '#type' => 'chart',
      '#library' => 'charts_test_library',
      '#chart_type' => 'column',
    ];

    $path = ['foo_configuration'];
    // Confirm that 'foo_configuration' option is set to 'bar'.
    $this->assertJsonPropertyHasValue($element, $path, 'bar');

    // Check if we change the default config for the library config it will
    // pick up after rendering.
    $new_value = $this->randomString();
    $this->updateFooConfiguration($new_value);
    $this->assertJsonPropertyHasValue($element, $path, $new_value);
  }

}
