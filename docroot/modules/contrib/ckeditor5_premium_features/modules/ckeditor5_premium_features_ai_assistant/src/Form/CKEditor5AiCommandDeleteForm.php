<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types = 1);

namespace Drupal\ckeditor5_premium_features_ai_assistant\Form;

use Drupal\ckeditor5_premium_features_ai_assistant\Entity\CKEditor5AiCommandGroup;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * CKEditor 5 AI Command delete form.
 */
class CKEditor5AiCommandDeleteForm extends ConfirmFormBase {

  /**
   * Command group entity.
   *
   * @var \Drupal\ckeditor5_premium_features_ai_assistant\Entity\CKEditor5AiCommandGroup|null
   */
  protected ?CKEditor5AiCommandGroup $commandGroup;

  /**
   * @var mixed|null
   */
  protected mixed $commandUuid;

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete the command from the group?');
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
  public function getCancelUrl() {
    return $this->commandGroup->toUrl('edit-form');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?ConfigEntityInterface $ckeditor5_ai_command_group = NULL, ?string $uuid = NULL) {
    $this->commandGroup = $ckeditor5_ai_command_group;
    $this->commandUuid = $uuid;

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->commandGroup->removeCommand($this->commandUuid);
    $this->messenger()->addStatus($this->t('The command has been deleted.'));
    $form_state->setRedirectUrl($this->commandGroup->toUrl('edit-form'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ckeditor5_ai_command_delete_form';
  }

}
