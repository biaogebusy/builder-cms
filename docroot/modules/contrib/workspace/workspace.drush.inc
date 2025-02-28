<?php

use Drupal\Core\Database\Database;
use Drupal\multiversion\Entity\Workspace;
use Psr\Log\LogLevel;

/**
 * Implements of hook_drush_command().
 */
function workspace_drush_command() {
  $items = [];

  $items['workspace-uninstall'] = [
    'bootstrap' => DRUSH_BOOTSTRAP_NONE,
    'description' => 'Uninstall Workspace.',
    'aliases' => ['wun'],
  ];

  return $items;
}

/**
 * Implements drush_hook_COMMAND().
 */
function drush_workspace_uninstall() {
  $extension = 'workspace';
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
    drush_print(dt('Workspace will be uninstalled.'));
    if(!drush_confirm(dt('Do you really want to continue?'))) {
      return drush_user_abort();
    }

    try {
      $entity_type_manager = \Drupal::entityTypeManager();
      $default_workspace_id = \Drupal::getContainer()->getParameter('workspace.default');
      $default_workspace = Workspace::load($default_workspace_id);
      \Drupal::service('workspace.manager')->setActiveWorkspace($default_workspace);
      $database = \Drupal::database();
      $database
        ->delete('key_value_expire')
        ->condition('collection', 'user.private_tempstore.workspace.negotiator.session')
        ->execute();

      // Delete the 'workspace_replication' queue before deleting non-default
      // workspaces.
      \Drupal::queue('workspace_replication')->deleteQueue();

      // Delete all workspaces excluding the default workspace, also delete all
      // content from deleted workspaces.
      $workspaces = Workspace::loadMultiple();
      foreach ($workspaces as $workspace) {
        if (!$workspace->isDefaultWorkspace()) {
          $workspace->delete();
        }
      }
      \Drupal::service('cron')->run();

      // Delete all workspace_pointer entities.
      $storage = \Drupal::entityTypeManager()->getStorage('workspace_pointer');
      $entities = $storage->loadMultiple();
      $storage->delete($entities);

      // Delete all replication entities.
      $storage = \Drupal::entityTypeManager()->getStorage('replication');
      $entities = $storage->loadMultiple();
      $storage->delete($entities);

      // Set values for all fields provided by Workspace to NULL in the database
      // (for workspace entity type), so the module can be uninstalled.
      $entity_field_manager = \Drupal::service('entity_field.manager');
      $storage = $entity_type_manager->getStorage('workspace');
      $fields = [];
      foreach ($entity_field_manager->getFieldStorageDefinitions('workspace') as $storage_definition) {
        if ($storage_definition->getProvider() === 'workspace') {
          $fields[$storage_definition->getName()] = NULL;
        }
      }
      if (!empty($fields)) {
        $connection = Database::getConnection();
        $connection->update($storage->getEntityType()->getBaseTable())
          ->fields($fields)
          ->execute();
      }

      drush_module_uninstall([$extension]);
    }
    catch (Exception $e) {
      drush_log($e->getMessage(), LogLevel::ERROR);
    }

    // Inform the user of final status.
    drush_log(dt('!extension was successfully uninstalled.', ['!extension' => $extension]), LogLevel::INFO);
  }

}
