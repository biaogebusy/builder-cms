<?php

declare(strict_types=1);

namespace Drupal\private_message\Controller;

use Drupal\Core\Ajax\AjaxResponse;

/**
 * Interface for the Private Message module's AjaxController.
 */
interface AjaxControllerInterface {

  /**
   * Create AJAX responses for JavaScript requests.
   *
   * @param string $op
   *   The type of data to build for the response.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The ajax response
   */
  public function ajaxCallback(string $op): AjaxResponse;

}
