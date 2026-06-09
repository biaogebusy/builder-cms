<?php

namespace Drupal\test_ai_vdb_provider_mysql\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure OpenAI API access.
 */
class SetupForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'test_ai_vdb_provider_mysql';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'test_ai_vdb_provider_mysql.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['hello'] = [
      '#markup' => $this->t('This is a test form.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // We don't do anything.
    $form['hello'] = 'test';
    parent::submitForm($form, $form_state);
  }

}
