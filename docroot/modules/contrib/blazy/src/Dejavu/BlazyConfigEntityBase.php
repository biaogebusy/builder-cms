<?php

namespace Drupal\blazy\Dejavu;

use Drupal\blazy\Config\Entity\BlazyConfigEntityBase as ConfigEntityBase;

@trigger_error('The ' . __NAMESPACE__ . '\BlazyConfigEntityBase is deprecated in blazy:8.x-2.9 and is removed from blazy:8.x-3.0. Use Drupal\blazy\Config\Entity\BlazyConfigEntityBase instead. See https://www.drupal.org/node/3367304', E_USER_DEPRECATED);

/**
 * Deprecated in blazy:8.x-2.9.
 *
 * @deprecated in blazy:8.x-2.9 and is removed from blazy:8.x-3.0. Use
 *   Drupal\blazy\Config\Entity\BlazyConfigEntityBase instead.
 * @see https://www.drupal.org/node/3367304
 */
abstract class BlazyConfigEntityBase extends ConfigEntityBase {}
