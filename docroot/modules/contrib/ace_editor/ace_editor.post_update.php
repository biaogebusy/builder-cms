<?php

/**
 * @file
 * Post update functions for the Ace Editor module.
 */

use Drupal\Core\Config\Entity\ConfigEntityUpdater;

/**
 * Add the new formatter options and drop the option lists from displays.
 *
 * Older releases merged the whole module configuration into the stored
 * formatter settings, so every display carried the theme and syntax option
 * lists (issue #2999328). The three per-formatter options added with the
 * syntax field also have to reach the active configuration, which
 * config/install does not do on an existing site.
 */
function ace_editor_post_update_formatter_settings(?array &$sandbox = NULL): void {
  $config = \Drupal::configFactory()->getEditable('ace_editor.settings');
  $changed = FALSE;
  foreach (['syntax_field' => '_none', 'modelist' => FALSE, 'inline' => FALSE] as $key => $value) {
    if ($config->get($key) === NULL) {
      $config->set($key, $value);
      $changed = TRUE;
    }
  }
  if ($changed) {
    $config->save();
  }

  \Drupal::classResolver(ConfigEntityUpdater::class)->update($sandbox, 'entity_view_display', function ($display): bool {
    /** @var \Drupal\Core\Entity\Display\EntityViewDisplayInterface $display */
    $updated = FALSE;
    foreach ($display->getComponents() as $name => $component) {
      if (($component['type'] ?? NULL) !== 'ace_formatter') {
        continue;
      }
      $before = $component['settings'] ?? [];
      $after = array_diff_key($before, array_flip(['theme_list', 'syntax_list', '_core']));
      if ($after !== $before) {
        $component['settings'] = $after;
        $display->setComponent($name, $component);
        $updated = TRUE;
      }
    }
    return $updated;
  });
}
