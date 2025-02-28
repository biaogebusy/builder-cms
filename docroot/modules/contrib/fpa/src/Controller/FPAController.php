<?php

namespace Drupal\fpa\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\fpa\FpaFormBuilder;

/**
 * Class FPAController.
 *
 * Helps to create a page with permissions.
 *
 * @package Drupal\fpa\Controller
 */
class FPAController extends ControllerBase {

  /**
   * Builds a permissions page.
   *
   * @return mixed
   *   Returns a render array.
   */
  public function permissionsList() {
    return FpaFormBuilder::buildFpaPage();
  }

}
