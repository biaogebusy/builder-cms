<?php

declare(strict_types=1);

namespace Drupal\Tests\layout_builder_st\FunctionalJavascript;

/**
 * Common functions for testing Layout Builder.
 */
trait LayoutBuilderTestTrait {

  /**
   * Adds a block in the Layout Builder.
   *
   * @param string $block_link_text
   *   The link text to add the block.
   * @param string $rendered_locator
   *   The CSS locator to confirm the block was rendered.
   * @param bool $label_display
   *   Whether the label should be displayed.
   * @param string|null $label
   *   The label use.
   */
  protected function addBlock($block_link_text, $rendered_locator, $label_display = FALSE, $label = NULL) {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    $add_block_text = 'Add block';
    if (version_compare(\Drupal::VERSION, '8.8.0', '<')) {
      $add_block_text = ucwords($add_block_text);
    }

    // Add a new block.
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', "#layout-builder a:contains('$add_block_text')"));
    $this->clickLink($add_block_text);
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', '#drupal-off-canvas'));
    $assert_session->assertWaitOnAjaxRequest();

    $assert_session->linkExists($block_link_text);
    $this->clickLink($block_link_text);

    // Wait for off-canvas dialog to reopen with block form.
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', ".layout-builder-add-block"));
    $assert_session->assertWaitOnAjaxRequest();
    if ($label_display) {
      $page->checkField('settings[label_display]');
    }
    if ($label !== NULL) {
      $page->fillField('settings[label]', $label);
    }
    $page->pressButton($add_block_text);

    // Wait for block form to be rendered in the Layout Builder.
    $this->assertNotEmpty($assert_session->waitForElement('css', $rendered_locator));
  }

  /**
   * Waits for an element to be removed from the page.
   *
   * @param string $selector
   *   CSS selector.
   * @param int $timeout
   *   (optional) Timeout in milliseconds, defaults to 10000.
   * @param string $message
   *   (optional) Custom message to display with the assertion.
   *
   * @todo Remove after https://www.drupal.org/project/drupal/issues/2892440
   */
  public function assertNoElementAfterWait($selector, $timeout = 10000, $message = '') {
    $page = $this->getSession()->getPage();
    if ($message === '') {
      $message = "Element '$selector' was not on the page after wait.";
    }
    $this->assertTrue($page->waitFor($timeout / 1000, function () use ($page, $selector) {
      return empty($page->find('css', $selector));
    }), $message);
  }

}
