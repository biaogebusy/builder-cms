<?php

declare(strict_types = 1);

namespace Drupal\sms\Plugin;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Plugin\DefaultSingleLazyPluginCollection;

/**
 * Provides a container for lazily loading SMS Gateway plugins.
 */
class SmsGatewayPluginCollection extends DefaultSingleLazyPluginCollection {

  /**
   * Constructs a new SmsGatewayPluginCollection object.
   *
   * @param \Drupal\Component\Plugin\PluginManagerInterface $manager
   *   The manager to be used for instantiating plugins.
   * @param string $instance_id
   *   The ID of the plugin instance.
   * @param array $configuration
   *   An array of configuration.
   */
  public function __construct(PluginManagerInterface $manager, $instance_id, array $configuration) {
    $this->manager = $manager;
    if ($instance_id !== NULL) {
      $this->addInstanceId($instance_id, $configuration);
    }
  }

}
