<?php

namespace Drupal\Tests\module_filter\FunctionalJavascript;

/**
 * Tests the Permisions tab on admin/people/permissions.
 *
 * @group module_filter
 */
class ModuleFilterJavascriptPermissionsTest extends ModuleFilterJavascriptTestBase {

  /**
   * Tests the filtering of permissions.
   */
  public function testPermissionsFiltering() {
    /** @var \Drupal\Tests\WebAssert $assert */
    $assert = $this->assertSession();

    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/people/permissions');
    $page = $this->getSession()->getPage();

    // Check hat the test modules and their permissions are shown by default.
    $assert->pageTextContains('Roses');
    $assert->pageTextContains('Buy flowers');
    $assert->pageTextContains('Send flowers');
    $assert->pageTextContains('Banana');
    $assert->pageTextContains('Buy fruit');

    // Enter 'ses' as the filter and check that the Red Roses module is
    // displayed and both of its permissions are displayed. Check that the other
    // module and its permissions are not displayed. This shows that filtering
    // works on the module's readable name.
    $page->fillField('edit-text', 'ses');
    $this->waitForNoText('Banana');
    $assert->waitForText('Roses');
    $assert->pageTextContains('Roses');
    $assert->pageTextContains('Buy flowers');
    $assert->pageTextContains('Send flowers');
    $assert->pageTextNotContains('Banana');
    $assert->pageTextNotContains('Buy fruit');

    // Enter 'rui' as the filter and check that the Banana module is
    // displayed along with its permission. Check that the Roses module and its
    // permissions are not displayed. This shows that filtering works on the
    // text of the permission and that the module name is also shown.
    $page->fillField('edit-text', 'rui');
    $this->waitForNoText('Roses');
    $assert->waitForText('Banana');
    $assert->pageTextNotContains('Roses');
    $assert->pageTextNotContains('Buy flowers');
    $assert->pageTextNotContains('Send flowers');
    $assert->pageTextContains('Banana');
    $assert->pageTextContains('Buy fruit');

    // Enter 'buy' as the filter and check that both of the modules are shown
    // but only with the permissions that contain 'buy'. This demonstates that
    // matching text on permission hides permissions from a matching module that
    // do not contain that text in the permission.
    $page->fillField('edit-text', 'buy');
    $assert->waitForText('Roses');
    $assert->pageTextContains('Roses');
    $assert->pageTextContains('Buy flowers');
    $assert->pageTextNotContains('Send flowers');
    $assert->pageTextContains('Banana');
    $assert->pageTextContains('Buy fruit');

    // Enter 'ana' as the filter and check that the matching module (Banana) is
    // shown, and also the matching permission (Panama). This demonstates that
    // matching can be done simultaneously on the module and the permission.
    $page->fillField('edit-text', 'ana');
    $assert->waitForText('Send');
    $assert->pageTextContains('Roses');
    $assert->pageTextNotContains('Buy flowers');
    $assert->pageTextContains('Send flowers to Panama');
    $assert->pageTextContains('Banana');
    $assert->pageTextContains('Buy fruit');
    $this->createScreenshot(\Drupal::root() . '/sites/default/files/simpletest/screen1.png');

  }

}
