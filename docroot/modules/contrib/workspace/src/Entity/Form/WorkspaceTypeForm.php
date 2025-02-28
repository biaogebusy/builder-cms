<?php

namespace Drupal\workspace\Entity\Form;

use Drupal\Core\Entity\BundleEntityFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class WorkspaceTypeForm.
 *
 * @package Drupal\workspace\Entity\Form
 */
class WorkspaceTypeForm extends BundleEntityFormBase {
  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $workspace_type = $this->entity;
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $workspace_type->label(),
      '#description' => $this->t("Label for the Workspace type."),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $workspace_type->id(),
      '#machine_name' => [
        'exists' => '\Drupal\multiversion\Entity\WorkspaceType::load',
      ],
      '#disabled' => !$workspace_type->isNew(),
    ];

    return $this->protectBundleIdElement($form);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $workspace_type = $this->entity;
    $status = $workspace_type->save();

    switch ($status) {
      case SAVED_NEW:
        drupal_set_message($this->t('Created the %label Workspace type.', [
          '%label' => $workspace_type->label(),
        ]));
        break;

      default:
        drupal_set_message($this->t('Saved the %label Workspace type.', [
          '%label' => $workspace_type->label(),
        ]));
    }
    $form_state->setRedirectUrl($workspace_type->toUrl('collection'));
  }

}