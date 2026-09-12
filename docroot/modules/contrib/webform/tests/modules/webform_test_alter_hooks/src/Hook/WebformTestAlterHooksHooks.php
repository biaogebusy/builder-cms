<?php

namespace Drupal\webform_test_alter_hooks\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for webform_test_alter_hooks.
 */
class WebformTestAlterHooksHooks {
  /* ************************************************************************** */
  // Form hooks.
  /* ************************************************************************** */

  /**
   * Implements hook_form_alter().
   */
  #[Hook('form_alter')]
  public function formAlter(&$form, FormStateInterface $form_state, $form_id) {
    if (str_starts_with($form_id, 'webform_')) {
      \Drupal::messenger()->addStatus(t("@hook: '@form_id' executed.", ['@hook' => 'hook_form_alter()', '@form_id' => $form_id]), TRUE);
    }
  }

  /**
   * Implements hook_form_webform_submission_form_alter().
   */
  #[Hook('form_webform_submission_form_alter')]
  public function formWebformSubmissionFormAlter(array $form, FormStateInterface $form_state, $form_id) {
    \Drupal::messenger()->addStatus(t("@hook: '@form_id' executed.", ['@hook' => 'hook_form_webform_submission_form_alter()', '@form_id' => $form_id]), TRUE);
  }

  /**
   * Implements hook_webform_submission_form_alter().
   *
   * @see \Drupal\webform\WebformSubmissionForm::buildForm
   */
  #[Hook('webform_submission_form_alter')]
  public function webformSubmissionFormAlter(array &$form, FormStateInterface $form_state, $form_id) {
    \Drupal::messenger()->addStatus(t("@hook: '@form_id' executed.", ['@hook' => 'hook_webform_submission_form_alter()', '@form_id' => $form_id]), TRUE);
  }

  /* ************************************************************************** */
  // Element hooks.
  /* ************************************************************************** */

  /**
   * Implements hook_webform_element_alter().
   *
   * @see webform.api.php
   * @see \Drupal\webform\WebformSubmissionForm::prepareElements
   */
  #[Hook('webform_element_alter')]
  public function webformElementAlter(array &$element, FormStateInterface $form_state, array $context) {
    \Drupal::messenger()->addStatus(t("@hook: '@webform_key' executed.", [
      '@hook' => 'hook_webform_element_alter()',
      '@webform_key' => $element['#webform_key'],
    ]), TRUE);
  }

}
