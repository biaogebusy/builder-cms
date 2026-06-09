<?php

/**
 * @file
 * Contains post updates for ai_ckeditor module.
 */

use Drupal\Core\Config\Entity\ConfigEntityUpdater;
use Drupal\editor\EditorInterface;

/**
 * Enable modify_prompt plugin for all editors that use AI CKEditor button.
 */
function ai_ckeditor_post_update_10001(&$sandbox) {
  $config_entity_updater = \Drupal::classResolver(ConfigEntityUpdater::class);
  $callback = function (EditorInterface $editor) {
    $needs_save = FALSE;
    $settings = $editor->getSettings();
    // Check if this editor uses CKEditor 5 and has AI CKEditor enabled.
    if (isset($settings['plugins']['ai_ckeditor_ai']['plugins'])) {
      $needs_save = TRUE;
      // Add the modify prompt plugin with enabled = FALSE.
      $settings['plugins']['ai_ckeditor_ai']['plugins']['ai_ckeditor_modify_prompt'] = [
        'enabled' => FALSE,
        'provider' => NULL,
      ];

      // Save the updated settings.
      $editor->setSettings($settings);
    }
    return $needs_save;
  };

  $config_entity_updater->update($sandbox, 'editor', $callback);
}
