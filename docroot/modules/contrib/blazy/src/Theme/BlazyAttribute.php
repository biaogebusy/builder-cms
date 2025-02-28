<?php

namespace Drupal\blazy\Theme;

@trigger_error('The ' . __NAMESPACE__ . '\BlazyAttribute is deprecated in blazy:8.x-2.17 and is removed from blazy:8.x-3.0. Use non-internal public methods instead. See https://www.drupal.org/node/3367304', E_USER_DEPRECATED);

/**
 * Provides non-reusable blazy attribute static methods.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module.
 *
 * @deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use
 *   non-internal public methods instead.
 * @see https://www.drupal.org/node/3367291
 */
class BlazyAttribute extends Attributes {}
