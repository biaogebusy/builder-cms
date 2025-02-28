<?php

/**
 * @file
 * Hooks provided by the Require Login module.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Alter login requirements evaluation result.
 *
 * @param bool $eval
 *   The completed evaluation boolean.
 */
function hook_require_login_evaluation_alter(bool &$eval): void {
  // Only force login on the example route name.
  $eval = \Drupal::routeMatch()->getRouteName() === 'example.page';
}

/**
 * @} End of "addtogroup hooks".
 */
