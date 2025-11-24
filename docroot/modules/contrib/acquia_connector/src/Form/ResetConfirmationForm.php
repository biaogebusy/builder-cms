<?php

namespace Drupal\acquia_connector\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Cloud Defaults reset confirmation form.
 *
 * Called when a user attempts to override a subscription on AH and resets it.
 */
class ResetConfirmationForm extends ConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to reset your credentials?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('acquia_connector.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'connector_reset_confirmation_form';
  }

  /**
   * Reset's the credentials by deleting the override from state.
   *
   * Note, this method is implemented in submitForm on the Settings Form.
   * See @Drupal\acquia_connector\Form\SettingsForm.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

}
