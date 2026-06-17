<?php

declare(strict_types=1);

namespace Drupal\Tests\layout_builder_st\Functional;

/**
 * Common functions for testing Layout Builder with translations.
 */
trait TranslationTestTrait {

  /**
   * Asserts that non-trans actions have been removed.
   */
  protected function assertNonTranslationActionsRemoved() {
    /** @var \Drupal\Tests\WebAssert $assert_session */
    $assert_session = $this->assertSession();
    // Confirm that links do not exist to change the layout.
    $assert_session->linkNotExists('Add Section');
    $assert_session->linkNotExists('Add Block');
    $assert_session->linkNotExists('Remove section');
    $assert_session->elementNotExists('css', '[data-contextual-id^="layout_builder_block:"]');
    $assert_session->buttonNotExists('Revert to defaults');
  }

}
