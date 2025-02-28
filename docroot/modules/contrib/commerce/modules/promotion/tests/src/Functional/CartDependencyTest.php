<?php

namespace Drupal\Tests\commerce_promotion\Functional;

use Drupal\Tests\commerce\Functional\CommerceBrowserTestBase;

/**
 * Tests that promotion installs without Commerce Cart.
 *
 * @see https://www.drupal.org/project/commerce/issues/3150268
 * @group commerce
 */
class CartDependencyTest extends CommerceBrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'commerce_order',
  ];

  /**
   * {@inheritdoc}
   */
  protected function getAdministratorPermissions() {
    return array_merge([
      'access administration pages',
      'administer modules',
    ], parent::getAdministratorPermissions());
  }

  /**
   * Verifies the test ran.
   */
  public function testDidNotCrash() {
    $edit = [];
    $edit['modules[commerce_promotion][enable]'] = TRUE;
    $this->drupalGet('admin/modules');
    $this->submitForm($edit, 'Install');
    $this->assertSession()->pageTextContains('Module Commerce Promotion has been enabled.');
  }

}
