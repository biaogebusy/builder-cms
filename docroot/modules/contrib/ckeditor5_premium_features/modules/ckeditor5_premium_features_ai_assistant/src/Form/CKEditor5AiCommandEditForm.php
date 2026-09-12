<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types = 1);

namespace Drupal\ckeditor5_premium_features_ai_assistant\Form;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * CKEditor 5 AI Command edit form.
 */
class CKEditor5AiCommandEditForm extends CKEditor5AiCommandAddForm {

  /**
   * Command uuid.
   *
   * @var string|null
   */
  protected ?string $commandUuid;

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?ConfigEntityInterface $ckeditor5_ai_command_group = NULL, ?string $uuid = NULL): array {
    $form = parent::buildForm($form, $form_state, $ckeditor5_ai_command_group, $uuid);
    $form['actions']['submit']['#value'] = $this->t('Update Command');
    $this->commandUuid = $uuid;
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->cleanValues()->getValues();
    $values['command_id'] = strip_tags(str_replace(' ', '_', $values['command_id']));
    $values['uuid'] = $this->commandUuid;
    $this->commandGroup->updateCommand($values);
    $form_state->setRedirectUrl($this->commandGroup->toUrl('edit-form'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ckeditor5_ai_command_edit_form';
  }

}
