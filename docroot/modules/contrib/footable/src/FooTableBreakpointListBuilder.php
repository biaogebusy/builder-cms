<?php

namespace Drupal\footable;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Defines a class to build a listing of FooTable breakpoint entities.
 *
 * @see \Drupal\footable\Entity\FooTableBreakpoint
 */
class FooTableBreakpointListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Label');
    $header['name'] = $this->t('Name');
    $header['breakpoint'] = $this->t('Breakpoint');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['label'] = $entity->label();
    $row['name'] = $entity->id();
    $row['breakpoint'] = $entity->getBreakpoint() . 'px';
    return $row + parent::buildRow($entity);
  }

}
