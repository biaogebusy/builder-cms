<?php

namespace Drupal\bootstrap_layout_builder;

use Drupal\Core\Config\Entity\DraggableListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a listing of layout builder breakpoints entities.
 */
class BreakpointListBuilder extends DraggableListBuilder {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'blb_breakpoints_admin_overview_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Label');
    $header['id'] = $this->t('Machine name');
    $header['base_class'] = $this->t('Base class');
    $header['status'] = $this->t('Status');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row = [];
    $row['label'] = $entity->label();
    $row['id'] = ['#plain_text' => $entity->id()];
    $row['base_class'] = ['#plain_text' => $entity->getBaseClass()];
    $row['status'] = ['#plain_text' => $entity->getStatus() == 1 ? $this->t('Enabled') : $this->t('Disabled')];
    return $row + parent::buildRow($entity);
  }

}
