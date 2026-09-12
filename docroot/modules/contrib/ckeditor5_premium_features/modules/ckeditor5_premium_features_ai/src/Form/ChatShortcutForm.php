<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_ai\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for Chat Shortcut add/edit forms.
 *
 * @property \Drupal\ckeditor5_premium_features_ai\Entity\ChatShortcut $entity
 */
final class ChatShortcutForm extends EntityForm {

  /** @var \Drupal\Core\Entity\EntityTypeManagerInterface */
  private EntityTypeManagerInterface $etm;

  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->etm = $entityTypeManager;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
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
      '#description' => $this->t('The name shown to editors in the AI Chat shortcuts.'),
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $this->entity->id(),
      '#machine_name' => [
        'exists' => '\\Drupal\\ckeditor5_premium_features_ai\\Entity\\ChatShortcut::load',
      ],
      '#disabled' => !$this->entity->isNew(),
    ];

    $form['type'] = [
      '#type' => 'select',
      '#title' => $this->t('Type'),
      '#options' => [
        'chat' => $this->t('Chat'),
        'review' => $this->t('Review'),
        'translate' => $this->t('Translate'),
      ],
      '#default_value' => $this->entity->get('type') ?? 'chat',
      '#required' => TRUE,
    ];

    $form['prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Prompt'),
      '#default_value' => $this->entity->get('prompt') ?? '',
      '#states' => [
        'visible' => [
          ':input[name="type"]' => ['value' => 'chat'],
        ],
        'required' => [
          ':input[name="type"]' => ['value' => 'chat'],
        ],
      ],
    ];

    $form['useReasoning'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use Reasoning'),
      '#default_value' => $this->entity->get('useReasoning') ?? FALSE,
      '#states' => [
        'visible' => [
          ':input[name="type"]' => ['value' => 'chat'],
        ],
      ],
    ];

    $form['useWebSearch'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use Web Search'),
      '#default_value' => $this->entity->get('useWebSearch') ?? FALSE,
      '#states' => [
        'visible' => [
          ':input[name="type"]' => ['value' => 'chat'],
        ],
      ],
    ];

    $form['commandId'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Command ID'),
      '#default_value' => $this->entity->get('commandId') ?? '',
      '#states' => [
        'visible' => [
          ':input[name="type"]' => ['value' => 'review'],
        ],
        'required' => [
          ':input[name="type"]' => ['value' => 'review'],
        ],
      ],
    ];

    $form['textFormats'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Available for text formats'),
      '#options' => $this->getCkeditor5EnabledTextFormats(),
      '#default_value' => $this->entity->get('textFormats') ?? [],
      '#description' => $this->t('Select which text formats can use this shortcut.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    parent::submitForm($form, $form_state);
    $form_state->setRedirect('entity.ckeditor5_ai_chat_shortcut.collection');
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
    $this->entity->set('label', $values['label'] ?? NULL);
    $this->entity->set('type', $values['type'] ?? NULL);

    if ($values['type'] === 'chat') {
      $this->entity->set('prompt', $values['prompt'] ?? NULL);
      $this->entity->set('useReasoning', (bool) ($values['useReasoning'] ?? FALSE));
      $this->entity->set('useWebSearch', (bool) ($values['useWebSearch'] ?? FALSE));
      $this->entity->set('commandId', NULL);
    }
    elseif ($values['type'] === 'review') {
      $this->entity->set('commandId', $values['commandId'] ?? NULL);
      $this->entity->set('prompt', NULL);
      $this->entity->set('useReasoning', NULL);
      $this->entity->set('useWebSearch', NULL);
    }
    else {
      $this->entity->set('prompt', NULL);
      $this->entity->set('useReasoning', NULL);
      $this->entity->set('useWebSearch', NULL);
      $this->entity->set('commandId', NULL);
    }

    return $this->entity->save();
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
