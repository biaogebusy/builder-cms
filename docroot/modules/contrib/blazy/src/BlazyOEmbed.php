<?php

namespace Drupal\blazy;

use Drupal\blazy\Media\BlazyOEmbed as OEmbed;

@trigger_error('The ' . __NAMESPACE__ . '\BlazyOEmbed is deprecated in blazy:8.x-2.6 and is removed from blazy:8.x-3.0. Use \Drupal\blazy\Media\BlazyOEmbed instead. See https://www.drupal.org/node/3367304', E_USER_DEPRECATED);

/**
 * Deprecated in blazy:8.x-2.6.
 *
 * @deprecated in blazy:8.x-2.6 and is removed from blazy:3.0.0. Use
 *   Drupal\blazy\Media\BlazyOEmbed instead.
 * @see https://www.drupal.org/node/3367304
 */
class BlazyOEmbed extends OEmbed {}
