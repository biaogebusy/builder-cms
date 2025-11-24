<?php

declare(strict_types=1);

namespace Drupal\image_effects\Plugin\ImageEffect;

use Drupal\Core\Image\ImageInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\image\Attribute\ImageEffect;
use Drupal\image\ImageEffectBase;

/**
 * Inverts image color.
 */
#[ImageEffect(
  id: 'image_effects_invert',
  label: new TranslatableMarkup('Invert'),
  description: new TranslatableMarkup('Invert image color.'),
)]
class InvertImageEffect extends ImageEffectBase {

  /**
   * {@inheritdoc}
   */
  public function applyEffect(ImageInterface $image) {
    return $image->apply('invert');
  }

}
