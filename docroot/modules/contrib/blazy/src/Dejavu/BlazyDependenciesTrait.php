<?php

namespace Drupal\blazy\Dejavu;

use Drupal\blazy\Field\BlazyDependenciesTrait as DependenciesTrait;

@trigger_error('The ' . __NAMESPACE__ . '\BlazyDependenciesTrait is deprecated in blazy:8.x-2.9 and is removed from blazy:8.x-3.0. Use or extend Blazy formatter classes instead. See https://www.drupal.org/node/3103018', E_USER_DEPRECATED);

/**
 * Deprecated in blazy:8.x-2.9.
 *
 * @deprecated in blazy:8.x-2.9 and is removed from blazy:8.x-3.0. Use or extend
 * Blazy formatter classes instead.
 * @see https://www.drupal.org/node/3103018
 */
trait BlazyDependenciesTrait {

  use DependenciesTrait;

}
