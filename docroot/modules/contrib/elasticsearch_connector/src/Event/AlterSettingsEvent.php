<?php

namespace Drupal\elasticsearch_connector\Event;

use Drupal\search_api\IndexInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * An event allowing ElasticSearch settings to be altered.
 */
class AlterSettingsEvent extends Event {

  /**
   * Creates a new event.
   *
   * @param array $settings
   *   ElasticSearch settings that can be altered.
   * @param array $backendConfig
   *   The server backend config.
   * @param \Drupal\search_api\IndexInterface $index
   *   The Search API index.
   */
  public function __construct(
    protected array $settings,
    protected array $backendConfig,
    protected IndexInterface $index,
  ) {}

  /**
   * Alter the settings.
   *
   * @param array $settings
   *   The settings.
   */
  public function setSettings(array $settings): void {
    $this->settings = $settings;
  }

  /**
   * Get the settings.
   */
  public function getSettings(): array {
    return $this->settings;
  }

  /**
   * Get the backend config.
   *
   * @return array
   *   The backend config.
   */
  public function getBackendConfig(): array {
    return $this->backendConfig;
  }

  /**
   * Get the index.
   */
  public function getIndex(): IndexInterface {
    return $this->index;
  }

}
