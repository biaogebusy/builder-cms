<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_merge_fields;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a listing of Merge Fields Group entities.
 */
class MergeFieldsGroupListBuilder extends ConfigEntityListBuilder {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new MergeFieldsGroupListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($entity_type, $storage);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Label');
    $header['id'] = $this->t('Machine name');
    $header['weight'] = $this->t('Weight');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\ckeditor5_premium_features_merge_fields\Entity\MergeFieldsGroupInterface $entity */
    $row['label'] = $entity->label();
    $row['id'] = $entity->id();

    // Get the items count and list.
    $items = $entity->getItems();
    $item_count = count($items);
    $item_labels = [];

    if (!empty($items)) {
      $item_storage = $this->entityTypeManager->getStorage('merge_fields_item');
      foreach ($items as $item_id) {
        $item = $item_storage->load($item_id);
        if ($item) {
          $item_labels[] = $item->label();
        }
      }
    }

    $row['items'] = $this->t('@count items', ['@count' => $item_count]);
    if (!empty($item_labels)) {
      $row['items'] .= ': ' . implode(', ', $item_labels);
    }

    $row['weight'] = $entity->getWeight();
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);

    // Add a link to add a new item to this group.
    $operations['add_item'] = [
      'title' => $this->t('Add item'),
      'weight' => 15,
      'url' => \Drupal\Core\Url::fromRoute('entity.merge_fields_item.add_form', [], [
        'query' => ['group' => $entity->id()],
      ]),
    ];

    return $operations;
  }

}
