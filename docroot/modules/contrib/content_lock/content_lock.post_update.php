<?php

/**
 * @file
 * Post update functions for Content Lock module.
 */

use Drupal\content_lock\Hook\ContentLockHooks;
use Drupal\Core\Config\Entity\ConfigEntityUpdater;
use Drupal\Core\Url;
use Drupal\views\ViewEntityInterface;

/**
 * Updates views cache settings for view displaying content lock information.
 */
function content_lock_post_update_fixing_views_caching(array &$sandbox = []): void {
  \Drupal::classResolver(ConfigEntityUpdater::class)->update($sandbox, 'view', function (ViewEntityInterface $view): bool {
    // Re-save all views with a dependency on the Content lock module.
    return in_array('content_lock', $view->getDependencies()['module'] ?? [], TRUE);
  });
}

/**
 * Uninstall Content Lock Timeout, it's features are part of Content Lock.
 */
function content_lock_post_update_uninstall_content_lock_timeout() {
  $installed = \Drupal::moduleHandler()->moduleExists('content_lock_timeout');

  // Migrate the supported configuration.
  $config = \Drupal::configFactory()->getEditable('content_lock.settings');
  if ($installed) {
    $config->set('timeout', ((int) \Drupal::config('content_lock_timeout.settings')->get('content_lock_timeout_minutes')) * 60);
  }
  else {
    $config->set('timeout', NULL);
  }
  $config->save();

  if ($installed) {
    \Drupal::service('module_installer')->uninstall(['content_lock_timeout']);
  }

  if ($config->get('timeout') === NULL) {
    return t('The content lock timeout is currently disabled. It is recommended that administrators <a href=":settings">set the timeout value</a> so that content locks automatically expire.', [':settings' => Url::fromRoute('content_lock.settings')->toString()]);
  }
}

/**
 * Clean up config.
 */
function content_lock_post_update_clean_up_config() {
  $config = \Drupal::configFactory()->getEditable('content_lock.settings');
  $not_lockable_entity_types = [];
  $changed = FALSE;
  foreach ($config->get('types') as $id => $value) {
    if (empty($value)) {
      $config = ContentLockHooks::removeEntityTypeFromConfig($id, $config);
      $changed = TRUE;
      $not_lockable_entity_types[] = $id;
    }
  }

  if ($changed) {
    $config->save();
    $plugin_ids = array_map(fn($type) => 'entity:break_lock:' . $type, $not_lockable_entity_types);
    $action_storage = \Drupal::entityTypeManager()->getStorage('action');
    $action_ids = $action_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('plugin', $plugin_ids, 'IN')
      ->execute();
    if (!empty($action_ids)) {
      $action_storage->delete($action_storage->loadMultiple($action_ids));
    }
  }
}
