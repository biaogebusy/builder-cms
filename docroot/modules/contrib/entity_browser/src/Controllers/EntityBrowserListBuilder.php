<?php

namespace Drupal\entity_browser\Controllers;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * Provides a list controller for entity browser.
 *
 * @ingroup entity_browser
 */
class EntityBrowserListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   *
   * Building the header and content lines for the entity browser list.
   *
   * Calling the parent::buildHeader() adds a column for the possible actions
   * and inserts the 'edit' and 'delete' links as defined for the entity type.
   */
  public function buildHeader() {
    $header['id'] = $this->t('ID');
    $header['name'] = $this->t('Name');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\entity_browser\Entity\EntityBrowser $entity */
    $row['id'] = $entity->id();
    $row['name'] = $entity->label();
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   *
   * @todo Make the $cacheability parameter required and drop the null-safe
   *   handling once we require at least drupal:11.3.0.
   */
  protected function getDefaultOperations(EntityInterface $entity, ?CacheableMetadata $cacheability = NULL) {
    $operations = [];

    // The $cacheability parameter was added to the parent method in
    // drupal:11.3.0 (and is required as of drupal:12.0.0); it is NULL on the
    // older core versions we still support, so every access result is added
    // through the null-safe operator.
    $update_access = $entity->access('update', NULL, TRUE);
    $cacheability?->addCacheableDependency($update_access);
    if ($update_access->isAllowed()) {
      $operations['edit'] = [
        'title' => $this->t('Edit'),
        'url' => $entity->toUrl('edit-form'),
      ];
    }

    $operations['edit-widgets'] = [
      'title' => $this->t('Edit Widgets'),
      'url' => $entity->toUrl('edit-widgets'),
    ];

    $delete_access = $entity->access('delete', NULL, TRUE);
    $cacheability?->addCacheableDependency($delete_access);
    if ($delete_access->isAllowed()) {
      $operations['delete'] = [
        'title' => $this->t('Delete'),
        'url' => $entity->toUrl('delete-form'),
        'weight' => 100,
      ];
    }

    return $operations;
  }

}
