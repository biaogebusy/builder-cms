<?php

/**
 * @file
 * Integrates third party settings on the CAPTCHA module's behalf.
 */

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\LegacyHook;
use Drupal\Core\Url;
use Drupal\webform\Hook\WebformCaptchaHooks;
use Drupal\webform\Utility\WebformElementHelper;

/**
 * Implements hook_webform_admin_third_party_settings_form_alter().
 */
#[LegacyHook]
function captcha_webform_admin_third_party_settings_form_alter(&$form, FormStateInterface $form_state) {
  \Drupal::service(WebformCaptchaHooks::class)->captchaWebformAdminThirdPartySettingsFormAlter($form, $form_state);
}

/**
 * Implements hook_webform_submission_form_alter().
 */
#[LegacyHook]
function captcha_webform_submission_form_alter(&$form, FormStateInterface $form_state, $form_id) {
  return \Drupal::service(WebformCaptchaHooks::class)->captchaWebformSubmissionFormAlter($form, $form_state, $form_id);
}

/**
 * After build callback to add warning to CAPTCHA placement.
 */
function _captcha_webform_submission_form_after_build(array $form, FormStateInterface $form_state) {
  // Make sure 'Add CAPTCHA administration links to forms' is appended to the
  // webform.
  // @see /admin/config/people/captcha
  // @see captcha_form_alter()
  if (!isset($form['captcha']) || !isset($form['captcha']['add_captcha'])) {
    return $form;
  }

  /** @var \Drupal\webform\WebformSubmissionForm $form_object */
  $form_object = $form_state->getFormObject();
  $webform = $form_object->getWebform();

  // Determine if the current user can update this webform via the UI.
  $has_update_access = $webform->access('update')
    && \Drupal::moduleHandler()->moduleExists('webform_ui');

  // If the webform has a CAPTCHA element, display a link to edit the element.
  $elements = $webform->getElementsInitializedAndFlattened();
  foreach ($elements as $key => &$element) {
    if (WebformElementHelper::isType($element, 'captcha')) {
      // Update the details element's title.
      $form['captcha']['#title'] = t('CAPTCHA: challenge enabled');

      // Replace 'Place a CAPTCHA here for untrusted users' link with link to
      // edit CAPTCHA element for this webform.
      if ($has_update_access) {
        $route_name = 'entity.webform_ui.element.edit_form';
        $route_parameters = ['webform' => $webform->id(), 'key' => $key];
        $route_options = ['query' => Drupal::destination()->getAsArray()];
        $form['captcha']['add_captcha'] = [
          '#type' => 'link',
          '#title' => t('Untrusted users will see a CAPTCHA element on this webform.'),
          '#url' => Url::fromRoute($route_name, $route_parameters, $route_options),
          '#prefix' => '<em>',
          '#suffix' => '</em>',
          '#parents' => [],
        ];
      }
      else {
        $form['captcha']['add_captcha'] = [
          '#markup' => t('Untrusted users will see a CAPTCHA element on this webform.'),
          '#prefix' => '<em>',
          '#suffix' => '</em>',
        ];
      }
      return $form;
    }
  }

  // Replace 'Place a CAPTCHA here for untrusted users' link with link to
  // add CAPTCHA element to this webform.
  if ($has_update_access) {
    $route_name = 'entity.webform_ui.element.add_form';
    $route_parameters = ['webform' => $webform->id(), 'type' => 'captcha'];
    $route_options = ['query' => Drupal::destination()->getAsArray()];
    $form['captcha']['add_captcha'] = [
      '#type' => 'link',
      '#title' => t('Add CAPTCHA element to this webform for untrusted users.'),
      '#url' => Url::fromRoute($route_name, $route_parameters, $route_options),
      '#parents' => [],
    ];
  }
  else {
    $form['captcha']['add_captcha'] = [
      '#type' => 'webform_message',
      '#message_message' => t('CAPTCHA should be added as an element to this webform.'),
      '#message_type' => 'warning',
    ];
  }
  return $form;
}
