<?php

declare(strict_types=1);

namespace Drupal\Tests\layout_builder_st\FunctionalJavascript;

/**
 * Common functions for testing Layout Builder with translations.
 */
trait JavascriptTranslationTestTrait {

  /**
   * Whether the test is using config_translation.
   *
   * @var bool
   */
  protected $usingConfigTranslation = FALSE;

  /**
   * Updates a block label translation.
   *
   * @param string $block_selector
   *   The CSS selector for the block.
   * @param string $untranslated_label
   *   The label untranslated.
   * @param string $new_label
   *   The new label to set.
   * @param string $expected_label
   *   The expected existing translated label.
   * @param array $unexpected_element_selectors
   *   A list of selectors for elements that should be present.
   */
  protected function updateBlockTranslation($block_selector, $untranslated_label, $new_label, $expected_label = '', array $unexpected_element_selectors = []) {
    /** @var \Drupal\Tests\WebAssert $assert_session */
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    $translation_selector_prefix = $this->usingConfigTranslation ? '#drupal-off-canvas .translation-set ' : '#drupal-off-canvas ';
    $this->clickContextualLink($block_selector, 'Translate block');
    $label_input = $assert_session->waitForElementVisible('css', $translation_selector_prefix . '[name="translation[label]"]');
    $this->assertNotEmpty($label_input);
    $this->assertEquals($expected_label, $label_input->getValue());
    $assert_session->elementTextContains('css', $translation_selector_prefix . '.form-item-source-label', $untranslated_label);
    $label_input->setValue($new_label);
    foreach ($unexpected_element_selectors as $unexpected_element_selector) {
      $assert_session->elementNotExists('css', $unexpected_element_selector);
    }
    $page->pressButton('Translate');
    $this->assertNoElementAfterWait('#drupal-off-canvas');
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', "h2:contains(\"$new_label\")"));
  }

}
