<?php

namespace Drupal\blazy\Dejavu;

use Drupal\blazy\Views\BlazyStyleOptionsTrait as StyleOptionsTrait;

@trigger_error('The ' . __NAMESPACE__ . '\BlazyStyleOptionsTrait is deprecated in blazy:8.x-2.17 and is removed from blazy:8.x-3.0. Use \Drupal\blazy\Views\BlazyStylePluginBase instead. See https://www.drupal.org/node/3367304', E_USER_DEPRECATED);

/**
 * Deprecated in blazy:8.x-2.14.
 *
 * @deprecated in blazy:8.x-2.14 and is removed from blazy:8.x-3.0. Use
 *   Drupal\blazy\Views\BlazyStylePluginBase instead.
 * @see https://www.drupal.org/node/3367304
 */
trait BlazyStyleOptionsTrait {

  use StyleOptionsTrait;

}
