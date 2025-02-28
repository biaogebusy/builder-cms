<?php

namespace Drupal\blazy\Dejavu;

use Drupal\blazy\Config\Entity\BlazyConfigEntityBaseInterface as ConfigEntityInterface;

@trigger_error('The ' . __NAMESPACE__ . '\BlazyConfigEntityBaseInterface is deprecated in blazy:8.x-2.9 and is removed from blazy:8.x-3.0. Use Drupal\blazy\Config\Entity\BlazyConfigEntityBaseInterface instead. See https://www.drupal.org/node/3367304', E_USER_DEPRECATED);

/**
 * Deprecated in blazy:8.x-2.9.
 *
 * @deprecated in blazy:8.x-2.9 and is removed from blazy:8.x-3.0. Use
 *   Drupal\blazy\Config\Entity\BlazyConfigEntityBaseInterface instead.
 * @see https://www.drupal.org/node/3367304
 */
interface BlazyConfigEntityBaseInterface extends ConfigEntityInterface {}
