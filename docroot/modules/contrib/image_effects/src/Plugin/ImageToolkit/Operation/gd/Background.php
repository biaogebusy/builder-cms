<?php

declare(strict_types=1);

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\gd;

use Drupal\Core\ImageToolkit\Attribute\ImageToolkitOperation;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\image_effects\Plugin\ImageToolkit\Operation\BackgroundTrait;
use Drupal\system\Plugin\ImageToolkit\Operation\gd\GDImageToolkitOperationBase;

/**
 * Defines GD Background operation.
 */
#[ImageToolkitOperation(
  id: 'image_effects_gd_background',
  toolkit: 'gd',
  operation: 'background',
  label: new TranslatableMarkup('Background'),
  description: new TranslatableMarkup('Places the source image over a background image.'),
)]
class Background extends GDImageToolkitOperationBase {

  use BackgroundTrait;
  use GDOperationTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    // Preserves original image.
    $original_image = $this->getToolkit()->getImage();

    // Prepare a new image.
    $data = [
      'width' => $arguments['background_image']->getWidth(),
      'height' => $arguments['background_image']->getHeight(),
      'extension' => image_type_to_extension($this->getToolkit()->getType(), FALSE),
      'transparent_color' => $this->getToolkit()->getTransparentColor(),
      'is_temp' => TRUE,
    ];
    if (!$this->getToolkit()->apply('create_new', $data)) {
      // In case of failure, restore the original image.
      $this->getToolkit()->setImage($original_image);
      return FALSE;
    }

    // Overlay background at 0,0.
    $success = $this->imageCopyMergeAlpha(
      $this->getToolkit()->getImage(),
      $arguments['background_image']->getToolkit()->getImage(),
      0,
      0,
      0,
      0,
      $arguments['background_image']->getWidth(),
      $arguments['background_image']->getHeight(),
      100
    );
    if (!$success) {
      // In case of failure, restore the original image.
      $this->getToolkit()->setImage($original_image);
      return FALSE;
    }

    // Overlay original source at offset.
    $success = $this->imageCopyMergeAlpha(
      $this->getToolkit()->getImage(),
      $original_image,
      $arguments['x_offset'],
      $arguments['y_offset'],
      0,
      0,
      imagesx($original_image),
      imagesy($original_image),
      $arguments['opacity']
    );
    if (!$success) {
      $this->getToolkit()->setImage($original_image);
    }
    return $success;
  }

}
