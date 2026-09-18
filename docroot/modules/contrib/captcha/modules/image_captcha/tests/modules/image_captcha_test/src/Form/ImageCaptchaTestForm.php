<?php

namespace Drupal\image_captcha_test\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\image_captcha\Constants\ImageCaptchaConstants;

/**
 * Provides a Image Captcha test form.
 */
class ImageCaptchaTestForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'image_captcha_test_test';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['image_captcha'] = [
      '#type' => 'captcha',
      '#captcha_type' => ImageCaptchaConstants::IMAGE_CAPTCHA_CAPTCHA_TYPE,
      '#captcha_admin_mode' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->messenger()->addStatus($this->t('Form submitted!'));
    $form_state->setRedirect('<front>');
  }

}
