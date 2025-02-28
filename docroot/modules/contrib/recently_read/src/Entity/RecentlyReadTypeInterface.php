<?php

namespace Drupal\recently_read\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface for defining Recently read type entities.
 */
interface RecentlyReadTypeInterface extends ConfigEntityInterface {

  /**
   * Return recently read types.
   *
   * @return string
   *   Name of recently read types.
   */
  public function getTypes();

}
