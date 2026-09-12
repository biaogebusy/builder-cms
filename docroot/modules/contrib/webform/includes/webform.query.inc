<?php

/**
 * @file
 * Webform module query hooks.
 */

use Drupal\Core\Hook\Attribute\LegacyHook;
use Drupal\webform\Hook\WebformQueryHooks;
use Drupal\Core\Database\Query\AlterableInterface;

/**
 * Implements hook_query_TAG_alter().
 *
 * Append EAV sort to webform_submission entity query.
 *
 * @see http://stackoverflow.com/questions/12893314/sorting-eav-database
 * @see \Drupal\webform\WebformSubmissionListBuilder::getEntityIds
 */
#[LegacyHook]
function webform_query_webform_submission_list_builder_alter(AlterableInterface $query) {
  \Drupal::service(WebformQueryHooks::class)->queryWebformSubmissionListBuilderAlter($query);
}

/**
 * Implements hook_query_TAG_alter().
 */
#[LegacyHook]
function webform_query_entity_reference_alter(AlterableInterface $query) {
  \Drupal::service(WebformQueryHooks::class)->queryEntityReferenceAlter($query);
}

/**
 * Implements hook_query_TAG_alter().
 *
 * This hook implementation adds webform submission access checks for the
 * account stored in the 'account' meta-data (or current user if not provided),
 * for an operation stored in the 'op' meta-data (or 'view' if not provided).
 */
#[LegacyHook]
function webform_query_webform_submission_access_alter(AlterableInterface $query) {
  return \Drupal::service(WebformQueryHooks::class)->queryWebformSubmissionAccessAlter($query);
}
