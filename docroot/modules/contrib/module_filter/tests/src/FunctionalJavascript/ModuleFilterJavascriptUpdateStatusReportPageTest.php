<?php

namespace Drupal\Tests\module_filter\FunctionalJavascript;

/**
 * Tests the module update status report page.
 *
 * @group module_filter
 */
class ModuleFilterJavascriptUpdateStatusReportPageTest extends ModuleFilterJavascriptTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'module_filter',
    'update',
  ];

  /**
   * Tests text filtering and radio buttons on the Updates Report page.
   */
  public function testUpdateStatusPageFiltering() {
    /** @var \Drupal\Tests\WebAssert $assert */
    $assert = $this->assertSession();

    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/reports/updates');
    $page = $this->getSession()->getPage();

    // Verify that all the modules used in this test are displayed by default.
    // Test modules will not show on the update status page, so we use this
    // modules dependencies instead.
    $assert->pageTextContains('Drupal core');
    $assert->pageTextContains('jQuery UI');
    $assert->pageTextContains('jQuery UI Autocomplete');
    $assert->pageTextContains('jQuery UI Menu');

    // Ensure the text filter field exists.
    $page->hasField('text');

    // Enter 'jquery' as the filter and check that Drupal core is not shown but
    // all the jQuery UI dependencies are. This shows that filtering works on
    // the module displayed name and is not case-sensitive.
    $page->fillField('text', 'jquery');
    $this->waitForNoText('Drupal core');
    $assert->pageTextNotContains('Drupal core');
    $assert->pageTextContains('jQuery UI');
    $assert->pageTextContains('jQuery UI Autocomplete');
    $assert->pageTextContains('jQuery UI Menu');

    // Enter 'toco' as the filter and check that the jQuery UI Autocomplete
    // module is displayed but Drupal Core and Jquery UI Menu are not. This
    // shows that the filter matches when entering partial words.
    $page->fillField('text', 'toco');
    $this->waitForNoText('jQuery UI Menu');
    $assert->pageTextNotContains('Drupal core');
    $assert->pageTextContains('jQuery UI Autocomplete');
    $assert->pageTextNotContains('jQuery UI Menu');

    // Clear the edit text field and verify that all the modules used in this
    // test are once more displayed.
    $page->fillField('text', '');
    $assert->waitForText('Drupal core');
    $assert->pageTextContains('Drupal core');
    $assert->pageTextContains('jQuery UI');
    $assert->pageTextContains('jQuery UI Autocomplete');
    $assert->pageTextContains('jQuery UI Menu');

    // Ensure the 'show' radio buttons field exists.
    $page->hasField('show');
    $show = $page->findField('show');

    // Select 'updates' and check that the JQuery modules are not shown.
    $show->selectOption('updates');
    $this->waitForNoText('jquery');

    // Reset back to all and check that jQuery UI is shown.
    $show->selectOption('all');
    $assert->waitForText('Drupal core');
    $assert->pageTextContains('jQuery UI Autocomplete');
    $assert->pageTextContains('jQuery UI Menu');

    // Select 'unsupported' and check that jQuery is not shown.
    $show->selectOption('unsupported');
    $this->waitForNoText('jQuery');
    $assert->pageTextNotContains('jQuery UI');
  }

}
