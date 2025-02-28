<?php

/**
 * @file
 * Describe hooks provided by the autologout module.
 */

/**
 * Prevent autologout logging a user out.
 *
 * This allows other modules to indicate that a page should not be included
 * in the autologout checks. This works in the same way as not ticking the
 * enforce on admin pages option for autologout which stops a user being logged
 * out of admin pages.
 *
 * @return bool
 *   Return TRUE if you do not want the user to be logged out.
 *   Return FALSE (or nothing) if you want to leave the autologout
 *   process alone.
 */
function hook_autologout_prevent() {
  // Don't include autologout JS checks on ajax callbacks.
  $path_args = explode('/', current_path());
  $blacklist = [
    'ajax',
    'autologout_ajax_logout',
    'autologout_ajax_set_last',
  ];

  if (in_array($path_args[0], $blacklist)) {
    return TRUE;
  }
}

/**
 * Keep a login alive whilst the user is on a particular page.
 *
 * @return bool
 *   By returning TRUE from this function the JS which talks to autologout
 *   module is included in the current page request and periodically dials back
 *   to the server to keep the login alive.
 *   Return FALSE (or nothing) to just use the standard behaviour.
 */
function hook_autologout_refresh_only() {
  // Check to see if an open admin page will keep login alive.
  if (\Drupal::service('router.admin_context')->isAdminRoute(routeMatch()->getRouteObject()) && !\Drupal::config('autologout.settings')->get('enforce_admin')) {
    return TRUE;
  }
}
