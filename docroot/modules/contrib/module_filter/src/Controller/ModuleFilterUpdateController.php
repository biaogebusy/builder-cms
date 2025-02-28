<?php

namespace Drupal\module_filter\Controller;

use Drupal\update\Controller\UpdateController;

/**
 * A wrapper controller for injecting the filter into the update status page.
 */
class ModuleFilterUpdateController extends UpdateController {

  /**
   * {@inheritdoc}
   */
  public function updateStatus() {
    $build = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'update-status',
      ],
    ];
    $build['module_filter'] = $this->formBuilder()->getForm('Drupal\module_filter\Form\ModuleFilterUpdateStatusForm');
    $build['update_report'] = parent::updateStatus();
    return $build;
  }

}
