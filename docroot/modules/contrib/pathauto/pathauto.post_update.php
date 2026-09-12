<?php

/**
 * @file
 * Post update functions for Pathauto.
 */

use Drupal\Core\Config\Entity\ConfigEntityUpdater;
use Drupal\pathauto\PathautoPatternInterface;

/**
 * Remove uuid key from condition plugin configuration.
 */
function pathauto_post_update_remove_uuid_config_key(array &$sandbox = []): void {
  \Drupal::classResolver(ConfigEntityUpdater::class)
    ->update($sandbox, 'pathauto_pattern', function (PathautoPatternInterface $pattern): bool {
      $selection_criteria = $pattern->get('selection_criteria');
      $changed = FALSE;
      foreach ($selection_criteria as $uuid => $condition) {
        if (isset($condition['uuid'])) {
          unset($selection_criteria[$uuid]['uuid']);
          $changed = TRUE;
        }
      }
      if ($changed) {
        $pattern->set('selection_criteria', $selection_criteria);
      }
      return $changed;
    });
}
