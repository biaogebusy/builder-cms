<?php

/**
 * @file
 * Integrates third party settings for the Webform module.
 */

use Drupal\Core\Hook\Attribute\LegacyHook;
use Drupal\webform_test_third_party_settings\Hook\WebformTestThirdPartySettingsWebformHooks;
use Drupal\Core\Form\FormStateInterface;

/**
 * Implements hook_webform_admin_third_party_settings_form_alter().
 */
#[LegacyHook]
function webform_test_third_party_settings_webform_admin_third_party_settings_form_alter(&$form, FormStateInterface $form_state) {
  \Drupal::service(WebformTestThirdPartySettingsWebformHooks::class)->webformAdminThirdPartySettingsFormAlter($form, $form_state);
}

/**
 * Implements hook_webform_third_party_settings_form_alter().
 */
#[LegacyHook]
function webform_test_third_party_settings_webform_third_party_settings_form_alter(&$form, FormStateInterface $form_state) {
  \Drupal::service(WebformTestThirdPartySettingsWebformHooks::class)->webformThirdPartySettingsFormAlter($form, $form_state);
}

/**
 * Validate callback; Cleans up third party settings.
 */
function _webform_test_third_party_settings_form_validate(&$form, FormStateInterface $form_state) {
  $third_party_settings = $form_state->getValue('third_party_settings');
  if (empty($third_party_settings['webform_test_third_party_settings']['message'])) {
    $third_party_settings['webform_test_third_party_settings'] = NULL;
  }
  $form_state->setValue('third_party_settings', $third_party_settings);
}

/**
 * Implements hook_webform_submission_form_alter().
 */
#[LegacyHook]
function webform_test_third_party_settings_webform_submission_form_alter(&$form, FormStateInterface $form_state, $form_id) {
  return \Drupal::service(WebformTestThirdPartySettingsWebformHooks::class)->webformSubmissionFormAlter($form, $form_state, $form_id);
}
