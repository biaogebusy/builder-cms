<?php

namespace Drupal\webform_clientside_validation\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for webform_clientside_validation.
 */
class WebformClientsideValidationHooks {

  /**
   * Implements hook_webform_submission_form_alter().
   */
  #[Hook('webform_submission_form_alter')]
  public function webformSubmissionFormAlter(array &$form, FormStateInterface $form_state, $form_id) {
    if (\Drupal::moduleHandler()->moduleExists('inline_form_errors')) {
      $form['#attached']['library'][] = 'webform_clientside_validation/webform_clientside_validation.ife';
    }
    if (isset($form['#attributes']['novalidate'])) {
      $form['#attributes']['data-webform-clientside-validation-novalidate'] = TRUE;
      $form['#attached']['library'][] = 'webform_clientside_validation/webform_clientside_validation.novalidate';
    }
  }

  /**
   * Implements hook_element_info_alter().
   */
  #[Hook('element_info_alter')]
  public function elementInfoAlter(array &$info) {
    if (isset($info['webform_email_confirm'])) {
      $info['webform_email_confirm']['#process'][] = '_webform_clientside_validation_webform_email_confirm_process';
    }
  }

}
