<?php

namespace Drupal\blazy;

use Drupal\blazy\Media\BlazyMedia as Media;

@trigger_error('The ' . __NAMESPACE__ . '\BlazyMedia is deprecated in blazy:8.x-2.9 and is removed from blazy:8.x-3.0. Use \Drupal\blazy\Media\BlazyMedia instead. See https://www.drupal.org/node/3367304', E_USER_DEPRECATED);

/**
 * Deprecated in blazy:8.x-2.9.
 *
 * @deprecated in blazy:8.x-2.9 and is removed from blazy:3.0.0. Use
 *   Drupal\blazy\Media\BlazyMedia instead.
 * @see https://www.drupal.org/node/3367304
 */
class BlazyMedia extends Media {}
