<?php

/**
 * @file
 * Drush integration for the replication module.
 */

// @todo move this file to the Relaxed module or revise to not be dependent
// upon Relaxed.
use Doctrine\CouchDB\CouchDBClient;
use Psr\Log\LogLevel;
use Relaxed\Replicator\ReplicationTask;
use Relaxed\Replicator\Replication;

/**
 * Implements of hook_drush_command().
 */
function replication_drush_command() {
  $items = [];

  $items['replication-uninstall'] = [
    'bootstrap' => DRUSH_BOOTSTRAP_NONE,
    'description' => 'Uninstall Replication.',
    'aliases' => ['repun'],
  ];

  $items['replication-start'] = [
    'bootstrap' => DRUSH_BOOTSTRAP_NONE,
    'description' => 'Start a replication.',
    'arguments' => [
      'source' => dt('Source database.'),
      'target' => dt('Target database.'),
    ],
    'required-arguments' => TRUE,
    'options' => [
      'continuous' => [
        'description' => dt('Continuous replication.'),
        'default' => FALSE,
      ],
      'replicator' => [
        'description' => dt('The used replicator.'),
      ],
    ],
    'outputformat' => [
      'default' => 'key-value',
      'pipe-format' => 'json',
      'field-labels' => [
        'ok' => 'Status',
        'no_changes' => 'No changes',
        'session_id' => 'Session ID',
        'source_last_seq' => 'Last sequence number',
        'replication_id_version' => 'Replication protocol version',
      ],
      'output-data-type' => 'format-list',
    ],
  ];
  $items['replication-stop'] = [
    'bootstrap' => DRUSH_BOOTSTRAP_NONE,
    'description' => 'Stop a replication.',
    'arguments' => [
      'source' => dt('Source database.'),
      'target' => dt('Target database.'),
    ],
    'required-arguments' => TRUE,
    'options' => [
      'continuous' => [
        'description' => dt('Continuous replication.'),
        'default' => FALSE,
      ],
      'replicator' => [
        'description' => dt('The used replicator.'),
      ],
    ],
    'outputformat' => [
      'default' => 'key-value',
      'pipe-format' => 'json',
      'field-labels' => [
        'ok' => 'Status',
        'no_changes' => 'No changes',
        'session_id' => 'Session ID',
        'source_last_seq' => 'Last sequence number',
        'replication_id_version' => 'Replication protocol version',
      ],
      'output-data-type' => 'format-list',
    ],
  ];
  $items['replication-active'] = [
    'bootstrap' => DRUSH_BOOTSTRAP_NONE,
    'description' => 'Prints information about the specific active replication between target and source databases.',
    'arguments' => [
      'source' => dt('Source database.'),
      'target' => dt('Target database.'),
    ],
    'options' => [
      'replicator' => [
        'description' => dt('The used replicator.'),
      ],
    ],
    'outputformat' => [
      'default' => 'key-value-list',
      'pipe-format' => 'json',
      'field-labels' => [
        'source' => 'Source',
        'target' => 'Target',
        'started_on' => 'Started on',
        'progress' => 'Progress',
        'docs_read' => 'Documents read',
        'docs_written' => 'Documents written',
        'revisions_checked' => 'Revisions checked',
        'doc_write_failures' => 'Write failures',
        'pid' => 'Process ID',
      ],
    ],
  ];

  return $items;
}

/**
 * Implements drush_hook_COMMAND().
 */
function drush_replication_uninstall() {
  $extension = 'replication';
  $uninstall = TRUE;
  $extension_info = drush_get_extensions();
  $required = drush_drupal_required_modules($extension_info);
  if (in_array($extension, $required)) {
    $info = $extension_info[$extension]->info;
    $explanation = !empty($info['explanation']) ? ' ' . dt('Reason: !explanation.', ['!explanation' => strip_tags($info['explanation'])])  : '';
    drush_log(dt('!extension is a required extension and can\'t be uninstalled.', ['!extension' => $extension]) . $explanation, LogLevel::INFO);
    $uninstall = FALSE;
  }
  elseif (!$extension_info[$extension]->status) {
    drush_log(dt('!extension is already uninstalled.', ['!extension' => $extension]), LogLevel::INFO);
    $uninstall = FALSE;
  }
  elseif (drush_extension_get_type($extension_info[$extension]) == 'module') {
    $dependents = [];
    foreach (drush_module_dependents([$extension], $extension_info) as $dependent) {
      if (!in_array($dependent, $required) && ($extension_info[$dependent]->status)) {
        $dependents[] = $dependent;
      }
    }
    if (count($dependents)) {
      drush_log(dt('To uninstall !extension, the following extensions must be uninstalled first: !required', ['!extension' => $extension, '!required' => implode(', ', $dependents)]), LogLevel::ERROR);
      $uninstall = FALSE;
    }
  }

  if ($uninstall) {
    drush_print(dt('Replication will be uninstalled.'));
    if(!drush_confirm(dt('Do you really want to continue?'))) {
      return drush_user_abort();
    }

    try {
      // Delete all replication_log entities.
      $storage = \Drupal::entityTypeManager()->getStorage('replication_log')->getOriginalStorage();
      $entities = $storage->loadMultiple();
      $storage->delete($entities);

      drush_module_uninstall([$extension]);
    }
    catch (Exception $e) {
      drush_log($e->getMessage(), LogLevel::ERROR);
    }

    // Inform the user of final status.
    drush_log(dt('!extension was successfully uninstalled.', ['!extension' => $extension]), LogLevel::INFO);
  }

}

/**
 * Implements drush_hook_COMMAND_validate().
 */
function drush_replication_start_validate() {
  // Array of "Callback arguments" and "command line args".
  $params = func_get_args();
  replication_command_validate($params);
}

/**
 * Implements drush_hook_COMMAND_validate().
 */
function drush_replication_stop_validate() {
  // Array of "Callback arguments" and "command line args".
  $params = func_get_args();
  replication_command_validate($params);
}

/**
 * Implements drush_hook_COMMAND_validate().
 */
function drush_replication_active_validate() {
  // Array of "Callback arguments" and "command line args".
  $params = func_get_args();
  if (isset($params[0]) || isset($params[1])) {
    replication_command_validate($params);
  }
}

/**
 * Implements drush_hook_COMMAND().
 */
function drush_replication_start($source, $target) {
  try {
    $source_client = replication_client_factory($source);
    $target_client = replication_client_factory($target);
    // Create the replication task.
    $task = new ReplicationTask();
    // Create the replication.
    $replication = new Replication($source_client, $target_client, $task);
    // Generate and set a replication ID.
    $replication->task->setRepId($replication->generateReplicationId());
    // Start the replication.
    $replicationResult = $replication->start();
    return $replicationResult;
  }
  catch (\Exception $e) {
    drush_set_error($e->getMessage());
  }
}

/**
 * Implements drush_hook_COMMAND().
 */
function drush_replication_stop($source, $target) {
  try {
    $client = replication_client_factory();
    $continuous = drush_get_option('continuous');
    return $client->replicate($source, $target, TRUE, $continuous);
  }
  catch (\Exception $e) {
    drush_set_error($e->getMessage());
  }
}

/**
 * Implements drush_hook_COMMAND().
 *
 * Prints information about the specific active replication between target and
 * source databases.
 */
function drush_replication_active($source = NULL, $target = NULL) {
  try {
    $client = replication_client_factory();
    $results = $client->getActiveTasks();
    foreach ($results as $key => $result) {
      $results[$key]['started_on'] = date('D, j M Y, H:i:s e', $result['started_on']);
      if ($source && $target && is_array($results)) {
        $source_diff = array_diff(replication_get_url_parts($result['source']), replication_get_url_parts($source));
        $target_diff = array_diff(replication_get_url_parts($result['target']), replication_get_url_parts($target));
        if (empty($source_diff) && empty($target_diff)) {
          // Return information about one active replication.
          return [$results[$key]];
        }
        else {
          drush_print('No active replication.');
          return;
        }
      }
    }
    if (!empty($results)) {
      // Return information about all active replications.
      return $results;
    }
    else {
      drush_print('No active replications.');
    }
  }
  catch (\Exception $e) {
    drush_set_error($e->getMessage());
  }
}

/**
 * Helper function for command validation.
 *
 * @param $params
 */
function replication_command_validate($params) {
  if (replication_get_http_response_code($params['0']) != 200) {
    drush_set_error(dt('Source database not found.'));
  }
  if (replication_get_http_response_code($params['1']) != 200) {
    drush_set_error(dt('Target database not found.'));
  }
}

/**
 * Returns the CouchDBClient() object.
 */
function replication_client_factory($url) {
  return CouchDBClient::create([
    'url' => (string) $url,
    'timeout' => 10
  ]);
}

/**
 * Returns the response code for a request.
 *
 * @param $url
 *
 * @return string
 */
function replication_get_http_response_code($url) {
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_exec($ch);
  $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  return $httpcode;
}

/**
 * Returns url parts (host, port, path, user and pass).
 *
 * @param $url
 * @param bool $credentials
 *
 * @return array
 */
function replication_get_url_parts($url, $credentials = FALSE) {
  $url_parts = parse_url($url);
  $options = [
    'host' => $url_parts['host'],
    'port' => $url_parts['port'],
  ];
  $path = trim($url_parts['path'], '/');
  if ($path != '') {
    $options['path'] = $path;
  }
  if ($credentials) {
    $options['user'] = $url_parts['user'] ? $url_parts['user'] : NULL;
    $options['password'] = $url_parts['pass'] ? $url_parts['pass'] : NULL;
  }
  return $options;
}
