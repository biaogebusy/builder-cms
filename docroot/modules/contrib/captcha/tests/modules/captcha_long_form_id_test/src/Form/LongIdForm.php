<?php

namespace Drupal\captcha_long_form_id_test\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Test class for long form ids (over 64 chars).
 */
class LongIdForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'this_formid_is_intentionally_longer_than_64_characters_to_test_captcha';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['text_field'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Text Field'),
      '#maxlength' => 64,
      '#size' => 64,
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Display result.
    foreach ($form_state->getValues() as $key => $value) {
      $this->messenger()->addMessage($key . ': ' . $value);
    }

  }

}
