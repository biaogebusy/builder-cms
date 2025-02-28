<?php

/**
 * @file
 * Post update functions for Media entity Instagram module.
 */

/**
 * Rename the cache bin.
 */
function media_entity_instagram_post_update_rename_cache_bin() {
  // An empty update will force service definitions to be cleared and create a
  // new bin with new name.
}

/**
 * Rename source from "instagram" to "oembed:instagram".
 */
function media_entity_instagram_post_update_change_source_name() {
  $config_factory = \Drupal::configFactory();
  foreach ($config_factory->listAll('media.type.') as $name) {
    $config = $config_factory->getEditable($name);
    if ($config->get('source') === 'instagram') {
      $config->set('source', 'oembed:instagram');
      $config->save();
      $source_configuration = $config->get('source_configuration');

      /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager */
      $entity_type_manager = \Drupal::service('entity_type.manager');

      /** @var \Drupal\Core\Entity\Display\EntityViewDisplayInterface[] $displays */
      $displays = $entity_type_manager->getStorage('entity_view_display')
        ->loadByProperties([
          'targetEntityType' => 'media',
          'bundle' => $config->get('id'),
        ]);

      foreach ($displays as $display) {
        $source_field = $display->getComponent($source_configuration['source_field']);
        if ($source_field['type'] === 'instagram_embed') {
          if (!empty($source_field['settings']['width'])) {
            $source_field['settings'] = [
              'max_width' => $source_field['settings']['width'],
              'hidecaption' => $source_field['settings']['hidecaption'],
              'max_height' => NULL,
            ];
          }
          $display->setComponent($source_configuration['source_field'], $source_field);
          $display->save();
        }
      }

      $displays = $entity_type_manager->getStorage('entity_form_display')
        ->loadByProperties([
          'targetEntityType' => 'media',
          'bundle' => $config->get('id'),
        ]);

      foreach ($displays as $display) {
        $source_field = $display->getComponent($source_configuration['source_field']);
        if ($source_field['type'] === 'string_textfield') {
          $source_field['type'] = 'oembed_textfield';

          $display->setComponent($source_configuration['source_field'], $source_field);
          $display->save();
        }
      }
    }
  }

  $config_factory->getEditable('media_entity_instagram.settings')
    ->clear('local_images')
    ->set('facebook_app_id', '')
    ->set('facebook_app_secret', '')
    ->save();

}
