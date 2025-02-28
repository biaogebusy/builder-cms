<?php

namespace Drupal\eck;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface defining an ECK Entity Bundle.
 *
 * @ingroup eck
 */
interface EckEntityBundleInterface extends ConfigEntityInterface {

  /**
   * Determines whether the entity is locked.
   *
   * @return string|false
   *   The module name that locks the type or FALSE.
   */
  public function isLocked();

}
