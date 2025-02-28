<?php

namespace Drupal\metatag_google_plus\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\LinkRelBase;

/**
 * Provides a plugin for the 'publisher' meta tag.
 *
 * @MetatagTag(
 *   id = "google_plus_publisher",
 *   label = @Translation("Publisher URL"),
 *   description = @Translation("Used by some search engines to confirm publication of the content on a page. Should be the full URL for the publication's Google+ profile page."),
 *   name = "publisher",
 *   group = "google_plus",
 *   weight = 5,
 *   type = "label",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 *
 * @deprecated in metatag:8.x-1.22 and is removed from metatag:2.0.0. No replacement is provided.
 *
 * @see https://www.drupal.org/project/metatag/issues/3065441
 */
class Publisher extends LinkRelBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
