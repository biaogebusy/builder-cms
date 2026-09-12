<?php

/**
 * @file
 * Integrates third party settings on the Maillog module's behalf.
 */

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\LegacyHook;
use Drupal\webform\Hook\WebformMaillogHooks;

/**
 * Implements hook_webform_submission_form_alter().
 */
#[LegacyHook]
function maillog_webform_submission_form_alter(array &$form, FormStateInterface $form_state, $form_id) {
  return \Drupal::service(WebformMaillogHooks::class)->maillogWebformSubmissionFormAlter($form, $form_state, $form_id);
}
