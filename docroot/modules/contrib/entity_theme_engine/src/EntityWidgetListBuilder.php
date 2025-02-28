<?php

namespace Drupal\entity_theme_engine;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a listing of Managed entity entities.
 */
class EntityWidgetListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header = [];
    $header['label'] = $this->t('Widget Name');
    $header['entity_type'] = $this->t('Entity type');
    $header['bundle'] = $this->t('Entity Bundle');
    $header['display'] = $this->t('Dispaly Mode');
    $header['theme'] = $this->t('Theme');
    $header['enabled'] = $this->t('Enabled');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row = [];
    $row['label'] = $entity->label();
    $row['entity_type'] = $entity->get('entity_type');
    $row['bundle'] = $entity->get('bundle');
    $row['display'] = $entity->get('display');
    $row['theme'] = $entity->get('theme');
    $row['enabled'] = $entity->get('enabled');
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);
    
    // Remove query option to allow the save and continue to correctly function.
    $options = $operations['edit']['url']->getOptions();
    unset($options['query']);
    $operations['edit']['url']->setOptions($options);
    return $operations;
  }
}
