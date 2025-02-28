<?php

namespace Drupal\image_effects\Plugin;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;

/**
 * Interface for image_effects base plugin.
 */
interface ImageEffectsPluginBaseInterface extends ConfigurableInterface, ContainerFactoryPluginInterface, PluginFormInterface {

  /**
   * Return a form element to select the plugin content.
   *
   * @param array $options
   *   (Optional) An array of additional Form API keys and values.
   *
   * @return array
   *   Render array of the form element.
   */
  public function selectionElement(array $options = []);

  /**
   * Get the image_effects plugin type.
   *
   * @return string
   *   The plugin type.
   */
  public function getType();

  /**
   * Determines if plugin can be used.
   *
   * @return bool
   *   TRUE if the plugin is available.
   */
  public static function isAvailable();

}
