<?php

declare(strict_types=1);

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\gd;

use Drupal\Component\Utility\Color;
use Drupal\image_effects\Component\ColorUtility;
use Drupal\image_effects\Component\GdGaussianBlur;
use Drupal\image_effects\Component\GdImageAnalysis;
use Drupal\image_effects\Component\MatrixUtility;
use Drupal\image_effects\Component\PositionedRectangle;

/**
 * Trait for GD image toolkit operations.
 */
trait GDOperationTrait {

  /**
   * Allocates a GD color from an RGBA hexadecimal.
   *
   * @param \GDImage $image
   *   An image.
   * @param string $rgba_hex
   *   A string specifing an RGBA color in the format '#RRGGBBAA'.
   *
   * @return int
   *   A GD color index.
   */
  protected function allocateColorFromRgba(\GDImage $image, string $rgba_hex): int {
    [$r, $g, $b, $alpha] = array_values($this->hexToRgba($rgba_hex));
    return imagecolorallocatealpha($image, $r, $g, $b, $alpha);
  }

  /**
   * Convert a RGBA hex to its RGBA integer GD components.
   *
   * GD expects a value between 0 and 127 for alpha, where 0 indicates
   * completely opaque while 127 indicates completely transparent.
   * RGBA hexadecimal notation has #00 for transparent and #FF for
   * fully opaque.
   *
   * @param string $rgba_hex
   *   A string specifing an RGBA color in the format '#RRGGBBAA'.
   *
   * @return array|false
   *   An array with four elements for red, green, blue, and alpha.
   */
  protected function hexToRgba(string $rgba_hex): array|FALSE {
    $rgbHex = mb_substr($rgba_hex, 0, 7);
    try {
      $rgb = Color::hexToRgb($rgbHex);
      $opacity = ColorUtility::rgbaToOpacity($rgba_hex);
      $alpha = 127 - (int) floor(($opacity / 100) * 127);
      $rgb['alpha'] = $alpha;
      return $rgb;
    }
    catch (\InvalidArgumentException $e) {
      return FALSE;
    }
  }

  /**
   * Convert a rectangle to a sequence of point coordinates.
   *
   * GD requires a simple array of point coordinates in its
   * imagepolygon() function.
   *
   * @param \Drupal\image_effects\Component\PositionedRectangle $rect
   *   A PositionedRectangle object.
   *
   * @return array
   *   A simple array of 8 point coordinates.
   */
  protected function getRectangleCorners(PositionedRectangle $rect): array {
    $points = [];
    foreach (['c_d', 'c_c', 'c_b', 'c_a'] as $c) {
      $point = $rect->getPoint($c);
      $points[] = $point[0];
      $points[] = $point[1];
    }
    return $points;
  }

  /**
   * Copy and merge part of an image, preserving alpha.
   *
   * The standard imagecopymerge() function in PHP GD fails to preserve the
   * alpha information of two merged images. This method implements the
   * workaround described in
   * http://php.net/manual/en/function.imagecopymerge.php#92787
   *
   * @param \GDImage $dst_im
   *   Destination image.
   * @param \GDImage $src_im
   *   Source image.
   * @param int $dst_x
   *   X-coordinate of destination point.
   * @param int $dst_y
   *   Y-coordinate of destination point.
   * @param int $src_x
   *   X-coordinate of source point.
   * @param int $src_y
   *   Y-coordinate of source point.
   * @param int $src_w
   *   Source width.
   * @param int $src_h
   *   Source height.
   * @param int $pct
   *   Opacity of the source image in percentage.
   *
   * @return bool
   *   Returns TRUE on success or FALSE on failure.
   *
   * @see http://php.net/manual/en/function.imagecopymerge.php#92787
   */
  protected function imageCopyMergeAlpha(\GDImage $dst_im, \GDImage $src_im, int $dst_x, int $dst_y, int $src_x, int $src_y, int $src_w, int $src_h, int $pct): bool {
    if ($pct === 100) {
      // Use imagecopy() if opacity is 100%.
      return imagecopy($dst_im, $src_im, $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h);
    }
    else {
      // If opacity is below 100%, use the approach described in
      // http://php.net/manual/it/function.imagecopymerge.php#92787
      // to preserve watermark alpha.
      // --------------------------------------
      // Create a cut \GDImage.
      $cut = imagecreatetruecolor($src_w, $src_h);
      if (!is_object($cut)) {
        return FALSE;
      }

      // Copy relevant section from destination image to the cut image.
      if (!imagecopy($cut, $dst_im, 0, 0, $dst_x, $dst_y, $src_w, $src_h)) {
        return FALSE;
      }

      // Copy relevant section from merged image to the cut image.
      if (!imagecopy($cut, $src_im, 0, 0, $src_x, $src_y, $src_w, $src_h)) {
        return FALSE;
      }

      // Insert cut image to destination image.
      return imagecopymerge($dst_im, $cut, $dst_x, $dst_y, 0, 0, $src_w, $src_h, $pct);
    }
  }

  /**
   * Wrapper of imagettftext().
   *
   * If imagettftext() is missing, throw an exception instead of failing
   * fatally.
   *
   * @param \GDImage $image
   *   An image.
   * @param float $size
   *   The font size.
   * @param float $angle
   *   The angle in degrees.
   * @param int $x
   *   The coordinates given by x and y will define the basepoint of the first
   *   character (roughly the lower-left corner of the character).
   * @param int $y
   *   The y-ordinate. This sets the position of the fonts baseline, not the
   *   very bottom of the character.
   * @param int $color
   *   The color index.
   * @param string $fontfile
   *   The path to the TrueType font to use.
   * @param string $text
   *   The text string in UTF-8 encoding.
   *
   * @return int[]
   *   An array with 8 elements representing four points making the bounding
   *   box of the text.
   *
   * @see http://php.net/manual/en/function.imagettftext.php
   */
  protected function imagettftextWrapper(\GDImage $image, float $size, float $angle, int $x, int $y, int $color, string $fontfile, string $text): array {
    if (function_exists('imagettftext')) {
      return imagettftext($image, $size, $angle, $x, $y, $color, $fontfile, $text);
    }
    else {
      throw new \RuntimeException("The imagettftext() PHP function is not available, and image effects using fonts cannot be executed");
    }
  }

  /**
   * Wrapper of imagettfbbox().
   *
   * If imagettfbbox() is missing, throw an exception instead of failing
   * fatally.
   *
   * @param float $size
   *   The font size.
   * @param float $angle
   *   The angle in degrees.
   * @param string $fontfile
   *   The path to the TrueType font to use.
   * @param string $text
   *   The string to be measured.
   *
   * @return int[]|false
   *   Array with 8 elements representing four points making the bounding box
   *   of the text on success and FALSE on error.
   *
   * @see http://php.net/manual/en/function.imagettfbbox.php
   */
  protected function imagettfbboxWrapper(float $size, float $angle, string $fontfile, string $text): array|FALSE {
    if (function_exists('imagettfbbox')) {
      return imagettfbbox($size, $angle, $fontfile, $text);
    }
    else {
      throw new \RuntimeException("The imagettfbbox() PHP function is not available, and image effects using fonts cannot be executed");
    }
  }

  /**
   * Change overall image transparency level.
   *
   * This method implements the algorithm described in
   * http://php.net/manual/en/function.imagefilter.php#82162
   *
   * @param \GDImage $img
   *   Image.
   * @param int $pct
   *   Opacity of the source image in percentage.
   *
   * @return bool
   *   Returns TRUE on success or FALSE on failure.
   *
   * @see http://php.net/manual/en/function.imagefilter.php#82162
   */
  protected function filterOpacity(\GDImage $img, int $pct): bool {
    $pct /= 100;

    // Get image width and height.
    $w = imagesx($img);
    $h = imagesy($img);

    // Turn alpha blending off.
    imagealphablending($img, FALSE);

    // Find the most opaque pixel in the image (the one with the smallest alpha
    // value).
    $min_alpha = 127;
    for ($x = 0; $x < $w; $x++) {
      for ($y = 0; $y < $h; $y++) {
        $alpha = (imagecolorat($img, $x, $y) >> 24) & 0xFF;
        if ($alpha < $min_alpha) {
          $min_alpha = $alpha;
        }
      }
    }

    // Loop through image pixels and modify alpha for each.
    for ($x = 0; $x < $w; $x++) {
      for ($y = 0; $y < $h; $y++) {
        // Get current alpha value (represents the TANSPARENCY!).
        $color_xy = imagecolorat($img, $x, $y);
        $alpha = ($color_xy >> 24) & 0xFF;
        // Calculate new alpha.
        if ($min_alpha !== 127) {
          $alpha = 127 + 127 * $pct * ($alpha - 127) / (127 - $min_alpha);
        }
        else {
          $alpha += 127 * $pct;
        }
        // Get the color index with new alpha.
        $alpha_color_xy = imagecolorallocatealpha($img, ($color_xy >> 16) & 0xFF, ($color_xy >> 8) & 0xFF, $color_xy & 0xFF, (int) $alpha);
        // Set pixel with the new color + opacity.
        if (!imagesetpixel($img, $x, $y, $alpha_color_xy)) {
          return FALSE;
        }
      }
    }

    return TRUE;
  }

  /**
   * Gets a copy of the source with the Gaussian Blur algorithm applied.
   *
   * This method implements in PHP the algorithm described in
   * https://github.com/libgd/libgd/blob/master/src/gd_filter.c for the
   * gdImageCopyGaussianBlurred function.
   *
   * 'radius' is a radius, not a diameter so a radius of 2 (for example) will
   * blur across a region 5 pixels across (2 to the center, 1 for the center
   * itself and another 2 to the other edge).
   * 'sigma' represents the "fatness" of the curve (lower == fatter). If
   * 'sigma' is NULL, the fucntions ignores it and instead computes an
   * "optimal" value.
   *
   * More details:
   * A Gaussian Blur is generated by replacing each pixel's color values with
   * the average of the surrounding pixels' colors. This region is a circle
   * whose radius is given by argument 'radius'. Thus, a larger radius will
   * yield a blurrier image.
   * This average is not a simple mean of the values. Instead, values are
   * weighted using the Gaussian function (roughly a bell curve centered around
   * the destination pixel) giving it much more influence on the result than
   * its neighbours.  Thus, a fatter curve  will give the center pixel more
   * weight and make the image less blurry; lower 'sigma' values will yield
   * flatter curves.
   * Currently, the default sigma is computed as (2/3)*radius.
   *
   * @param \GDImage $src
   *   The source image.
   * @param int $radius
   *   The blur radius (*not* diameter: range is 2*radius + 1).
   * @param float|null $sigma
   *   (optional) The sigma value or NULL to use the computed default.
   *
   * @return \GDImage|null
   *   The computed new image, or NULL if an error occurred.
   */
  protected function imageCopyGaussianBlurred(\GDImage $src, int $radius, ?float $sigma = NULL): ?\GDImage {
    // Radius must be a positive integer.
    if ($radius < 1) {
      return NULL;
    }

    // Compute the coefficients.
    $coeffs = GdGaussianBlur::gaussianCoeffs($radius, $sigma);
    if (!$coeffs) {
      return NULL;
    }

    // Get image width and height.
    $w = imagesx($src);
    $h = imagesy($src);

    // Apply the filter horizontally.
    $tmp = imagecreatetruecolor($w, $h);
    imagealphablending($tmp, FALSE);
    if (!$tmp) {
      return NULL;
    }
    GdGaussianBlur::applyCoeffs($src, $tmp, $coeffs, $radius, 'HORIZONTAL');

    // Apply the filter vertically.
    $result = imagecreatetruecolor($w, $h);
    imagealphablending($result, FALSE);
    if ($result) {
      GdGaussianBlur::applyCoeffs($tmp, $result, $coeffs, $radius, 'VERTICAL');
    }

    return $result;
  }

  /**
   * Computes the entropy of the area of an image.
   *
   * @param \GDImage $src
   *   The source image.
   * @param int $x
   *   Starting X coordinate.
   * @param int $y
   *   Starting Y coordinate.
   * @param int $width
   *   The width of the area.
   * @param int $height
   *   The height of the area.
   *
   * @return float
   *   The entropy of the selected area image.
   */
  protected function getAreaEntropy(\GDImage $src, int $x, int $y, int $width, int $height): float {
    $window = imagecreatetruecolor($width, $height);
    imagecopy($window, $src, 0, 0, $x, $y, $width, $height);
    return GdImageAnalysis::entropy($window);
  }

  /**
   * Computes the entropy crop of an image, using slices.
   *
   * @param \GDImage $src
   *   The source image.
   * @param int $width
   *   The width of the crop.
   * @param int $height
   *   The height of the crop.
   *
   * @return \Drupal\image_effects\Component\PositionedRectangle
   *   The PositionedRectangle object marking the crop area.
   */
  protected function getEntropyCropBySlicing(\GDImage $src, int $width, int $height): PositionedRectangle {
    $dx = imagesx($src) - min(imagesx($src), $width);
    $dy = imagesy($src) - min(imagesy($src), $height);
    $left = $top = 0;
    $left_entropy = $right_entropy = $top_entropy = $bottom_entropy = 0;
    $right = imagesx($src);
    $bottom = imagesy($src);

    // Slice from left and right edges until the correct width is reached.
    while ($dx) {
      $slice = min($dx, 10);

      // Calculate the entropy of the new slice.
      if (!$left_entropy) {
        $left_entropy = $this->getAreaEntropy($src, $left, $top, $slice, imagesy($src));
      }
      if (!$right_entropy) {
        $right_entropy = $this->getAreaEntropy($src, $right - $slice, $top, $slice, imagesy($src));
      }

      // Remove the lowest entropy slice.
      if ($left_entropy >= $right_entropy) {
        $right -= $slice;
        $right_entropy = 0;
      }
      else {
        $left += $slice;
        $left_entropy = 0;
      }
      $dx -= $slice;
    }

    // Slice from the top and bottom edges until the correct width is reached.
    while ($dy) {
      $slice = min($dy, 10);

      // Calculate the entropy of the new slice.
      if (!$top_entropy) {
        $top_entropy = $this->getAreaEntropy($src, $left, $top, $width, $slice);
      }
      if (!$bottom_entropy) {
        $bottom_entropy = $this->getAreaEntropy($src, $left, $bottom - $slice, $width, $slice);
      }

      // Remove the lowest entropy slice.
      if ($top_entropy >= $bottom_entropy) {
        $bottom -= $slice;
        $bottom_entropy = 0;
      }
      else {
        $top += $slice;
        $top_entropy = 0;
      }
      $dy -= $slice;
    }

    $rect = new PositionedRectangle($right - $left, $bottom - $top);
    $rect->translate([$left, $top]);
    return $rect;
  }

  /**
   * Computes the entropy crop of an image, using recursive gridding.
   *
   * @param \GDImage $src
   *   The source image.
   * @param int $crop_width
   *   The width of the crop.
   * @param int $crop_height
   *   The height of the crop.
   * @param bool $simulate
   *   If TRUE, the crop will be simulated, and image markers will be overlaid
   *   on the source image.
   * @param int $grid_width
   *   The maximum width of the sub-grid window.
   * @param int $grid_height
   *   The maximum height of the sub-grid window.
   * @param int $grid_rows
   *   The number of rows of the sub-grid.
   * @param int $grid_columns
   *   The number of columns of the sub-grid.
   * @param int $grid_sub_rows
   *   The number of rows of the sub-grid for the sum calculation.
   * @param int $grid_sub_columns
   *   The number of columns of the sub-grid for the sum calculation.
   *
   * @return \Drupal\image_effects\Component\PositionedRectangle
   *   The PositionedRectangle object marking the crop area.
   */
  protected function getEntropyCropByGridding(\GDImage $src, int $crop_width, int $crop_height, bool $simulate, int $grid_width, int $grid_height, int $grid_rows, int $grid_columns, int $grid_sub_rows, int $grid_sub_columns): PositionedRectangle {
    // Source image data.
    $source_rect = new PositionedRectangle(imagesx($src), imagesy($src));
    $source_image_aspect = imagesy($src) / imagesx($src);

    // If simulating, create an image serving as the layer for the visual
    // markers.
    if ($simulate) {
      $marker_layer_image = imagecreatetruecolor(imagesx($src), imagesy($src));
      imagefill($marker_layer_image, 0, 0, imagecolorallocatealpha($marker_layer_image, 0, 0, 0, 127));
    }

    // Determine dimensions of the grid window. The window dimensions are
    // limited to reduce entropy compared to the source. It needs to respect the
    // aspect ratio of the source image.
    if ($source_image_aspect < $grid_height / $grid_width) {
      $grid_height = (int) round($grid_width * $source_image_aspect);
    }
    else {
      $grid_width = (int) round($grid_height / $source_image_aspect);
    }

    // Analyse the grid window to find the highest entropy sub-grid. Loop until
    // the window size is smaller than the crop area, nesting sub-windows.
    $window_nesting = 0;
    $window_x_offset = 0;
    $window_y_offset = 0;
    $window_width = imagesx($src);
    $window_height = imagesy($src);
    while ($window_width > $crop_width || $window_height > $crop_height) {
      // Determine the working size of the grid window. Resample the source
      // image grid area into the grid window, if it is bigger than the bounds
      // given.
      if ($window_width >= $grid_width && $window_height >= $grid_height) {
        $work_window_width = $grid_width;
        $work_window_height = $grid_height;
      }
      else {
        $work_window_width = $window_width;
        $work_window_height = $window_height;
      }

      // If the grid window is smaller than the matrix, it does not make sense
      // to dig deeper.
      if ($work_window_width < $grid_columns || $work_window_height < $grid_rows) {
        break;
      }

      // Add a grid to the source image rectangle.
      $source_rect->addGrid('grid_' . $window_nesting, $window_x_offset, $window_y_offset, $window_width, $window_height, $grid_rows, $grid_columns);

      // Create the grid window.
      $grid_rect = new PositionedRectangle($work_window_width, $work_window_height);
      $grid_rect->addGrid('grid_0', 0, 0, $work_window_width, $work_window_height, $grid_rows, $grid_columns);
      $grid_image = imagecreatetruecolor($work_window_width, $work_window_height);
      imagefill($grid_image, 0, 0, imagecolorallocatealpha($grid_image, 0, 0, 0, 127));
      imagecopyresampled($grid_image, $src, 0, 0, $window_x_offset, $window_y_offset, $work_window_width, $work_window_height, $window_width, $window_height);

      // Build the entropy matrix of the window.
      $entropy_matrix = [];
      for ($row = 0; $row < $grid_rows; $row++) {
        for ($column = 0; $column < $grid_columns; $column++) {
          $cell_top_left = $grid_rect->getPoint('grid_0_' . $row . '_' . $column);
          $cell_dimensions = $grid_rect->getSubGridDimensions('grid_0', $row, $column, 1, 1);
          $entropy_matrix[$row][$column] = $this->getAreaEntropy($grid_image, $cell_top_left[0], $cell_top_left[1], $cell_dimensions[0], $cell_dimensions[1]);
        }
      }

      // Find the sub-matrix that has the highest entropy sum.
      $entropy_sum_matrix = MatrixUtility::cumulativeSum($entropy_matrix);
      $max_sum_position = MatrixUtility::findMaxSumSubmatrix($entropy_sum_matrix, $grid_sub_rows, $grid_sub_columns);

      // Position the highest entropy sub-matrix on the source image.
      [$window_x_offset, $window_y_offset] = $source_rect->getPoint('grid_' . $window_nesting . '_' . $max_sum_position[0] . '_' . $max_sum_position[1]);
      [$window_width, $window_height] = $source_rect->getSubGridDimensions('grid_' . $window_nesting, $max_sum_position[0], $max_sum_position[1], $grid_sub_rows, $grid_sub_columns);

      if ($simulate) {
        switch ($window_nesting % 3) {
          case 0:
            $color = imagecolorallocatealpha($marker_layer_image, 255, 0, 0, 0);
            break;

          case 1:
            $color = imagecolorallocatealpha($marker_layer_image, 0, 255, 255, 0);
            break;

          case 2:
            $color = imagecolorallocatealpha($marker_layer_image, 255, 255, 0, 0);
            break;

        }

        // Add grid points on marker layer.
        for ($row = 0; $row <= $grid_rows; $row++) {
          for ($column = 0; $column <= $grid_columns; $column++) {
            $coord = $source_rect->getPoint('grid_' . $window_nesting . '_' . $row . '_' . $column);
            imagefilledellipse($marker_layer_image, $coord[0], $coord[1], 6, 6, $color);
          }
        }

        // Polygon on marker layer.
        $rect = new PositionedRectangle($window_width, $window_height);
        $rect->translate([$window_x_offset, $window_y_offset]);
        $rect->translate([-2, -2]);
        for ($i = -2; $i <= 2; $i++) {
          imagepolygon($marker_layer_image, $this->getRectangleCorners($rect), $color);
          $rect->translate([1, 1]);
        }
      }

      $window_nesting++;
    }

    // Overlay marker layer onto source at 70% transparency.
    if ($simulate) {
      $this->imageCopyMergeAlpha($src, $marker_layer_image, 0, 0, 0, 0, imagesx($src), imagesy($src), 70);
    }

    // Determine the Rectangle containing the crop.
    $window_x_center = $window_x_offset + (int) ($window_width / 2);
    $window_y_center = $window_y_offset + (int) ($window_height / 2);
    $crop_x_offset = $window_x_center - (int) ($crop_width / 2);
    $crop_y_offset = $window_y_center - (int) ($crop_height / 2);
    $rect = new PositionedRectangle($crop_width, $crop_height);
    $rect->translate([$crop_x_offset, $crop_y_offset]);
    if ($crop_x_offset < 0) {
      $rect->translate([-$crop_x_offset, 0]);
    }
    if ($crop_y_offset < 0) {
      $rect->translate([0, -$crop_y_offset]);
    }
    if ($crop_x_offset + $crop_width > imagesx($src)) {
      $rect->translate([-($crop_x_offset + $crop_width - imagesx($src)), 0]);
    }
    if ($crop_y_offset + $crop_height > imagesy($src)) {
      $rect->translate([0, -($crop_y_offset + $crop_height - imagesy($src))]);
    }

    return $rect;
  }

}
