<?php

namespace Drupal\tourauto_test\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller for tourauto test routes.
 */
class TourautoTestController extends ControllerBase {

  /**
   * Test page for tourauto functionality.
   *
   * @return array
   *   A render array.
   */
  public function testPage(): array {
    return [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => 'Tourauto test page with tour functionality.',
      '#attributes' => [
        'id' => 'tourauto-test-tip-1',
        'class' => ['tourauto-test-element'],
      ],
    ];
  }

}
