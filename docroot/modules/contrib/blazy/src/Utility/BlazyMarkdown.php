<?php

namespace Drupal\blazy\Utility;

@trigger_error('The ' . __NAMESPACE__ . '\BlazyMarkdown is deprecated in blazy:8.x-2.17 and is removed from blazy:8.x-3.0. Use \Drupal\blazy\BlazyInterface::markdown() instead. See https://www.drupal.org/node/3103018', E_USER_DEPRECATED);

/**
 * Deprecated in blazy:8.x-2.17.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module, or its sub-modules.
 *
 * @deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use
 *   \Drupal\blazy\BlazyInterface::markdown() instead.
 * @see https://www.drupal.org/node/3103018
 */
class BlazyMarkdown extends Markdown {}
