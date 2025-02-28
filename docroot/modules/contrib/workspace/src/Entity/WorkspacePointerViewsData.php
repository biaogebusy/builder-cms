<?php

namespace Drupal\workspace\Entity;

use Drupal\views\EntityViewsData;
use Drupal\views\EntityViewsDataInterface;

/**
 * Provides Views data for Workspace pointer entities.
 */
class WorkspacePointerViewsData extends EntityViewsData implements EntityViewsDataInterface {
  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    $data['workspace_pointer']['table']['base'] = [
      'field' => 'id',
      'title' => $this->t('Workspace pointer'),
      'help' => $this->t('The Workspace pointer ID.'),
    ];

    return $data;
  }

}
