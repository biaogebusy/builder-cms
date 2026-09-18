<?php

namespace Drupal\captcha_test\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\captcha\Constants\CaptchaConstants;

/**
 * Provides a Captcha test form.
 */
class TestForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'captcha_test_test';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['math_captcha_admin_true'] = [
      '#type' => 'captcha',
      '#captcha_type' => CaptchaConstants::CAPTCHA_MATH_CAPTCHA_TYPE,
      '#captcha_admin_mode' => TRUE,
    ];

    $form['math_captcha_admin_false'] = [
      '#type' => 'captcha',
      '#captcha_type' => CaptchaConstants::CAPTCHA_MATH_CAPTCHA_TYPE,
      '#captcha_admin_mode' => FALSE,
    ];

    $form['math_captcha_admin_not_set'] = [
      '#type' => 'captcha',
      '#captcha_type' => CaptchaConstants::CAPTCHA_MATH_CAPTCHA_TYPE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->messenger()->addStatus($this->t('Form submitted!'));
  }

}
