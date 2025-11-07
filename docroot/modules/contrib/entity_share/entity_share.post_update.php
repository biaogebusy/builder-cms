<?php

/**
 * @file
 * Post-update functions for Entity Share.
 */

declare(strict_types = 1);

/**
 * Clear cache because custom HTML route providers had been removed.
 */
function entity_share_post_update_remove_custom_html_route_provider() {
  // Empty post-update hook.
}
