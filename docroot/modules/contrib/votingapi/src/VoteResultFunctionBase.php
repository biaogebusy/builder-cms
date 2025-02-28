<?php

namespace Drupal\votingapi;

use Drupal\Core\Plugin\PluginBase;

/**
 * Base class for plugins which provide a function to compute the vote result.
 */
abstract class VoteResultFunctionBase extends PluginBase implements VoteResultFunctionInterface {

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return $this->t($this->pluginDefinition['label']);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t($this->pluginDefinition['description']);
  }

}
