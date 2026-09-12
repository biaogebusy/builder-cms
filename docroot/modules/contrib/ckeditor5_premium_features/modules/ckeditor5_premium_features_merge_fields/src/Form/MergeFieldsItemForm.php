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
use Drupal\ckeditor5_premium_features\Utility\LibraryVersionChecker;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for creating/editing Merge Fields Item entities.
 */
class MergeFieldsItemForm extends EntityForm {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The core library version checker.
   *
   * @var \Drupal\ckeditor5_premium_features\Utility\LibraryVersionChecker
   */
  protected $coreLibraryVersionChecker;

  /**
   * Constructs a MergeFieldsItemForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\ckeditor5_premium_features\Utility\LibraryVersionChecker $core_library_version_checker
   *   The core library version checker.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, LibraryVersionChecker $core_library_version_checker) {
    $this->entityTypeManager = $entity_type_manager;
    $this->coreLibraryVersionChecker = $core_library_version_checker;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('ckeditor5_premium_features.core_library_version_checker')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $merge_fields_item = $this->entity;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $merge_fields_item->label(),
      '#description' => $this->t('Label for the Merge Fields Item.'),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $merge_fields_item->id(),
      '#machine_name' => [
        'exists' => [$this, 'exist'],
      ],
      '#disabled' => !$merge_fields_item->isNew(),
    ];

    $form['token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Token'),
      '#maxlength' => 255,
      '#default_value' => $merge_fields_item->getToken(),
      '#description' => $this->t('The token to use for this merge field. Use the token browser above to find available tokens.'),
      '#required' => TRUE,
    ];

    // Add token tree link and browser.
    $form['token_tree'] = [
      '#theme' => 'token_tree_link',
      '#token_types' => ['user', 'site', 'node'],
      '#global_types' => TRUE,
      '#click_insert' => TRUE,
      '#dialog' => TRUE,
    ];

    // Add default value field.
    $form['default_value'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Default value'),
      '#rows' => 3,
      '#default_value' => $merge_fields_item->getDefaultValue(),
      '#description' => $this->t('The default value for this merge field.'),
    ];

    // Get all available groups.
    $groups = $this->entityTypeManager->getStorage('merge_fields_group')->loadMultiple();
    $group_options = ['_other' => $this->t('- None -')];
    foreach ($groups as $group) {
      $group_options[$group->id()] = $group->label();
    }

    $form['group'] = [
      '#type' => 'select',
      '#title' => $this->t('Group'),
      '#options' => $group_options,
      '#default_value' => $merge_fields_item->getGroup(),
      '#description' => $this->t('The group this merge field belongs to.'),
    ];

    $form['weight'] = [
      '#type' => 'weight',
      '#title' => $this->t('Weight'),
      '#default_value' => $merge_fields_item->getWeight(),
      '#description' => $this->t('The weight of this merge field in relation to other merge fields in the same group.'),
    ];

    $options = ['text' => $this->t('Text')];
    if ($this->coreLibraryVersionChecker->isLibraryVersionHigherOrEqual('43.1.0')) {
      $options['block'] = $this->t('Block');
    }
    if ($this->coreLibraryVersionChecker->isLibraryVersionHigherOrEqual('44.2.0')) {
      $options['image'] = $this->t('Image');
    }
    // Add type select field.
    $form['type'] = [
      '#type' => 'select',
      '#title' => $this->t('Type'),
      '#options' => $options,
      '#default_value' => $merge_fields_item->getType(),
      '#description' => $this->t('The type of this merge field.'),
      '#required' => TRUE,
    ];

    // Add height field for block and image types.
    $form['height'] = [
      '#type' => 'number',
      '#title' => $this->t('Height'),
      '#default_value' => $merge_fields_item->getHeight(),
      '#description' => $this->t('The height of this merge field (for block and image types).'),
      '#min' => 1,
      '#states' => [
        'visible' => [
          [
            ':input[name="type"]' => ['value' => 'block'],
          ],
          [
            ':input[name="type"]' => ['value' => 'image'],
          ],
        ],
      ],
    ];

    // Add width field for image type.
    $form['width'] = [
      '#type' => 'number',
      '#title' => $this->t('Width'),
      '#default_value' => $merge_fields_item->getWidth(),
      '#description' => $this->t('The width of this merge field (for image type).'),
      '#min' => 1,
      '#states' => [
        'visible' => [
          ':input[name="type"]' => ['value' => 'image'],
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $merge_fields_item = $this->entity;
    $status = $merge_fields_item->save();

    if ($status) {
      $this->messenger()->addStatus($this->t('Saved the %label Merge Fields Item.', [
        '%label' => $merge_fields_item->label(),
      ]));
    }
    else {
      $this->messenger()->addError($this->t('The %label Merge Fields Item was not saved.', [
        '%label' => $merge_fields_item->label(),
      ]));
    }

    $form_state->setRedirectUrl($merge_fields_item->toUrl('collection'));
  }

  /**
   * Helper function to check whether a Merge Fields Item configuration entity exists.
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
