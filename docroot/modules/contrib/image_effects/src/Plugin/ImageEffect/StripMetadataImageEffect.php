<?php

declare(strict_types=1);

namespace Drupal\image_effects\Plugin\ImageEffect;

use Drupal\Core\Image\ImageInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\image\Attribute\ImageEffect;
use Drupal\image\ImageEffectBase;

/**
 * Strips metadata from an image.
 */
#[ImageEffect(
  id: 'image_effects_strip_metadata',
  label: new TranslatableMarkup('Strip metadata'),
  description: new TranslatableMarkup('Strips metadata from images.'),
)]
class StripMetadataImageEffect extends ImageEffectBase {

  /**
   * {@inheritdoc}
   */
  public function applyEffect(ImageInterface $image) {
    return $image->apply('strip');
  }

}
