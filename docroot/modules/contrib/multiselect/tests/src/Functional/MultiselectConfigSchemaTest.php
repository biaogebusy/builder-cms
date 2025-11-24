<?php

namespace Drupal\Tests\multiselect\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Assert that multiselect config schema is valid.
 *
 * @group multiselect
 */
class MultiselectConfigSchemaTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['multiselect_test'];

  /**
   * Assert that the multiselect_test module installed correctly.
   */
  public function testConfigInstalls() {
    // If we get here, then the multiselect_test module was successfully
    // installed during the setUp phase without throwing any Exceptions.
    // Since the test module includes a multiselect.settings config which will
    // be installed and checked against the config schema, an exception is
    // thrown if the config schema is incorrect.
    // So, assert that TRUE is true, so at least one assertion runs, and then
    // exit.
    $this->assertTrue(TRUE, 'Module installed correctly.');
  }

}
