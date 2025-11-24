<?php

declare(strict_types=1);

namespace Drupal\Tests\diff\Functional;

/**
 * Maintains differences between 8.3.x and 8.4.x for tests.
 */
trait CoreVersionUiTestTrait {

  /**
   * Posts the node form depending on core version.
   *
   * @param string|\Drupal\Core\Url $path
   *   The path to post the form.
   * @param array $edit
   *   An array of values to post.
   * @param string $submit
   *   The label of the submit button to post.
   */
  protected function drupalPostNodeForm($path, array $edit, string $submit): void {
    $this->drupalGet($path);
    $this->submitForm($edit, $submit);
  }

}
