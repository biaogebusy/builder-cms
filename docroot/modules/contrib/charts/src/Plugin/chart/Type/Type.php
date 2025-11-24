<?php

namespace Drupal\charts\Plugin\chart\Type;

use Drupal\Core\Plugin\PluginBase;

/**
 * Chart type class plugins.
 */
class Type extends PluginBase implements TypeInterface {

  /**
   * {@inheritdoc}
   */
  public function getId() {
    return $this->pluginDefinition['id'];
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getAxis() {
    return $this->pluginDefinition['axis'];
  }

  /**
   * {@inheritdoc}
   */
  public function isAxisInverted() {
    return $this->pluginDefinition['axis_inverted'] == TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function supportStacking() {
    return $this->pluginDefinition['stacking'] == TRUE;
  }

}
