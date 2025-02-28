<?php

namespace Drupal\metatag_google_plus\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaItempropBase;

/**
 * The GooglePlus 'name' meta tag.
 *
 * @MetatagTag(
 *   id = "google_plus_name",
 *   label = @Translation("Name"),
 *   description = @Translation("Content title."),
 *   name = "name",
 *   group = "google_plus",
 *   weight = 1,
 *   type = "label",
 *   secure = FALSE,
 *   multiple = FALSE,
 *   trimmable = TRUE
 * )
 *
 * @deprecated in metatag:8.x-1.22 and is removed from metatag:2.0.0. No replacement is provided.
 *
 * @see https://www.drupal.org/project/metatag/issues/3065441
 */
class Name extends MetaItempropBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
