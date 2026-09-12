<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types = 1);

namespace Drupal\ckeditor5_premium_features_ai_assistant\Form;

use Drupal\ckeditor5_premium_features_ai_assistant\Entity\CKEditor5AiCommandGroup;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * CKEditor 5 AI Command add form.
 */
class CKEditor5AiCommandAddForm extends FormBase {

  /**
   * Command group entity.
   *
   * @var \Drupal\ckeditor5_premium_features_ai_assistant\Entity\CKEditor5AiCommandGroup|null
   */
  protected ?CKEditor5AiCommandGroup $commandGroup;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ckeditor5_ai_command_add_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?CKEditor5AiCommandGroup $ckeditor5_ai_command_group = NULL, ?string $uuid = NULL): array {
    $this->commandGroup = $ckeditor5_ai_command_group;
    $command = [];
    if ($uuid) {
      $command = $this->commandGroup->getCommandByUuid($uuid);
    }
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $command['label'] ?? '',
      '#description' => $this->t('Label for the CKEditor 5 AI command.'),
      '#required' => TRUE,
    ];

    $form['command_id'] = [
      '#type' => 'machine_name',
      '#label' => 'Command id',
      '#default_value' => $command['command_id'] ?? '',
      '#machine_name' => [
        'source' => ['label'],
        'exists' => [$this, 'exists'],
      ],
      '#maxlength' => 32,
    ];

    $form['prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Prompt'),
      '#required' => TRUE,
      '#default_value' => $command['prompt'] ?? '',
      '#description' => $this->t('Description of the CKEditor 5 AI command.'),
    ];

    $form['weight'] = [
      '#type' => 'weight',
      '#title_display' => 'invisible',
      "#disabled" => TRUE,
      "#access" => FALSE,
      '#default_value' => $command['weight'] ?? 0,
    ];

    $form['uuid'] = [
      '#type' => 'string',
      '#title_display' => 'invisible',
      "#disabled" => TRUE,
      "#access" => FALSE,
      '#default_value' => $command['uuid'] ?? '',
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#button_type' => 'primary',
    ];

    $form['actions']['submit']['#value'] = $this->t('Add Command');

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->cleanValues()->getValues();
    $values['weight'] = 0;
    $values['command_id'] = strip_tags(str_replace(' ', '_', $values['command_id']));
    $this->commandGroup->addCommand($values);
    $form_state->setRedirectUrl($this->commandGroup->toUrl('edit-form'));
  }

  /**
   * Determines if the command already exists.
   *
   * @param string $id
   *   The commad ID.
   *
   * @return bool
   *   TRUE if the command exists, FALSE otherwise.
   */
  public function exists(string $id): bool {
    return $this->commandGroup->commandExists($id);
  }

}
