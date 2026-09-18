<?php

/**
 * @file
 * Captcha updates once other modules have made their own updates.
 */

/**
 * Ensure the container cache is cleared.
 */
function captcha_post_update_refresh_captcha_helper_service() {
  drupal_flush_all_caches();
}
