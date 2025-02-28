<?php

// @codingStandardsIgnoreFile

/**
 * This is file was generated using Drush. DO NOT EDIT. 
 *
 * @see drush json_editor-generate-commands
 * @see \Drupal\json_editor\Commands\DrushCliServiceBase::generate_commands_drush8
 */

/**
 * Implements hook_drush_command().
 */
function json_editor_drush_command() {
  return array(
    'json_editor-libraries-download' => array(
      'description' => 'Download third party libraries required by the Json Editor module.',
      'core' => array(
        '8+',
      ),
      'bootstrap' => 1,
      'examples' => array(
        'json_editor-libraries-download' => 'Download third party libraries required by the Json Editor module.',
      ),
      'aliases' => array(
        'jedl',
      ),
    ),
    'json_editor-generate-commands' => array(
      'description' => 'Generate Drush commands from json_editor.drush.inc for Drush 8.x to JsonEditorCommands for Drush 9.x.',
      'core' => array(
        '8+',
      ),
      'bootstrap' => 2,
      'examples' => array(
        'drush json_editor-generate-commana ds' => 'Generate Drush commands from json_editor.drush.inc for Drush 8.x to JsonEditorCommands for Drush 9.x.',
      ),
      'aliases' => array(
        'jegc',
      ),
    ),
  );
}

/**
 * Implements drush_hook_COMMAND().
 */
function drush_json_editor_libraries_download() {
  return call_user_func_array([\Drupal::service('json_editor.cli_service'), 'drush_json_editor_libraries_download'], func_get_args());
}

/**
 * Implements drush_hook_COMMAND().
 */
function drush_json_editor_generate_commands() {
  return call_user_func_array([\Drupal::service('json_editor.cli_service'), 'drush_json_editor_generate_commands'], func_get_args());
}

