<?php

namespace Drupal\tour;

use Drupal\Core\Plugin\DefaultLazyPluginCollection;

/**
 * A collection of tips.
 */
class TipsPluginCollection extends DefaultLazyPluginCollection {

  /**
   * {@inheritdoc}
   */
  protected $pluginKey = 'plugin';

}
