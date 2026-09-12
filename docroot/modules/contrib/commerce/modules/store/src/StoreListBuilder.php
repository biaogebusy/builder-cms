<?php

namespace Drupal\commerce_store;

use Drupal\commerce_store\Entity\StoreInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;
use Drupal\commerce_store\Entity\StoreType;

/**
 * Defines the list builder for stores.
 */
class StoreListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['name'] = $this->t('Name');
    $header['type'] = $this->t('Type');
    $header['status'] = $this->t('Status');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\commerce_store\Entity\StoreInterface $entity */
    $store_type = StoreType::load($entity->bundle());

    $row['name']['data'] = Link::fromTextAndUrl($entity->label(), $entity->toUrl());
    $row['type'] = $store_type->label();
    $row['status'] = $entity->isPublished() ? $this->t('Enabled') : $this->t('Disabled');

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultOperations(EntityInterface $entity/* , ?CacheableMetadata $cacheability = NULL */) {
    $cacheability = func_num_args() > 1 ? func_get_arg(1) : NULL;
    $operations = parent::getDefaultOperations($entity, $cacheability);
    assert($entity instanceof StoreInterface);

    $access = $entity->access('update', NULL, TRUE);
    if ($cacheability instanceof CacheableMetadata) {
      $cacheability->addCacheableDependency($access);
    }
    if ($access->isAllowed()) {
      if (!$entity->isPublished() &&
        $entity->hasLinkTemplate('enable-form')) {
        $operations['enable'] = [
          'title' => $this->t('Enable'),
          'weight' => -10,
          'url' => $this->ensureDestination($entity->toUrl('enable-form')),
        ];
      }
      elseif ($entity->hasLinkTemplate('disable-form')) {
        $operations['disable'] = [
          'title' => $this->t('Disable'),
          'weight' => 40,
          'url' => $this->ensureDestination($entity->toUrl('disable-form')),
        ];
      }
    }

    return $operations;
  }

}
