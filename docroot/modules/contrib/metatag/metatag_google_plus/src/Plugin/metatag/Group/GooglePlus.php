<?php

namespace Drupal\metatag_google_plus\Plugin\metatag\Group;

use Drupal\metatag\Plugin\metatag\Group\GroupBase;

/**
 * The GooglePlus group.
 *
 * @MetatagGroup(
 *   id = "google_plus",
 *   label = @Translation("Google Plus"),
 *   description = @Translation("A set of meta tags specially for controlling the summaries displayed when content is shared on <a href=':plus'>Google Plus</a>.", arguments = { ":plus" = "https://plus.google.com/" }),
 *   weight = 4
 * )
 *
 * @deprecated in metatag:8.x-1.22 and is removed from metatag:2.0.0. No replacement is provided.
 *
 * @see https://www.drupal.org/project/metatag/issues/3065441
 */
class GooglePlus extends GroupBase {
  // Inherits everything from Base.
}
