<?php

namespace Drupal\workspace\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\multiversion\Entity\WorkspaceInterface;

/**
 * Handle activation of a workspace on administrative pages.
 */
class WorkspaceActivateForm extends WorkspaceActivateFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'activate';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, WorkspaceInterface $workspace = NULL) {
    $form['workspace_id'] = [
      '#type' => 'hidden',
      '#value' => $workspace->id(),
    ];

    $form['instruction'] = [
      '#type' => 'markup',
      '#prefix' => '<p>',
      '#markup' => $this->t('Would you like to activate the %workspace workspace?', ['%workspace' => $workspace->label()]),
      '#suffix' => '</p>',
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => 'Activate',
    ];

    $form['#title'] = $this->t('Activate workspace %label', ['%label' => $workspace->label()]);

    return $form;
  }

}
