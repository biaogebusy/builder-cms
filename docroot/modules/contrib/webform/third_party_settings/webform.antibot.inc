<?php

/**
 * @file
 * Integrates third party settings on the Antibot module's behalf.
 */

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\LegacyHook;
use Drupal\Core\Url;
use Drupal\webform\Hook\WebformAntibotHooks;

/**
 * Flag to indicate that antibot can be set.
 */
define('WEBFORM_ANTIBOT_NEUTRAL', -1);

/**
 * Flag to indicate that antibot is disabled for all webforms.
 */
define('WEBFORM_ANTIBOT_DISABLED_WEBFORM', 2);

/**
 * Flag to indicate that antibot is enabled for all webforms.
 */
define('WEBFORM_ANTIBOT_ENABLED_WEBFORM', 3);

/**
 * Determine if antibot is enabled for webform submissions.
 *
 * @return bool
 *   TRUE if antibot is enabled for webform submissions.
 */
function _webform_antibot_enabled() {
  /** @var \Drupal\Core\Path\PathMatcherInterface $path_matcher */
  $path_matcher = \Drupal::service('path.matcher');
  $form_ids = \Drupal::config('antibot.settings')->get('form_ids') ?? [];
  return $path_matcher->matchPath('webform_submission_', implode("\n", $form_ids));
}

/**
 * Alter webform third party settings webforms to include Antibot configuration.
 *
 * @param array $form
 *   An associative array containing the structure of the form.
 * @param \Drupal\Core\Form\FormStateInterface $form_state
 *   The current state of the form.
 * @param bool $antibot
 *   TRUE if antibot protection is enabled.
 * @param int $antibot_state
 *   Flag that determines if antibot protection is enabled, disabled, or can be
 *   set.
 * @param string|\Drupal\Core\StringTranslation\TranslatableMarkup $label
 *   The label to displayed within the checkbox titles.
 */
function _webform_antibot_form(array &$form, FormStateInterface $form_state, $antibot, $antibot_state, $label) {
  if (_webform_antibot_enabled()) {
    return;
  }

  $t_args = [
    '%label' => $label,
    ':href_antibot' => Url::fromRoute('antibot.settings')->toString(),
    ':href_webform' => Url::fromRoute('webform.config')->toString(),
  ];

  // Antibot.
  $form['third_party_settings']['antibot'] = [
    '#type' => 'details',
    '#title' => t('Antibot'),
    '#open' => TRUE,
    '#description' => t('Prevent SPAM webform submissions from being submitted without JavaScript enabled using the <a href=":href_antibot">antibot</a> method.', $t_args),
  ];
  $form['third_party_settings']['antibot']['antibot'] = [
    '#type' => 'checkbox',
    '#title' => t('Protect %label with Antibot', $t_args),
    '#default_value' => $antibot,
    '#return_value' => TRUE,
  ];

  $antibot_state = (int) $antibot_state;
  if ($antibot_state !== WEBFORM_ANTIBOT_NEUTRAL) {
    $form['third_party_settings']['antibot']['antibot']['#attributes']['disabled'] = 'disabled';
    $form_state->set('antibot_disabled', TRUE);
    if ($antibot_state === WEBFORM_ANTIBOT_ENABLED_WEBFORM) {
      $form['third_party_settings']['antibot']['antibot']['#default_value'] = 1;
      $form['third_party_settings']['antibot']['antibot']['#description'] = t('<a href=":href_webform">Antibot protection</a> is enabled for all webforms.', $t_args);
    }
  }

  $form['#validate'][] = '_webform_antibot_form_validate';
}

/**
 * Validate callback; Checks if antibot is disabled and remove it from the third party settings values.
 */
function _webform_antibot_form_validate(&$form, FormStateInterface $form_state) {
  $third_party_settings = $form_state->getValue('third_party_settings');
  if ($form_state->get('antibot_disabled') || empty($third_party_settings['antibot']['antibot'])) {
    unset($third_party_settings['antibot']['antibot']);
  }
  $form_state->setValue('third_party_settings', $third_party_settings);
}

/**
 * Implements hook_webform_admin_third_party_settings_form_alter().
 */
#[LegacyHook]
function antibot_webform_admin_third_party_settings_form_alter(&$form, FormStateInterface $form_state) {
  \Drupal::service(WebformAntibotHooks::class)->antibotWebformAdminThirdPartySettingsFormAlter($form, $form_state);
}

/**
 * Implements hook_webform_third_party_settings_form_alter().
 */
#[LegacyHook]
function antibot_webform_third_party_settings_form_alter(&$form, FormStateInterface $form_state) {
  \Drupal::service(WebformAntibotHooks::class)->antibotWebformThirdPartySettingsFormAlter($form, $form_state);
}

/**
 * Implements hook_webform_submission_form_alter().
 */
#[LegacyHook]
function antibot_webform_submission_form_alter(&$form, FormStateInterface $form_state, $form_id) {
  return \Drupal::service(WebformAntibotHooks::class)->antibotWebformSubmissionFormAlter($form, $form_state, $form_id);
}
