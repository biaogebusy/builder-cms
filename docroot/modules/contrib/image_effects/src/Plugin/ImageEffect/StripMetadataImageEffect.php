<?php

namespace Drupal\image_effects\Plugin\ImageEffect;

use Drupal\Core\Image\ImageInterface;
use Drupal\image\ImageEffectBase;

/**
 * Strips metadata from an image resource.
 *
 * @ImageEffect(
 *   id = "image_effects_strip_metadata",
 *   label = @Translation("Strip metadata"),
 *   description = @Translation("Strips metadata from images.")
 * )
 */
class StripMetadataImageEffect extends ImageEffectBase {

  /**
   * {@inheritdoc}
   */
  public function applyEffect(ImageInterface $image) {
    return $image->apply('strip');
  }

}
