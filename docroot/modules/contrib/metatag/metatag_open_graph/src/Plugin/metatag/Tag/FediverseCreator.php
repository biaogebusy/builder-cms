<?php

namespace Drupal\metatag_open_graph\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Open Graph "Fediverse:creator" meta tag.
 *
 * @MetatagTag(
 *   id = "fediverse_creator",
 *   label = @Translation("Creator's fediverse account"),
 *   description = @Translation("The fediverse @username for the content creator / author for this page, including the first @ symbol."),
 *   name = "fediverse:creator",
 *   group = "open_graph",
 *   weight = 28,
 *   type = "label",
 *   secure = FALSE,
 *   multiple = TRUE
 * )
 */
class FediverseCreator extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
