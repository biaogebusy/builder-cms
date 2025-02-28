<?php

namespace Drupal\metatag_twitter_cards\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Twitter Cards image height metatag.
 *
 * @MetatagTag(
 *   id = "twitter_cards_image_height",
 *   label = @Translation("Image height"),
 *   description = @Translation("The height of the image being linked to, in pixels."),
 *   name = "twitter:image:height",
 *   group = "twitter_cards",
 *   weight = 7,
 *   type = "integer",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 *
 * @deprecated in metatag:8.x-1.23 and is removed from metatag:2.0.0. No replacement is provided.
 *
 * @see https://www.drupal.org/node/3329072
 */
class TwitterCardsImageHeight extends MetaNameBase {
}
