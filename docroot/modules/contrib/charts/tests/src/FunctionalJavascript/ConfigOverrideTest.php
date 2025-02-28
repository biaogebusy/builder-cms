<?php

namespace Drupal\Tests\charts\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\charts\Traits\ConfigUpdateTrait;

/**
 * Tests that chart configuration can be overridden.
 *
 * @group charts
 */
class ConfigOverrideTest extends WebDriverTestBase {

  use ConfigUpdateTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'charts',
    'charts_chartjs',
  ];

  /**
   * Tests that charts config can be overridden.
   */
  public function testOverridingConfig() {
    $this->drupalLogin($this->rootUser);

    // Set up an override of the cdn.
    $settings['config']['charts.settings']['advanced']['requirements']['cdn'] = (object) [
      'value' => FALSE,
      'required' => TRUE,
    ];
    $this->writeSettings($settings);

    // Check the error message.
    $this->drupalGet('admin/reports/status');
    $this->assertSession()->pageTextContains('You are missing the Chart.js library in your Drupal installation directory and you have opted not to use a CDN.');
  }

}
