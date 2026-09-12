<?php

namespace Drupal\blazy_layout\Plugin\Layout;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * Provides an interface for BlazyLayout methods.
 */
interface BlazyLayoutInterface extends ContainerFactoryPluginInterface {

  /**
   * Returns the region configurations based on the key.
   *
   * @param string $name
   *   The region name.
   * @param string $key
   *   The settings key.
   *
   * @return string
   *   The region settings value.
   */
  public function getRegionConfig(string $name, string $key): string;

  /**
   * Sets the region configurations based on the key.
   *
   * @param string $name
   *   The region name.
   * @param array $values
   *   The settings values.
   *
   * @return $this
   *   The region settings value.
   */
  public function setRegionConfig(string $name, array $values): self;

}
