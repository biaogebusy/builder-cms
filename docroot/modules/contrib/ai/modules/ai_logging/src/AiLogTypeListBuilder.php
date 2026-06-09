<?php

namespace Drupal\ai_logging;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * Defines a class to build a listing of AI Log Type entities.
 */
class AiLogTypeListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('AI Log Type');
    $header['id'] = $this->t('Machine name');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\ai_log\Entity\AiLogType $entity */
    // Display the label.
    $row['label'] = $entity->label();
    // Display the machine name.
    $row['id'] = $entity->id();
    return $row + parent::buildRow($entity);
  }

}
