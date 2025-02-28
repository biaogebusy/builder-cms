<?php

namespace Drupal\metatag_twitter_cards\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Twitter Cards gallery image0 metatag.
 *
 * @MetatagTag(
 *   id = "twitter_cards_gallery_image0",
 *   label = @Translation("1st gallery image"),
 *   description = @Translation("A URL to the image representing the first photo in your gallery."),
 *   name = "twitter:gallery:image0",
 *   group = "twitter_cards",
 *   weight = 200,
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
class TwitterCardsGalleryImage0 extends MetaNameBase {
}
