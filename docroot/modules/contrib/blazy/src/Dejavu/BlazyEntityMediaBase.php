<?php

namespace Drupal\blazy\Dejavu;

use Drupal\blazy\Field\BlazyEntityMediaBase as EntityMediaBase;

@trigger_error('The ' . __NAMESPACE__ . '\BlazyEntityMediaBase is deprecated in blazy:8.x-2.9 and is removed from blazy:8.x-3.0. Use or extend Blazy formatter classes instead. See https://www.drupal.org/node/3103018', E_USER_DEPRECATED);

/**
 * Deprecated in blazy:8.x-2.9.
 *
 * Not used by sub-modules, safe to delete.
 *
 * @todo enable post blazy:2.17.
 * @deprecated in blazy:8.x-2.9 and is removed from blazy:8.x-3.0. Use
 *   Drupal\blazy\Field\BlazyEntityMediaBase instead.
 * @see https://www.drupal.org/node/3367304
 */
abstract class BlazyEntityMediaBase extends EntityMediaBase {}
