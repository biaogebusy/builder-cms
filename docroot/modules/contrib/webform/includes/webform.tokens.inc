<?php

/**
 * @file
 * Builds placeholder replacement tokens for webforms and submissions.
 */

use Drupal\Core\Hook\Attribute\LegacyHook;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\webform\Hook\WebformTokensHooks;

/**
 * Implements hook_token_info().
 */
#[LegacyHook]
function webform_token_info() {
  return \Drupal::service(WebformTokensHooks::class)->tokenInfo();
}

/**
 * Implements hook_tokens().
 */
#[LegacyHook]
function webform_tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
  return \Drupal::service(WebformTokensHooks::class)->tokens($type, $tokens, $data, $options, $bubbleable_metadata);
}
