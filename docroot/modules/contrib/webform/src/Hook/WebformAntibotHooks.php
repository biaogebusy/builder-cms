<?php

namespace Drupal\webform\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for webform.
 */
class WebformAntibotHooks {
  use StringTranslationTrait;

  /**
   * Implements hook_webform_admin_third_party_settings_form_alter().
   */
  #[Hook('webform_admin_third_party_settings_form_alter', module: 'antibot')]
  public function antibotWebformAdminThirdPartySettingsFormAlter(&$form, FormStateInterface $form_state) {
    /** @var \Drupal\webform\WebformThirdPartySettingsManagerInterface $third_party_settings_manager */
    $third_party_settings_manager = \Drupal::service('webform.third_party_settings_manager');
    $antibot = $third_party_settings_manager->getThirdPartySetting('antibot', 'antibot');
    $antibot_state = WEBFORM_ANTIBOT_NEUTRAL;
    _webform_antibot_form($form, $form_state, $antibot, $antibot_state, $this->t('all webforms'));
  }

  /**
   * Implements hook_webform_third_party_settings_form_alter().
   */
  #[Hook('webform_third_party_settings_form_alter', module: 'antibot')]
  public function antibotWebformThirdPartySettingsFormAlter(&$form, FormStateInterface $form_state) {
    /** @var \Drupal\webform\WebformThirdPartySettingsManagerInterface $third_party_settings_manager */
    $third_party_settings_manager = \Drupal::service('webform.third_party_settings_manager');
    /** @var \Drupal\webform\WebformInterface $webform */
    $webform = $form_state->getFormObject()->getEntity();
    $antibot = $webform->getThirdPartySetting('antibot', 'antibot');
    if ($third_party_settings_manager->getThirdPartySetting('antibot', 'antibot')) {
      $antibot_state = WEBFORM_ANTIBOT_ENABLED_WEBFORM;
    }
    else {
      $antibot_state = WEBFORM_ANTIBOT_NEUTRAL;
    }
    _webform_antibot_form($form, $form_state, $antibot, $antibot_state, $this->t('@label webform', ['@label' => $webform->label()]));
  }

  /**
   * Implements hook_webform_submission_form_alter().
   */
  #[Hook('webform_submission_form_alter', module: 'antibot')]
  public function antibotWebformSubmissionFormAlter(&$form, FormStateInterface $form_state, $form_id) {
    if (_webform_antibot_enabled()) {
      return;
    }
    // Only add an Antibot when a webform is initially load.
    // After a webform is submitted, via a multi-step webform and/or saving a draft,
    // we can skip adding an Antibot.
    if ($form_state->isSubmitted()) {
      return;
    }
    /** @var \Drupal\webform\WebformThirdPartySettingsManagerInterface $third_party_settings_manager */
    $third_party_settings_manager = \Drupal::service('webform.third_party_settings_manager');
    /** @var \Drupal\webform\WebformSubmissionInterface $webform_submission */
    $webform_submission = $form_state->getFormObject()->getEntity();
    $webform = $webform_submission->getWebform();
    $antibot = $third_party_settings_manager->getThirdPartySetting('antibot', 'antibot') ?: $webform->getThirdPartySetting('antibot', 'antibot');
    if ($antibot) {
      if (function_exists('antibot_protect_form')) {
        // Applies to antibot-8.x-1.2+
        // Set #form_id which is needed by antibot_protect_form().
        $form['#form_id'] = $form_id;
        antibot_protect_form($form);
      }
      else {
        // Applies to antibot-8.x-1.1 and below.
        // @todo Remove backward compatibility for antibot-8.x-1.1.
        $form['#pre_render'][] = 'antibot_form_pre_render';
      }
    }
  }

}
