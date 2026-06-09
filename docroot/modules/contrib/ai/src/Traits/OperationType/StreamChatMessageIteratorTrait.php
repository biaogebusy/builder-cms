<?php

namespace Drupal\ai\Traits\OperationType;

use Drupal\ai\Service\HostnameFilter;

/**
 * Chat specific base methods.
 *
 * @package Drupal\ai\Traits\OperationType
 */
trait StreamChatMessageIteratorTrait {

  /**
   * Load the hostname filter service.
   *
   * @return \Drupal\ai\Service\HostnameFilter
   *   The hostname filter service.
   */
  protected function getHostnameFilterService(): HostnameFilter {
    $service = \Drupal::service('ai.hostname_filter_service');
    // Streaming always removes all links, but not in fiber.
    if (!\Fiber::getCurrent()) {
      $service->setPlainTextMode(TRUE);
    }
    return $service;
  }

}
