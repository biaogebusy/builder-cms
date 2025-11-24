<?php

declare(strict_types=1);

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\gd;

use Drupal\Core\ImageToolkit\Attribute\ImageToolkitOperation;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\image_effects\Component\PositionedRectangle;
use Drupal\image_effects\Plugin\ImageToolkit\Operation\SetCanvasTrait;
use Drupal\system\Plugin\ImageToolkit\Operation\gd\GDImageToolkitOperationBase;

/**
 * Defines GD2 set canvas operation.
 */
#[ImageToolkitOperation(
  id: 'image_effects_gd_set_canvas',
  toolkit: 'gd',
  operation: 'set_canvas',
  label: new TranslatableMarkup('Set canvas'),
  description: new TranslatableMarkup('Lay the image over a colored canvas.'),
)]
class SetCanvas extends GDImageToolkitOperationBase {

  use SetCanvasTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    // Store the original image.
    $original_image = $this->getToolkit()->getImage();

    // Prepare the canvas.
    $data = [
      'width' => $arguments['width'],
      'height' => $arguments['height'],
      'extension' => image_type_to_extension($this->getToolkit()->getType(), FALSE),
      'transparent_color' => $this->getToolkit()->getTransparentColor(),
      'is_temp' => TRUE,
    ];
    if (!$this->getToolkit()->apply('create_new', $data)) {
      return FALSE;
    }

    // Fill the canvas with required color.
    $data = [
      'rectangle' => new PositionedRectangle($arguments['width'], $arguments['height']),
      'fill_color' => $arguments['canvas_color'],
    ];
    if (!$this->getToolkit()->apply('draw_rectangle', $data)) {
      return FALSE;
    }

    // Overlay the current image on the canvas.
    imagealphablending($original_image, TRUE);
    imagesavealpha($original_image, TRUE);
    imagealphablending($this->getToolkit()->getImage(), TRUE);
    imagesavealpha($this->getToolkit()->getImage(), TRUE);
    if (imagecopy($this->getToolkit()->getImage(), $original_image, $arguments['x_pos'], $arguments['y_pos'], 0, 0, imagesx($original_image), imagesy($original_image))) {
      return TRUE;
    }
    else {
      // In case of failure, restore the original image.
      $this->getToolkit()->setimage($original_image);
    }
    return FALSE;
  }

}
