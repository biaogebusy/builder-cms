<?php

namespace Drupal\ai\Traits\OperationType;

use Drupal\ai\Service\HostnameFilter;

/**
 * Chat specific base methods.
 *
 * @package Drupal\ai\Traits\OperationType
 */
trait ChatTrait {

  /**
   * {@inheritDoc}
   */
  public function getMaxInputTokens(string $model_id): int {
    return 1024;
  }

  /**
   * {@inheritDoc}
   */
  public function getMaxOutputTokens(string $model_id): int {
    // Since this method was added later, we try to apply some values.
    // @phpstan-ignore-next-line
    $default = $this->getAvailableConfiguration('chat', $model_id);
    if (isset($default['max_tokens']['constraints']['max'])) {
      return $default['max_tokens']['constraints']['max'];
    }
    return 1024;
  }

  /**
   * Add the HostnameFilter service.
   *
   * @return \Drupal\ai\Service\HostnameFilter
   *   The hostname filter service.
   */
  protected function getHostnameFilterService(): HostnameFilter {
    return \Drupal::service('ai.hostname_filter_service');
  }

}
