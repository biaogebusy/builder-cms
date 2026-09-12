<?php

/**
 * @file
 * Webform module form alter hooks.
 */

use Drupal\Core\Hook\Attribute\LegacyHook;
use Drupal\webform\Hook\WebformFormAlterHooks;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\webform\Element\WebformMessage;

/**
 * Implements hook_form_alter().
 */
#[LegacyHook]
function webform_form_alter(&$form, FormStateInterface $form_state, $form_id) {
  return \Drupal::service(WebformFormAlterHooks::class)->formAlter($form, $form_state, $form_id);
}

/**
 * Alter webform after build.
 */
function _webform_form_webform_submission_form_after_build($form, FormStateInterface $form_state) {
  $form_object = $form_state->getFormObject();

  /** @var \Drupal\webform\WebformSubmissionInterface $webform_submission */
  $webform_submission = $form_object->getEntity();
  $webform = $webform_submission->getWebform();

  // Add contextual links and change theme wrapper to webform.html.twig
  // which includes 'title_prefix' and 'title_suffix' variables needed for
  // contextual links to appear.
  $form['#contextual_links']['webform'] = [
    'route_parameters' => ['webform' => $webform->id()],
  ];
  $form['#theme_wrappers'] = ['webform'];
  return $form;
}

/* ************************************************************************** */
// Update manager.
/* ************************************************************************** */

/**
 * Implements hook_form_FORM_ID_alter() for update manager update form.
 *
 * Add warnings when attempting to update the Webform module using
 * the 'Update manager'.
 *
 * @see https://www.drupal.org/project/webform/issues/2930116
 * @see https://www.drupal.org/project/webform/issues/2920095
 */
function webform_form_update_manager_update_form_alter(&$form, FormStateInterface $form_state) {
  if (!isset($form['projects']) || !isset($form['projects']['#options']['webform'])) {
    return;
  }

  // Display dismissible warning at the top of the page.
  $t_args = [
    ':href_manual' => 'https://www.drupal.org/docs/user_guide/en/extend-manual-install.html',
    ':href_composer' => 'https://www.drupal.org/docs/user_guide/en/install-composer.html',
  ];
  $form['webform_update_manager_warning'] = [
    '#type' => 'webform_message',
    '#message_type' => 'warning',
    '#message_message' => t('The Webform module may not update properly using this administrative interface. It is strongly recommended that you update the Webform module <a href=":href_manual">manually</a> or by using <a href=":href_composer">Composer</a>.', $t_args),
    '#message_close' => TRUE,
    '#message_storage' => WebformMessage::STORAGE_SESSION,
    '#weight' => -10,
  ];

  // Display warning to backup site when webform is checked.
  $form['projects']['#options']['webform']['title']['data'] = [
    'title' => $form['projects']['#options']['webform']['title']['data'],
    'container' => [
      '#type' => 'container',
      '#states' => ['visible' => [':input[name="projects[webform]"]' => ['checked' => TRUE]]],
      '#attributes' => ['class' => ['js-form-wrapper'], 'style' => 'display:none'],
      'message' => [
        '#type' => 'webform_message',
        '#message_type' => 'warning',
        '#message_message' => t('Please make sure to backup your website before updating the Webform module.'),
      ],
    ],
  ];
}

/* ************************************************************************** */
// Views.
/* ************************************************************************** */

/**
 * Implements hook_form_FORM_ID_alter() for views exposed form.
 */
function webform_form_views_exposed_form_alter(&$form, FormStateInterface $form_state, $form_id) {
  /** @var \Drupal\views\ViewExecutable $view */
  $view = $form_state->get('view');

  // Check if this a is webform submission view.
  // @see \Drupal\webform\WebformSubmissionListBuilder::buildSubmissionViews
  if (isset($view->webform_submission_view)) {
    $form['#action'] = Url::fromRoute(\Drupal::routeMatch()->getRouteName(), \Drupal::routeMatch()->getRawParameters()->all())->toString();
  }
}

/* ************************************************************************** */
// SMTP.
/* ************************************************************************** */

/**
 * Implements hook_form_FORM_ID_alter() for SMTP admin settings form.
 */
function webform_form_smtp_admin_settings_alter(&$form, FormStateInterface $form_state) {
  $form['#submit'][] = '_webform_form_smtp_admin_settings_submit';
}

/**
 * Submit callback for SMTP admin settings.
 */
function _webform_form_smtp_admin_settings_submit(&$form, FormStateInterface $form_state) {
  // Since the SMTP module can enabled or disabled, check HTML email provider.
  /** @var \Drupal\webform\WebformEmailProviderInterface $email_provider */
  $email_provider = \Drupal::service('webform.email_provider');
  $email_provider->check();
}

/* ************************************************************************** */
// Configuration management.
/* ************************************************************************** */

/**
 * Implements hook_form_FORM_ID_alter() for config single import form.
 */
function webform_form_config_single_import_form_alter(&$form, FormStateInterface $form_state) {
  $config_type = \Drupal::request()->query->get('config_type');
  if ($config_type === 'webform') {
    $form['config_type']['#default_value'] = 'webform';
  }
}
