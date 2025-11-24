<?php

declare(strict_types=1);

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\imagemagick;

use Drupal\Core\ImageToolkit\Attribute\ImageToolkitOperation;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\image_effects\Plugin\ImageToolkit\Operation\BackgroundTrait;
use Drupal\imagemagick\Plugin\ImageToolkit\Operation\imagemagick\ImagemagickImageToolkitOperationBase;

/**
 * Defines ImageMagick Background operation.
 */
#[ImageToolkitOperation(
  id: 'image_effects_imagemagick_background',
  toolkit: 'imagemagick',
  operation: 'background',
  label: new TranslatableMarkup('Background'),
  description: new TranslatableMarkup('Places the source image over a background image.'),
)]
class Background extends ImagemagickImageToolkitOperationBase {

  use BackgroundTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    // Background image local path.
    $local_path = $arguments['background_image']->getToolkit()->ensureSourceLocalPath();
    if ($local_path !== '') {
      $image_path = $local_path;
    }
    else {
      $source_path = $arguments['background_image']->getToolkit()->getSource();
      throw new \InvalidArgumentException("Missing local path for image at {$source_path}");
    }

    // Reset any gravity settings from earlier effects.
    $op = ['-gravity', 'None'];

    // Set transparent background.
    $op[] = '-background';
    $op[] = 'transparent';

    // Set the dimensions of the background.
    $w = $arguments['background_image']->getToolkit()->getWidth();
    $h = $arguments['background_image']->getToolkit()->getHeight();
    // Reverse offset sign. Offset arguments require a sign in front.
    $x = $arguments['x_offset'] > 0 ? ('-' . $arguments['x_offset']) : ('+' . -$arguments['x_offset']);
    $y = $arguments['y_offset'] > 0 ? ('-' . $arguments['y_offset']) : ('+' . -$arguments['y_offset']);
    $op[] = "-extent";
    $op[] = "{$w}x{$h}{$x}{$y}";

    // Add the background image.
    $op[] = $image_path;

    // Compose it with the destination.
    if ($arguments['opacity'] == 100) {
      $op[] = '-compose';
      $op[] = 'dst-over';
      $op[] = '-composite';
    }
    else {
      $op[] = '-compose';
      $op[] = 'blend';
      $op[] = '-define';
      $op[] = "compose:args=100,{$arguments['opacity']}";
      $op[] = '-composite';
    }

    $this->addArguments($op);
    $this->getToolkit()
      ->setWidth($w)
      ->setHeight($h);
    return TRUE;
  }

}
