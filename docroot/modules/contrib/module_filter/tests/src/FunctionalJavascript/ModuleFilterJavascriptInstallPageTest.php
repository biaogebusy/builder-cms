<?php

namespace Drupal\Tests\module_filter\FunctionalJavascript;

/**
 * Tests the module extend install page.
 *
 * @group module_filter
 */
class ModuleFilterJavascriptInstallPageTest extends ModuleFilterJavascriptTestBase {

  /**
   * Tests filtering on the module install page.
   *
   * @see https://www.drupal.org/project/module_filter/issues/3327899
   */
  public function testInstallPageFiltering() {
    /** @var \Drupal\Tests\WebAssert $assert */
    $assert = $this->assertSession();

    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/modules');
    $page = $this->getSession()->getPage();
    $page->clickLink('Testing');

    // Verify that all the modules used in this test are displayed by default.
    $assert->pageTextContains('Roses');
    $assert->pageTextContains('Banana');
    $assert->pageTextContains('Ridge');

    // Enter 'red' as the filter and check that the Red Roses module is
    // displayed but the other modules are not. This shows that filtering works
    // on the module's internal machine name.
    $page->fillField('edit-text', 'red');
    $this->waitForNoText('Banana');
    $assert->pageTextContains('Roses');
    $assert->pageTextContains('Say it with flowers');
    $assert->pageTextNotContains('Banana');
    $assert->pageTextNotContains('Ridge');

    // Enter 'nana' as the filter and check that the Yellow Banana module is
    // displayed but the others modules are not. This shows that filtering works
    // on the module displayed name, and matches partial words.
    $page->fillField('edit-text', 'nana');
    $assert->waitForText('Banana');
    $assert->pageTextNotContains('Roses');
    $assert->pageTextContains('Banana');
    $assert->pageTextContains('Its a fruity one');
    $assert->pageTextNotContains('Ridge');

    // Enter 'untain' as the filter and check that the Blue Ridge module is
    // displayed but the other modules are not. This shows that filtering works
    // on the module description. It also shows that matches can be on partial
    // words and the first word is not ignored. Core is buggy and fails on both
    // of these. See https://www.drupal.org/project/drupal/issues/3316584
    $page->fillField('edit-text', 'untain');
    $assert->waitForText('Ridge');
    $assert->pageTextNotContains('Roses');
    $assert->pageTextNotContains('Banana');
    $assert->pageTextContains('Ridge');
    $assert->pageTextContains('Mountains of Virginia');

    // Enter 'low' as the filter and check that both the Red Roses and Yellow
    // Banana modules are dispayed, and the Blue Ridge module is not. This shows
    // that filtering can work simultaneously on two different sources, as it
    // matches 'flowers' in the Roses desciption and also 'yellow' in the Banana
    // module name.
    $page->fillField('edit-text', 'low');
    $assert->waitForText('Roses');
    $assert->pageTextContains('Roses');
    $assert->pageTextContains('Banana');
    $assert->pageTextNotContains('Ridge');
    $assert->pageTextNotContains('Mountains of Virginia');

  }

}
