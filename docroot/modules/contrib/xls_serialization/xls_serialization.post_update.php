<?php

/**
 * @file
 * Post-update functions for the xls_serialization module.
 */

/**
 * Forces a container rebuild for changes introduced in 2.1.0.
 */
function xls_serialization_post_update_2_1_0_container_rebuild(): void {
  // Empty on purpose. This triggers a container and cache rebuild.
}
