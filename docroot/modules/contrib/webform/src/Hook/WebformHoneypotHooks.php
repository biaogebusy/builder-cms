<?php

namespace Drupal\webform\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for webform.
 */
class WebformHoneypotHooks {
  use StringTranslationTrait;

  /**
   * Implements hook_webform_admin_third_party_settings_form_alter().
   */
  #[Hook('webform_admin_third_party_settings_form_alter', module: 'honeypot')]
  public function honeypotWebformAdminThirdPartySettingsFormAlter(&$form, FormStateInterface $form_state) {
    /** @var \Drupal\webform\WebformThirdPartySettingsManagerInterface $third_party_settings_manager */
    $third_party_settings_manager = \Drupal::service('webform.third_party_settings_manager');
    $honeypot = (int) $third_party_settings_manager->getThirdPartySetting('honeypot', 'honeypot');
    $honeypot_state = \Drupal::config('honeypot.settings')->get('protect_all_forms') ? WEBFORM_HONEYPOT_ENABLED_ALL : WEBFORM_HONEYPOT_NEUTRAL;
    $honeypot_time_limit = (int) \Drupal::config('honeypot.settings')->get('time_limit');
    $time_restriction = (int) $third_party_settings_manager->getThirdPartySetting('honeypot', 'time_restriction');
    $time_restriction_state = $honeypot_time_limit === 0 ? WEBFORM_HONEYPOT_DISABLED_ALL : WEBFORM_HONEYPOT_NEUTRAL;
    _webform_honeypot_form($form, $form_state, $honeypot, $honeypot_state, $time_restriction, $time_restriction_state, $this->t('all webforms'));
  }

  /**
   * Implements hook_webform_third_party_settings_form_alter().
   */
  #[Hook('webform_third_party_settings_form_alter', module: 'honeypot')]
  public function honeypotWebformThirdPartySettingsFormAlter(&$form, FormStateInterface $form_state) {
    /** @var \Drupal\webform\WebformThirdPartySettingsManagerInterface $third_party_settings_manager */
    $third_party_settings_manager = \Drupal::service('webform.third_party_settings_manager');
    /** @var \Drupal\webform\WebformInterface $webform */
    $webform = $form_state->getFormObject()->getEntity();
    $honeypot = (int) $webform->getThirdPartySetting('honeypot', 'honeypot');
    if (\Drupal::config('honeypot.settings')->get('protect_all_forms')) {
      $honeypot_state = WEBFORM_HONEYPOT_ENABLED_ALL;
    }
    elseif ($third_party_settings_manager->getThirdPartySetting('honeypot', 'honeypot')) {
      $honeypot_state = WEBFORM_HONEYPOT_ENABLED_WEBFORM;
    }
    else {
      $honeypot_state = WEBFORM_HONEYPOT_NEUTRAL;
    }
    $time_restriction = (int) $webform->getThirdPartySetting('honeypot', 'time_restriction');
    $honeypot_time_limit = (int) \Drupal::config('honeypot.settings')->get('time_limit');
    if ($honeypot_time_limit === 0) {
      $time_restriction_state = WEBFORM_HONEYPOT_DISABLED_ALL;
    }
    elseif ($third_party_settings_manager->getThirdPartySetting('honeypot', 'time_restriction')) {
      $time_restriction_state = WEBFORM_HONEYPOT_ENABLED_WEBFORM;
    }
    else {
      $time_restriction_state = WEBFORM_HONEYPOT_NEUTRAL;
    }
    _webform_honeypot_form($form, $form_state, $honeypot, $honeypot_state, $time_restriction, $time_restriction_state, $this->t('@label webform', ['@label' => $webform->label()]));
  }

  /**
   * Implements hook_webform_submission_form_alter().
   */
  #[Hook('webform_submission_form_alter', module: 'honeypot')]
  public function honeypotWebformSubmissionFormAlter(&$form, FormStateInterface $form_state, $form_id) {
    // Only add a Honeypot when a webform is initially load.
    // After a webform is submitted, via a multi-step webform and/or saving a draft,
    // we can skip adding a Honeypot.
    if ($form_state->isSubmitted()) {
      return;
    }
    /** @var \Drupal\webform\WebformThirdPartySettingsManagerInterface $third_party_settings_manager */
    $third_party_settings_manager = \Drupal::service('webform.third_party_settings_manager');
    /** @var \Drupal\webform\WebformSubmissionInterface $webform_submission */
    $webform_submission = $form_state->getFormObject()->getEntity();
    $webform = $webform_submission->getWebform();
    $options = [];
    $honeypot = (int) $third_party_settings_manager->getThirdPartySetting('honeypot', 'honeypot') ?: $webform->getThirdPartySetting('honeypot', 'honeypot');
    if ($honeypot) {
      $options[] = 'honeypot';
    }
    $time_restriction = (int) $third_party_settings_manager->getThirdPartySetting('honeypot', 'time_restriction') ?: $webform->getThirdPartySetting('honeypot', 'time_restriction');
    if ($time_restriction) {
      $options[] = 'time_restriction';
    }
    if ($options) {
      \Drupal::service('honeypot')->addFormProtection($form, $form_state, $options);
    }
  }

}
