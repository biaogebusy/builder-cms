<?php

namespace Drupal\memcache;

/**
 * Wrapper for a split cache item.
 *
 * When a cache item is larger than Memcache can handle as a single item, it
 * gets split into smaller chunks and stored as multiple items.  An object of
 * this class gets stored with the original CID - it does not contain data
 * itself, but tracks the CIDs of the children that contain the data.
 */
class MultipartItem {

  /**
   * The CIDs that contain the item's data.
   *
   * @var array
   */
  private $cids;

  /**
   * Constructor.
   *
   * @param string[] $cids
   *   The CIDs that contain the item's data.
   */
  public function __construct(array $cids) {
    $this->cids = $cids;
  }

  /**
   * Get the CIDs of this item's children.
   *
   * @return string[]
   *   The CIDs that contain the item's data.
   */
  public function getCids() {
    return $this->cids;
  }

}
