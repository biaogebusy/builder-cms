<?php

/**
 * @file
 * Drush integration for chosen.
 */

/**
 * The Chosen plugin URI.
 */
define('CHOSEN_DOWNLOAD_URI', 'https://github.com/harvesthq/chosen/releases/download/v1.8.7/chosen_v1.8.7.zip');

/**
 * Implements hook_drush_command().
 */
function chosen_lib_drush_command() {
  $items = [];

  // The key in the $items array is the name of the command.
  // No bootstrap.
  $items['chosen-plugin'] = [
    'callback' => 'drush_chosen_lib_plugin',
    'description' => dt('Download and install the Chosen plugin.'),
    'bootstrap' => DRUSH_BOOTSTRAP_NONE,
    'arguments' => [
      'path' => dt('Optional. A path where to install the Chosen plugin. If omitted Drush will use the default location.'),
    ],
    'aliases' => ['chosenplugin'],
  ];

  return $items;
}

/**
 * Implements hook_drush_help().
 */
function chosen_lib_drush_help($section) {
  switch ($section) {
    case 'drush:chosen-plugin':
      return dt('Download and install the Chosen plugin from https://harvesthq.github.com/chosen, default location is /libraries.');
  }
}

/**
 * Command to download the Chosen plugin.
 */
function drush_chosen_lib_plugin() {
  $args = func_get_args();
  if (!empty($args[0])) {
    $path = $args[0];
  }
  else {
    $path = 'libraries';
  }

  // Create the path if it does not exist.
  if (!is_dir($path)) {
    drush_op('mkdir', $path);
    \Drupal::logger(dt('Directory @path was created', ['@path' => $path]))->notice('notice');

  }

  // Set the directory to the download location.
  $olddir = getcwd();
  chdir($path);

  // Download the zip archive.
  if ($filepath = drush_download_file(CHOSEN_DOWNLOAD_URI)) {
    $filename = basename($filepath);
    $dirname = basename($filepath, '.zip');

    // Remove any existing Chosen plugin directory.
    if (is_dir($dirname) || is_dir('chosen')) {
      $fileservice = \Drupal::service('file_system');
      $fileservice->deleteRecursive($dirname);
      $fileservice->deleteRecursive('chosen');
      \Drupal::logger(dt('A existing Chosen plugin was deleted from @path', ['@path' => $path]))->notice('notice');
    }

    // Decompress the zip archive.
    drush_tarball_extract($filename, $dirname);

    // Change the directory name to "chosen" if needed.
    if ($dirname != 'chosen') {
      drush_move_dir($dirname, 'chosen', TRUE);
      $dirname = 'chosen';
    }
  }

  if (is_dir($dirname)) {
    \Drupal::logger(dt('Chosen plugin has been installed in @path', ['@path' => $path]))->info('success');
  }
  else {
    \Drupal::logger(dt('Drush was unable to install the Chosen plugin to @path', ['@path' => $path]))->error('error');
  }

  // Set working directory back to the previous working directory.
  chdir($olddir);
}
