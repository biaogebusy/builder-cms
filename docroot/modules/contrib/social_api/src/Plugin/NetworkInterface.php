<?php

namespace Drupal\social_api\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * Defines an interface for Social Network plugins.
 */
interface NetworkInterface extends PluginInspectionInterface, ContainerFactoryPluginInterface {

  /**
   * Gets the underlying SDK library.
   *
   * @return mixed
   *   The SDK client.
   */
  public function getSdk(): mixed;

}
