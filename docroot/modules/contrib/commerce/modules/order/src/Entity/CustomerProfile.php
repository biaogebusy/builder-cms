<?php

namespace Drupal\commerce_order\Entity;

use Drupal\Core\Cache\Cache;
use Drupal\profile\Entity\Profile;

/**
 * Overrides Profile entity class for the 'customer' profile bundle.
 */
class CustomerProfile extends Profile {

  /**
   * {@inheritdoc}
   */
  protected function invalidateTagsOnSave($update) {
    // An entity was created or updated: invalidate its list cache tags. (An
    // updated entity may start to appear in a listing because it now meets that
    // listing's filtering requirements. A newly created entity may start to
    // appear in listings because it did not exist before.)
    $tags = $this->getListCacheTagsToInvalidate();

    // Do not invalidate 4xx-response cache tag, because profiles in most cases
    // are not affected by possibly stale 404 pages.
    if ($update) {
      // An existing entity was updated, also invalidate its unique cache tag.
      $tags = Cache::mergeTags($tags, $this->getCacheTagsToInvalidate());
    }
    Cache::invalidateTags($tags);
  }

}
