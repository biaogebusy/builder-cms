<?php

namespace Drupal\workspace\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Class ReplicationConfigController.
 *
 * @package Drupal\workspace\Controller
 */
class ReplicationConfigController extends ControllerBase {

  /**
   * Returns the replication configuration forms.
   */
  public function getForms() {
    $build['replication_unblock_button'] = $this->formBuilder()->getForm('Drupal\workspace\Form\UnblockReplicationForm');
    $build['clear_queue_button'] = $this->formBuilder()->getForm('Drupal\workspace\Form\ClearReplicationQueueForm');
    $build['replication_settings'] = $this->formBuilder()->getForm('Drupal\replication\Form\SettingsForm');
    return $build;
  }

}
