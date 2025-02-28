<?php

namespace Drupal\metatag_twitter_cards\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Twitter Cards gallery image3 metatag.
 *
 * @MetatagTag(
 *   id = "twitter_cards_gallery_image3",
 *   label = @Translation("4th gallery image"),
 *   description = @Translation("A URL to the image representing the fourth photo in your gallery."),
 *   name = "twitter:gallery:image3",
 *   group = "twitter_cards",
 *   weight = 203,
 *   type = "image",
 *   secure = FALSE,
 *   multiple = FALSE,
 *   absolute_url = TRUE
 * )
 *
 * @deprecated in metatag:8.x-1.23 and is removed from metatag:2.0.0. No replacement is provided.
 *
 * @see https://www.drupal.org/node/3329072
 */
class TwitterCardsGalleryImage3 extends MetaNameBase {
}
