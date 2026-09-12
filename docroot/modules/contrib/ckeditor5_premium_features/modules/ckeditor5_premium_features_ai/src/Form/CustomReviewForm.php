<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_ai\Form;

use Drupal\ckeditor5_premium_features_ai\Utility\ApiAdapter;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for Custom Review add/edit forms.
 *
 * @property \Drupal\ckeditor5_premium_features_ai\Entity\CustomReview $entity
 */
final class CustomReviewForm extends EntityForm {

  /** @var \Drupal\ckeditor5_premium_features_ai\Utility\ApiAdapter */
  private ApiAdapter $apiAdapter;

  /** @var \Drupal\Core\Entity\EntityTypeManagerInterface */
  private EntityTypeManagerInterface $etm;

  public function __construct(ApiAdapter $apiAdapter, EntityTypeManagerInterface $entityTypeManager) {
    $this->apiAdapter = $apiAdapter;
    $this->etm = $entityTypeManager;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('ckeditor5_premium_features_ai.api_adapter'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $this->entity->label(),
      '#required' => TRUE,
      '#description' => $this->t('The name shown to editors in the AI Reviews menu.'),
    ];

    $form['description'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Description'),
      '#maxlength' => 255,
      '#default_value' => $this->entity->get('description') ?? '',
      '#description' => $this->t('The description of the review command.'),
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $this->entity->id(),
      '#machine_name' => [
        'exists' => '\\Drupal\\ckeditor5_premium_features_ai\\Entity\\CustomReview::load',
      ],
      '#disabled' => !$this->entity->isNew(),
    ];

    $form['prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Prompt'),
      '#default_value' => $this->entity->get('prompt') ?? '',
      '#required' => TRUE,
    ];

    $form['model'] = [
      '#type' => 'select',
      '#title' => $this->t('Model'),
      '#options' => $this->getModelOptions(),
      '#default_value' => $this->entity->get('model') ?? 'agent',
    ];

    $form['textFormats'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Available for text formats'),
      '#options' => $this->getCkeditor5EnabledTextFormats(),
      '#default_value' => $this->entity->get('textFormats') ?? [],
      '#description' => $this->t('Select which text formats can use this review.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    parent::submitForm($form, $form_state);
    $form_state->setRedirect('entity.ckeditor5_ai_custom_review.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int|null {
    $values = $form_state->getValues();
    // Remove unchecked checkboxes.
    if (isset($values['textFormats']) && is_array($values['textFormats'])) {
      $values['textFormats'] = array_values(array_filter($values['textFormats']));
      $this->entity->set('textFormats', $values['textFormats']);
    }
    $this->entity->set('id', $values['id'] ?? NULL);
    $this->entity->set('description', $values['description'] ?? NULL);
    $this->entity->set('prompt', $values['prompt'] ?? NULL);
    $this->entity->set('model', $values['model'] ?? NULL);
    return $this->entity->save();
  }

  private function getModelOptions(): array {
    $options = [];
    $response = $this->apiAdapter->getModels('1');
    $models = $response['models'] ?? $response ?? [];
    if (empty($models) || !isset($models['items'])) {
      return $options;
    }
    foreach ($models['items'] as $item) {
      if (is_array($item)) {
        $id = $item['id'] ?? ($item['name'] ?? NULL);
        $label = $item['label'] ?? ($item['name'] ?? $id);
        if ($id) {
          $options[(string) $id] = (string) $label;
        }
      }
    }
    asort($options);
    return $options;
  }

  private function getCkeditor5EnabledTextFormats(): array {
    $options = [];
    $formats = $this->etm->getStorage('filter_format')->loadMultiple();
    foreach ($formats as $format) {
      /** @var \Drupal\filter\Entity\FilterFormat $format */
      $editor = $this->etm->getStorage('editor')->load($format->id());
      if ($editor && $editor->get('editor') === 'ckeditor5') {
        $options[$format->id()] = $format->label();
      }
    }
    return $options;
  }
}
