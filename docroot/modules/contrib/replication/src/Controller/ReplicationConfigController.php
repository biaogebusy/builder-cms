<?php

namespace Drupal\replication\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Class ReplicationConfigController.
 *
 * @package Drupal\replication\Controller
 */
class ReplicationConfigController extends ControllerBase {

  /**
   * Returns the replication configuration forms.
   */
  public function getForms() {
    $build['replication_settings'] = $this->formBuilder()->getForm('Drupal\replication\Form\SettingsForm');
    return $build;
  }

}
