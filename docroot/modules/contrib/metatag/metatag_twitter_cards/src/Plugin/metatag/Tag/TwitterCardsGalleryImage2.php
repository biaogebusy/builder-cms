<?php

namespace Drupal\metatag_twitter_cards\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Twitter Cards gallery image2 metatag.
 *
 * @MetatagTag(
 *   id = "twitter_cards_gallery_image2",
 *   label = @Translation("3rd gallery image"),
 *   description = @Translation("A URL to the image representing the third photo in your gallery."),
 *   name = "twitter:gallery:image2",
 *   group = "twitter_cards",
 *   weight = 202,
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
class TwitterCardsGalleryImage2 extends MetaNameBase {
}
