<?php

declare(strict_types=1);

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\gd;

use Drupal\Core\ImageToolkit\Attribute\ImageToolkitOperation;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\image_effects\Plugin\ImageToolkit\Operation\ImagemagickArgumentsTrait;
use Drupal\system\Plugin\ImageToolkit\Operation\gd\GDImageToolkitOperationBase;

/**
 * Defines GD 'Imagemagick arguments' operation.
 */
#[ImageToolkitOperation(
  id: 'image_effects_gd_imagemagick_arguments',
  toolkit: 'gd',
  operation: 'imagemagick_arguments',
  label: new TranslatableMarkup('ImageMagick arguments'),
  description: new TranslatableMarkup('Directly execute ImageMagick command line arguments.'),
)]
class ImagemagickArguments extends GDImageToolkitOperationBase {

  use ImagemagickArgumentsTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    // Obviously, we can not do anything here. This operation is a no-op in GD,
    // this is just defined to avoid effects to fail when GD toolkit is in use.
    return TRUE;
  }

}
