<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Plugin;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Plugin\DefaultLazyPluginCollection;

/**
 * Plugin collection for import processor plugins.
 *
 * This is needed to override initializePlugin() because the saved configuration
 * for import processors doesn't contain the plugin ID.
 */
class ImportProcessorPluginCollection extends DefaultLazyPluginCollection {

  /**
   * {@inheritdoc}
   */
  protected function initializePlugin($instance_id) {
    $configuration = $this->configurations[$instance_id] ?? [];
    if (empty($configuration)) {
      throw new PluginNotFoundException($instance_id);
    }
    $this->set($instance_id, $this->manager->createInstance($instance_id, $configuration));
  }

}
