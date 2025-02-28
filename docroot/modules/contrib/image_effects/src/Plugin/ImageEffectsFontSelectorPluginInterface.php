<?php

namespace Drupal\image_effects\Plugin;

/**
 * Fonts handler interface.
 *
 * Defines the methods that font selector plugins have to implement.
 */
interface ImageEffectsFontSelectorPluginInterface extends ImageEffectsPluginBaseInterface {

  /**
   * Get the description of a font file.
   *
   * @param string $uri
   *   The URI of the font file.
   *
   * @return string
   *   The description of the font.
   */
  public function getDescription($uri);

}
