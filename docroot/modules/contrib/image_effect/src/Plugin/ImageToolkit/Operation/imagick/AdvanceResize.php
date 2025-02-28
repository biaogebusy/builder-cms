<?php

namespace Drupal\image_effect\Plugin\ImageToolkit\Operation\imagick;

use Drupal\imagick\Plugin\ImageToolkit\Operation\imagick\ImagickOperationBase;
use Imagick;
use ImagickPixel;
/**
 * Defines imagick advance resize operation.
 *
 * @ImageToolkitOperation(
 *   id = "imagick_advance_resize",
 *   toolkit = "imagick",
 *   operation = "advance_resize",
 *   label = @Translation("Advance Resize"),
 *   description = @Translation("Advance Resizes an image to the given dimensions, add white background if need.")
 * )
 */
class AdvanceResize extends ImagickOperationBase {

  /**
   * {@inheritdoc}
   */
  protected function arguments() {
    return [
      'width' => [
        'description' => 'The new width of the resized image, in pixels',
      ],
      'height' => [
        'description' => 'The new height of the resized image, in pixels',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function validateArguments(array $arguments) {
    // Assure integers for all arguments.
    $arguments['width'] = (int) round($arguments['width']);
    $arguments['height'] = (int) round($arguments['height']);

    // Fail when width or height are 0 or negative.
    if ($arguments['width'] <= 0) {
      throw new \InvalidArgumentException("Invalid width ('{$arguments['width']}') specified for the image 'resize' operation");
    }
    if ($arguments['height'] <= 0) {
      throw new \InvalidArgumentException("Invalid height ('{$arguments['height']}') specified for the image 'resize' operation");
    }

    return $arguments;
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments = []) {
    // Create a new resource of the required dimensions, and copy and resize
    // the original resource on it with resampling. Destroy the original
    // resource upon success.
    $w=$arguments['width'];
    $h=$arguments['height'];
    $src_x=0;
    $src_y=0;
    $src_w=$this->getToolkit()->getWidth();
    $src_h=$this->getToolkit()->getHeight();
    $dst_w=$src_w;
    $dst_h=$src_h;
    $dst_x=0;
    $dst_y=0;
    if($w>$src_w)
      $dst_x=($w-$src_w)/2;
    if($h>$src_h)
      $dst_y=($h-$src_h)/2;

    $original_resource = $this->getToolkit()->getResource();
    $dst_image = new Imagick();
    $dst_image->newImage($w, $h, new ImagickPixel("white"));
    $dst_image->compositeImage($original_resource, Imagick::COMPOSITE_DEFAULT, $dst_x, $dst_y);
    $image_format = $original_resource->getImageFormat();
    $dst_image->setImageFormat($image_format);
    $original_resource->destroy();
    $this->getToolkit()->setResource($dst_image);

    return TRUE;
  }

}
