<?php

/**
 * @file
 * Post update functions for Simple OAuth.
 */

/**
 * Create path alias for JWKS endpoint to support /.well-known/jwks.json.
 */
function simple_oauth_post_update_create_jwks_path_alias() {
  // Load the .install file to access the helper function.
  \Drupal::moduleHandler()->loadInclude('simple_oauth', 'install');
  _simple_oauth_create_jwks_alias();
}
