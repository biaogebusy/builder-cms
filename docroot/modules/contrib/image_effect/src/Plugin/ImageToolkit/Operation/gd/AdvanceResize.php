<?php

namespace Drupal\image_effect\Plugin\ImageToolkit\Operation\gd;

use Drupal\system\Plugin\ImageToolkit\Operation\gd\GDImageToolkitOperationBase;
/**
 * Defines GD2 advance resize operation.
 *
 * @ImageToolkitOperation(
 *   id = "gd_advance_resize",
 *   toolkit = "gd",
 *   operation = "advance_resize",
 *   label = @Translation("Advance Resize"),
 *   description = @Translation("Advance Resizes an image to the given dimensions, add white background if need.")
 * )
 */
class AdvanceResize extends GDImageToolkitOperationBase {

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
    $data = [
      'width' => $arguments['width'],
      'height' => $arguments['height'],
      'extension' => image_type_to_extension($this->getToolkit()->getType(), FALSE),
      'transparent_color' => $this->getToolkit()->getTransparentColor(),
      'is_temp' => TRUE,
    ];
    if ($this->getToolkit()->apply('create_new', $data)) {
      $backgroundColor = imagecolorallocate($this->getToolkit()->getResource(), 255, 255, 255);
      imagefill($this->getToolkit()->getResource(), 0, 0, $backgroundColor);
      if (imagecopyresampled($this->getToolkit()->getResource(), $original_resource, $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h)) {
        imagedestroy($original_resource);
        return TRUE;
      }
      else {
        // In case of failure, destroy the temporary resource and restore
        // the original one.
        imagedestroy($this->getToolkit()->getResource());
        $this->getToolkit()->setResource($original_resource);
      }
    }
    return FALSE;
  }

}
