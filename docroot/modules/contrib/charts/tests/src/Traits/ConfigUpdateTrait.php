<?php

namespace Drupal\Tests\charts\Traits;

/**
 * Provides a trait to update the configuration.
 */
trait ConfigUpdateTrait {

  /**
   * Updates the foo configuration.
   *
   * @param string $value
   *   The value to set.
   * @param string $library
   *   Library to be rendered.
   */
  protected function updateFooConfiguration(string $value, $library = 'charts_test_library'): void {
    $config = $this->config('charts.settings');
    $settings = $config->get('charts_default_settings');
    $settings['library'] = $library;
    $settings['library_config'] = ['foo' => $value];
    $config->set('charts_default_settings', $settings);
    $config->save();
  }

}
