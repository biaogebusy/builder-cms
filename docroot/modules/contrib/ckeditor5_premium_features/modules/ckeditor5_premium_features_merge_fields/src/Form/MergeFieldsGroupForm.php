<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_merge_fields\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for creating/editing Merge Fields Group entities.
 */
class MergeFieldsGroupForm extends EntityForm {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a MergeFieldsGroupForm object.
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
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $merge_fields_group = $this->entity;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $merge_fields_group->label(),
      '#description' => $this->t('Label for the Merge Fields Group.'),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $merge_fields_group->id(),
      '#machine_name' => [
        'exists' => [$this, 'exist'],
      ],
      '#disabled' => !$merge_fields_group->isNew(),
    ];

    $form['weight'] = [
      '#type' => 'weight',
      '#title' => $this->t('Weight'),
      '#default_value' => $merge_fields_group->getWeight(),
      '#description' => $this->t('The weight of this group in relation to other groups.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $merge_fields_group = $this->entity;
    $status = $merge_fields_group->save();

    if ($status) {
      $this->messenger()->addStatus($this->t('Saved the %label Merge Fields Group.', [
        '%label' => $merge_fields_group->label(),
      ]));
    }
    else {
      $this->messenger()->addError($this->t('The %label Merge Fields Group was not saved.', [
        '%label' => $merge_fields_group->label(),
      ]));
    }

    $form_state->setRedirectUrl($merge_fields_group->toUrl('collection'));
  }

  /**
   * Helper function to check whether a Merge Fields Group configuration entity exists.
   */
  public function exist($id) {
    $group = $this->entityTypeManager->getStorage('merge_fields_group')->getQuery()
      ->accessCheck(FALSE)
      ->condition('id', $id)
      ->execute();
    $item = $this->entityTypeManager->getStorage('merge_fields_item')->getQuery()
      ->accessCheck(FALSE)
      ->condition('id', $id)
      ->execute();
    return $group || $item;
  }

}
