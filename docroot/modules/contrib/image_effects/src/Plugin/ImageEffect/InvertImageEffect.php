<?php

namespace Drupal\image_effects\Plugin\ImageEffect;

use Drupal\Core\Image\ImageInterface;
use Drupal\image\ImageEffectBase;

/**
 * Inverts image color.
 *
 * @ImageEffect(
 *   id = "image_effects_invert",
 *   label = @Translation("Invert"),
 *   description = @Translation("Invert image color.")
 * )
 */
class InvertImageEffect extends ImageEffectBase {

  /**
   * {@inheritdoc}
   */
  public function applyEffect(ImageInterface $image) {
    return $image->apply('invert');
  }

}
