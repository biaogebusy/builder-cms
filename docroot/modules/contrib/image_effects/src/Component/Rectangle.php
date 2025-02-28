<?php

namespace Drupal\image_effects\Component;

/**
 * Rectangle rotation algebra class.
 *
 * This class is used by the image system to abstract, from toolkit
 * implementations, the calculation of the expected dimensions resulting from
 * an image rotate operation.
 *
 * Different versions of the libgd library embedded in PHP, and alternative
 * toolkits, use different algorithms to perform the rotation of an image and
 * result in different dimensions of the output image. This prevents
 * predictability of the final image size for instance by the image rotate
 * effect, or by image toolkit rotate operations.
 *
 * This class implements a calculation algorithm that returns, given input
 * width, height and rotation angle, dimensions of the expected image after
 * rotation that are consistent with those produced by the GD rotate image
 * toolkit operation using libgd 2.2.2 and above. The code is taken from the C++
 * libgd implementation that uses affine transformation math, ported for use in
 * PHP.
 *
 * @see https://github.com/libgd/libgd/blob/master/src/gd_interpolation.c
 * @see https://github.com/libgd/libgd/blob/master/src/gd_matrix.c
 * @see https://en.wikipedia.org/wiki/Affine_transformation
 */
class Rectangle {

  /**
   * The width of the rectangle.
   *
   * @var int
   */
  private $width;

  /**
   * The height of the rectangle.
   *
   * @var int
   */
  private $height;

  /**
   * The width of the rotated rectangle.
   *
   * @var int
   */
  private $boundingWidth;

  /**
   * The height of the rotated rectangle.
   *
   * @var int
   */
  private $boundingHeight;

  /**
   * Constructs a new Rectangle object.
   *
   * @param int $width
   *   The width of the rectangle.
   * @param int $height
   *   The height of the rectangle.
   */
  public function __construct(int $width, int $height) {
    if ($width > 0 && $height > 0) {
      $this->width = $width;
      $this->height = $height;
      $this->boundingWidth = $width;
      $this->boundingHeight = $height;
    }
    else {
      throw new \InvalidArgumentException("Invalid dimensions ({$width}x{$height}) specified for a Rectangle object");
    }
  }

  /**
   * Rotates the rectangle.
   *
   * @param float $angle
   *   Rotation angle.
   *
   * @return $this
   */
  public function rotate(float $angle): self {
    if ((int) $angle == $angle && $angle % 90 == 0) {
      // For rotations that are multiple of 90 degrees, no trigonometry is
      // needed.
      if (abs($angle) % 180 == 0) {
        $this->boundingWidth = $this->width;
        $this->boundingHeight = $this->height;
      }
      else {
        $this->boundingWidth = $this->height;
        $this->boundingHeight = $this->width;
      }
    }
    else {
      $rotate_affine_transform = $this->gdAffineRotate($angle);
      $bounding_box = $this->gdTransformAffineBoundingBox($this->width, $this->height, $rotate_affine_transform);
      $this->boundingWidth = $bounding_box['width'];
      $this->boundingHeight = $bounding_box['height'];
    }
    return $this;
  }

  /**
   * Set up a rotation affine transform.
   *
   * @param float $angle
   *   Rotation angle.
   *
   * @return array
   *   The resulting affine transform.
   *
   * @see https://libgd.github.io/manuals/2.2.2/files/gd_matrix-c.html#gdAffineRotate
   */
  private function gdAffineRotate(float $angle): array {
    $rad = deg2rad($angle);
    $sin_t = sin($rad);
    $cos_t = cos($rad);
    return [$cos_t, $sin_t, -$sin_t, $cos_t, 0, 0];
  }

  /**
   * Applies an affine transformation to a point.
   *
   * @param array $src
   *   The source point.
   * @param array $affine
   *   The affine transform to apply.
   *
   * @return array
   *   The resulting point.
   *
   * @see https://libgd.github.io/manuals/2.2.2/files/gd_matrix-c.html#gdAffineApplyToPointF
   */
  private function gdAffineApplyToPointF(array $src, array $affine): array {
    return [
      'x' => $src['x'] * $affine[0] + $src['y'] * $affine[2] + $affine[4],
      'y' => $src['x'] * $affine[1] + $src['y'] * $affine[3] + $affine[5],
    ];
  }

  /**
   * Returns the bounding box of an affine transform applied to a rectangle.
   *
   * @param int $width
   *   The width of the rectangle.
   * @param int $height
   *   The height of the rectangle.
   * @param array $affine
   *   The affine transform to apply.
   *
   * @return array
   *   The resulting bounding box.
   *
   * @see https://libgd.github.io/manuals/2.1.1/files/gd_interpolation-c.html#gdTransformAffineBoundingBox
   */
  private function gdTransformAffineBoundingBox(int $width, int $height, array $affine): array {
    $extent = [];
    $extent[0]['x'] = 0.0;
    $extent[0]['y'] = 0.0;
    $extent[1]['x'] = $width;
    $extent[1]['y'] = 0.0;
    $extent[2]['x'] = $width;
    $extent[2]['y'] = $height;
    $extent[3]['x'] = 0.0;
    $extent[3]['y'] = $height;

    for ($i = 0; $i < 4; $i++) {
      $extent[$i] = $this->gdAffineApplyToPointF($extent[$i], $affine);
    }
    $min = $extent[0];
    $max = $extent[0];

    for ($i = 1; $i < 4; $i++) {
      $min['x'] = $min['x'] > $extent[$i]['x'] ? $extent[$i]['x'] : $min['x'];
      $min['y'] = $min['y'] > $extent[$i]['y'] ? $extent[$i]['y'] : $min['y'];
      $max['x'] = $max['x'] < $extent[$i]['x'] ? $extent[$i]['x'] : $max['x'];
      $max['y'] = $max['y'] < $extent[$i]['y'] ? $extent[$i]['y'] : $max['y'];
    }

    return [
      'x' => (int) $min['x'],
      'y' => (int) $min['y'],
      'width' => (int) ceil(($max['x'] - $min['x'])) + 1,
      'height' => (int) ceil($max['y'] - $min['y']) + 1,
    ];
  }

  /**
   * Gets the bounding width of the rectangle.
   *
   * @return int
   *   The bounding width of the rotated rectangle.
   */
  public function getBoundingWidth(): int {
    return $this->boundingWidth;
  }

  /**
   * Gets the bounding height of the rectangle.
   *
   * @return int
   *   The bounding height of the rotated rectangle.
   */
  public function getBoundingHeight(): int {
    return $this->boundingHeight;
  }

}
