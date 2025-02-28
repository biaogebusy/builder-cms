<?php

namespace Drupal\profile;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Defines the list builder for profile types.
 */
class ProfileTypeListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['type'] = $this->t('Profile type');
    $header['registration'] = $this->t('Registration');
    $header['multiple'] = $this->t('Allow multiple profiles');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\profile\Entity\ProfileTypeInterface $entity */
    $row['type'] = $entity->toLink(NULL, 'edit-form');
    $row['registration'] = $entity->getRegistration() ? $this->t('Yes') : $this->t('No');
    $row['multiple'] = $entity->allowsMultiple() ? $this->t('Yes') : $this->t('No');
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getOperations(EntityInterface $entity) {
    $operations = parent::getOperations($entity);
    // Place the edit operation after the operations added by field_ui.module
    // which have the weights 15, 20, 25.
    if (isset($operations['edit'])) {
      $operations['edit'] = [
        'title' => $this->t('Edit'),
        'weight' => 30,
        'url' => $entity->toUrl('edit-form'),
      ];
    }
    if (isset($operations['delete'])) {
      $operations['delete'] = [
        'title' => $this->t('Delete'),
        'weight' => 35,
        'url' => $entity->toUrl('delete-form'),
      ];
    }
    // Sort the operations to normalize link order.
    uasort($operations, [
      'Drupal\Component\Utility\SortArray',
      'sortByWeightElement',
    ]);

    return $operations;
  }

}
