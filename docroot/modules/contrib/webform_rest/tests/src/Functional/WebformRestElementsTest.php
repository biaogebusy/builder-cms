<?php

namespace Drupal\Tests\webform_rest\Functional;

use Drupal\Tests\webform\Functional\WebformBrowserTestBase;

/**
 * Test the webform rest endpoints for elements.
 *
 * @group webform_rest
 */
class WebformRestElementsTest extends WebformBrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'webform',
    'webform_rest',
    'webform_rest_test',
  ];

  /**
   * Webforms to load.
   *
   * @var array
   */
  protected static $testWebforms = ['webform_rest_test'];

  /**
   * Test method GET elements resource.
   */
  public function testWebformRestGetElements() {
    $this->drupalLogin($this->rootUser);

    // Get webform elements.
    $result = $this->drupalGet("/webform_rest/webform_rest_test/elements", ['query' => ['_format' => 'hal_json']]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains('"first_name":{"#title":"First name"');
    $this->assertSession()->responseContains('"last_name":{"#title":"Last name"');
  }

}
