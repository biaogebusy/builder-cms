<?php

namespace Drupal\simple_oauth_test\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Resource route for testing access.
 */
class Resource extends ControllerBase {

  /**
   * Controller method for testing purpose.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return array
   *   Returns render array.
   */
  public function get(Request $request): array {
    return [
      '#markup' => 'Successful access',
    ];
  }

}
