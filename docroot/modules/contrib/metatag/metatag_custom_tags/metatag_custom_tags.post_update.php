<?php

/**
 * @file
 * Post update functions for Metatag: Custom Tags.
 */

use Drupal\Core\Config\Entity\ConfigEntityUpdater;
use Drupal\metatag_custom_tags\Entity\MetaTagCustomTag;

/**
 * Update all metatag_custom_tag config entities to the new structure.
 */
function metatag_custom_tags_post_update_convert_custom_tag_structure(&$sandbox) {
  $updater = \Drupal::classResolver(ConfigEntityUpdater::class);
  $updater->update($sandbox, 'metatag_custom_tag', function (MetaTagCustomTag $custom_tag) {
    if ($custom_tag->get('htmlNameAttribute') && !$custom_tag->get('attributes')) {
      $attributes = [
        [
          'name' => $custom_tag->get('htmlNameAttribute'),
          'value' => $custom_tag->id(),
        ],
      ];
      $custom_tag->set('attributes', $attributes);

      return TRUE;
    }
    return FALSE;
  });
}
