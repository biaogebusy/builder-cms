<?php

namespace Drupal\blazy;

use Drupal\blazy\Theme\Grid;

@trigger_error('The ' . __NAMESPACE__ . '\BlazyGrid is deprecated in blazy:8.x-2.9 and is removed from blazy:8.x-3.0. Use \Drupal\blazy\Blazy::grid() or \Drupal\blazy\BlazyManager::toGrid() instead. See https://www.drupal.org/node/3367304', E_USER_DEPRECATED);

/**
 * Deprecated in blazy:8.x-2.9.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module.
 *
 * @deprecated in blazy:8.x-2.9 and is removed from blazy:3.0.0. Use
 *   Drupal\blazy\Blazy::grid() or Drupal\blazy\BlazyManager::toGrid() instead.
 * @see https://www.drupal.org/node/3367304
 */
class BlazyGrid extends Grid {}
