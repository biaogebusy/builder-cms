<?php

namespace Drupal\metatag_twitter_cards\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * Provides a plugin for the 'twitter:data2' meta tag.
 *
 * @MetatagTag(
 *   id = "twitter_cards_data2",
 *   label = @Translation("Data 2"),
 *   description = @Translation("This field expects a string, and allows you to specify the types of data you want to offer (price, country, etc.)."),
 *   name = "twitter:data2",
 *   group = "twitter_cards",
 *   weight = 503,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 *
 * @deprecated in metatag:8.x-1.23 and is removed from metatag:2.0.0. No replacement is provided.
 *
 * @see https://www.drupal.org/node/3329072
 */
class TwitterCardsData2 extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
