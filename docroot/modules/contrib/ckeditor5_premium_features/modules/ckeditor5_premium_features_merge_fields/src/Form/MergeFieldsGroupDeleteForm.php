<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_merge_fields\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for deleting Merge Fields Group entities.
 */
class MergeFieldsGroupDeleteForm extends EntityConfirmFormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a MergeFieldsGroupDeleteForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete group %name? All items related to this group will be also deleted.' , ['%name' => $this->entity->label()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.merge_fields_group.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $entity = $this->getEntity();
    $entity_id = $entity->id();
    $entity_label = $entity->label();

    // Delete all items that belong to this group.
    $query = $this->entityTypeManager->getStorage('merge_fields_item')->getQuery();
    $itemIds = $query
      ->accessCheck(FALSE)
      ->condition('group', $entity_id)
      ->execute();
    if (!empty($itemIds)) {
      $items = $this->entityTypeManager->getStorage('merge_fields_item')->loadMultiple( $itemIds);
      foreach ($items as $item) {
        $item->delete();
      }
    }

    // Delete the entity.
    $entity->delete();

    $this->messenger()->addStatus($this->t('The Merge Fields Group %label has been deleted.', [
      '%label' => $entity_label,
    ]));

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
