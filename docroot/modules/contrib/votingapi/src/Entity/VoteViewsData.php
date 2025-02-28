<?php

namespace Drupal\votingapi\Entity;

use Drupal\views\EntityViewsData;
use Drupal\views\EntityViewsDataInterface;

/**
 * Provides Views data for Vote entities.
 */
class VoteViewsData extends EntityViewsData implements EntityViewsDataInterface {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    $data['votingapi_vote']['table']['base'] = [
      'field' => 'id',
      'title' => $this->t('Vote'),
      'help' => $this->t('The Vote ID.'),
    ];

    return $data;
  }

}
