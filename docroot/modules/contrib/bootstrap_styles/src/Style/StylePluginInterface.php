<?php

namespace Drupal\bootstrap_styles\Style;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Provides an interface defining a Styles Group.
 */
interface StylePluginInterface extends PluginInspectionInterface, ContainerFactoryPluginInterface {

  /**
   * Return the name of the Styles Group form plugin.
   *
   * @return string
   *   The name of styles group.
   */
  public function getTitle();

}
