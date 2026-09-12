<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types = 1);

namespace Drupal\ckeditor5_premium_features_ai_assistant\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * CKEditor 5 AI Commands group form.
 *
 * @property \Drupal\ckeditor5_premium_features_ai_assistant\CKEditor5AiCommandGroupInterface $entity
 */
class CKEditor5AiCommandGroupForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {

    $form = parent::form($form, $form_state);
    $availableFormats = $this->getAvailableTextFormats();

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $this->entity->label(),
      '#description' => $this->t('Label for the CKEditor 5 AI commands group.'),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $this->entity->id(),
      '#machine_name' => [
        'exists' => '\Drupal\ckeditor5_premium_features_ai_assistant\Entity\CKEditor5AiCommandGroup::load',
      ],
      '#disabled' => !$this->entity->isNew(),
    ];

    $form['textFormats'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Available For'),
      '#default_value' => $this->entity->get('textFormats') ?? [],
      '#options' => $availableFormats,
      '#required' => TRUE,
    ];
    if (!$this->entity->isNew()) {
      $form['new_command'] = [
        'data' => [
          [
            'add' => [
              '#type' => 'submit',
              '#button_type' => 'primary',
              '#value' => $this->t('Add new command'),
              '#submit' => ['::addCommand'],
            ],
          ],
        ],

      ];

      $form['commands_table'] = [
        '#type' => 'table',
        '#header' => [
          $this->t('Id'),
          $this->t('Operations'),
          $this->t('Weight'),
        ],
        '#tabledrag' => [
          [
            'action' => 'order',
            'relationship' => 'sibling',
            'group' => 'weight',
          ],
        ],
        '#attributes' => [
          'id' => 'ai-commands',
        ],
        '#empty' => $this->t('There are currently no AI Commands.'),
        '#weight' => 5,
      ];

      $commands = $this->entity->get('commands');
      if ($commands) {
        foreach ($commands as $command) {
          $key = $command['uuid'];
          $label = $command['label'];
          $form['commands_table'][$key]['#attributes']['class'][] = 'draggable';
          $form['commands_table'][$key]['#weight'] = $command['weight'];
          $form['commands_table'][$key]['command'] = [
            '#tree' => FALSE,
            'data' => [
              'label' => [
                '#plain_text' => $label,
              ],
            ],
          ];

          $links['edit'] = [
            'title' => $this->t('Edit'),
            'url' => Url::fromRoute('ckeditor5_ai_command.edit_form', [
              'ckeditor5_ai_command_group' => $this->entity->id(),
              'uuid' => $key,
            ]),
          ];
          $links['delete'] = [
            'title' => $this->t('Delete'),
            'url' => Url::fromRoute('ckeditor5_ai_command.delete_form', [
              'ckeditor5_ai_command_group' => $this->entity->id(),
              'uuid' => $key,
            ]),
          ];
          $form['commands_table'][$key]['operations'] = [
            '#type' => 'operations',
            '#links' => $links,
          ];
          $form['commands_table'][$key]['weight'] = [
            '#type' => 'weight',
            '#title' => $this->t('Weight for @title', ['@title' => $label]),
            '#title_display' => 'invisible',
            '#default_value' => $command['weight'],
            '#attributes' => [
              'class' => ['weight'],
            ],
          ];
        }
      }
    }

    return $form;
  }

  /**
   * Add new command to the group.
   *
   * @param $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function addCommand($form, FormStateInterface $form_state): void {

    $form_state->setRedirect(
      'ckeditor5_ai_command.add_form',
      [
        'ckeditor5_ai_command_group' => $this->entity->id(),
      ],
    );
    $form_state->setIgnoreDestination();
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    if ($this->entity->isNew()) {
      $form_state->setRedirectUrl($this->entity->toUrl('edit-form'));
    }
    else {
      $form_state->setRedirectUrl($this->entity->toUrl('collection'));
    }
    $result = parent::save($form, $form_state);
    $message_args = ['%label' => $this->entity->label()];
    $message = $result == SAVED_NEW
      ? $this->t('Created new CKEditor 5 AI Commands Group %label.', $message_args)
      : $this->t('Updated CKEditor 5 AI Commands Group %label.', $message_args);
    $this->messenger()->addStatus($message);
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if (!$this->entity->isNew()) {
      $values = $form_state->cleanValues()->getValues();
      if (!empty($values['commands_table'])) {
        $this->entity->updateWeights($values['commands_table']);
      }
    }
    parent::submitForm($form, $form_state);
  }

  /**
   * Gets list of TextFormats with CKEditor5.
   */
  protected function getAvailableTextFormats(): array {
    $availableFormats = [];
    $filterFormats = filter_formats();
    foreach ($filterFormats as $format) {
      $editor = $this->entityTypeManager->getStorage('editor')->load($format->id());
      if ($editor && $editor->getEditor() === 'ckeditor5') {
        $availableFormats[$format->id()] = $format->label();
      }
    }
    return $availableFormats;
  }

}
