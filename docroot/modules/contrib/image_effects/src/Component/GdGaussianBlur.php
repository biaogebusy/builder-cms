<?php

namespace Drupal\image_effects\Component;

/**
 * Gaussian Blur helper methods for GD.
 *
 * This class implements in PHP the algorithm described in
 * https://github.com/libgd/libgd/blob/master/src/gd_filter.c for the
 * gdImageCopyGaussianBlurred function.
 */
abstract class GdGaussianBlur {

  /**
   * Calculates an array of coefficients to use for the blur.
   *
   * Returns an array of coefficients for 'radius' and 'sigma'. Null sigma will
   * use a default value. Number of resulting coefficients is 2 * radius + 1.
   *
   * @param int $radius
   *   The radius is used to determine the size of the array which will hold
   *   the calculated Gaussian distribution. It should be an integer. The
   *   larger the radius the slower the operation is. However, too small the
   *   radius, unwanted aliasing effects may result. As a guideline, radius
   *   should be at least twice the sigma value, though three times will
   *   produce a more accurate result.
   * @param float $sigma_arg
   *   (optional) The Sigma value determines the actual amount of blurring
   *   that will take place. Defaults to 2 / 3 of the radius.
   *
   * @return float[]
   *   The array of coefficients to use for the blur.
   */
  public static function gaussianCoeffs($radius, $sigma_arg = NULL) {
    $sigma = $sigma_arg === NULL ? $radius * 2 / 3 : $sigma_arg;
    $s = $sigma * $sigma * 2;

    $result = [];
    $sum = 0;
    for ($x = -$radius; $x <= $radius; $x++) {
      $coeff = exp(-($x * $x) / $s);
      $sum += $coeff;
      $result[$x + $radius] = $coeff;
    }

    $count = $radius * 2 + 1;
    for ($n = 0; $n < $count; $n++) {
      $result[$n] /= $sum;
    }

    return $result;
  }

  /**
   * Applies the Gaussian coefficients to the destination image.
   *
   * @param resource $src
   *   The source image resource.
   * @param resource $dst
   *   The destination image resource.
   * @param float[] $coeffs
   *   The array of coefficients to use for the blur.
   * @param int $radius
   *   The radius of the blur.
   * @param string $axis
   *   The direction of the blur.
   */
  public static function applyCoeffs($src, $dst, array $coeffs, $radius, $axis) {
    if ($axis === 'HORIZONTAL') {
      $numlines = imagesy($src);
      $linelen = imagesx($src);
    }
    else {
      $numlines = imagesx($src);
      $linelen = imagesy($src);
    }

    for ($line = 0; $line < $numlines; $line++) {
      static::applyCoeffsLine($src, $dst, $line, $linelen, $coeffs, $radius, $axis);
    }
  }

  /**
   * Applies the Gaussian coefficients to a line of the destination image.
   *
   * @param resource $src
   *   The source image resource.
   * @param resource $dst
   *   The destination image resource.
   * @param int $line
   *   The image's line to be processed.
   * @param int $linelen
   *   The line's length in pixels.
   * @param float[] $coeffs
   *   The array of coefficients to use for the blur.
   * @param int $radius
   *   The radius of the blur.
   * @param string $axis
   *   The direction of the blur.
   */
  protected static function applyCoeffsLine($src, $dst, $line, $linelen, array $coeffs, $radius, $axis) {
    // Preloads line's pixels colors to minimize calls to imagexxx functions.
    $pixels = [];
    for ($ndx = 0; $ndx < $linelen; $ndx++) {
      $src_idx = $axis === 'HORIZONTAL' ? imagecolorat($src, $ndx, $line) : imagecolorat($src, $line, $ndx);
      $pixels[$ndx] = imagecolorsforindex($src, $src_idx);
    }

    // Loops through all pixels on the line.
    for ($ndx = 0; $ndx < $linelen; $ndx++) {
      $r = $g = $b = $a = 0;

      // Loops through all pixels in the radius.
      for ($cndx = -$radius; $cndx <= $radius; $cndx++) {
        $coeff = $coeffs[$cndx + $radius];
        $rndx = static::reflect($linelen, $ndx + $cndx);
        $r += $coeff * $pixels[$rndx]['red'];
        $g += $coeff * $pixels[$rndx]['green'];
        $b += $coeff * $pixels[$rndx]['blue'];
        $a += $coeff * $pixels[$rndx]['alpha'];
      }

      // Set resulting pixel color on the destination resource.
      $dst_color = imagecolorallocatealpha($dst, static::ucharClamp($r, 0xFF), static::ucharClamp($g, 0xFF), static::ucharClamp($b, 0xFF), static::ucharClamp($a, 0x7F));
      if ($axis === 'HORIZONTAL') {
        imagesetpixel($dst, $ndx, $line, $dst_color);
      }
      else {
        imagesetpixel($dst, $line, $ndx, $dst_color);
      }
    }
  }

  /**
   * Ensures that a value is in the 0-max range.
   *
   * If out of bounds, returns the reflected value within the range.
   *
   * @param int $max
   *   The maximum value.
   * @param int $x
   *   The value to be checked.
   *
   * @return int
   *   The input value if in-bounds, otherwise the reflected value within the
   *   range.
   */
  protected static function reflect($max, $x) {
    if ($x < 0) {
      return -$x;
    }
    if ($x >= $max) {
      return $max - ($x - $max) - 1;
    }
    return $x;
  }

  /**
   * Convert a double to an unsigned char.
   *
   * Round to the nearest integer and clamp the result between 0 and max.
   * The absolute value of $clr must be less than the maximum value of an
   * unsigned short.
   * Casting a negative float to an unsigned short is undefined. However,
   * casting a float to a signed truncates toward zero and casting a negative
   * signed value to an unsigned of the same size results in a bit-identical
   * value (assuming twos-complement arithmetic). This is what we want: all
   * legal negative values for $clr will be greater than 255.
   *
   * @param float $clr
   *   The float to be converted.
   * @param int $max
   *   The maximum value.
   *
   * @return int
   *   The converted value.
   */
  protected static function ucharClamp($clr, $max) {
    // Convert and clamp.
    $result = (int) ($clr + 0.5);
    if ($result > $max) {
      $result = ($clr < 0) ? 0 : $max;
    }
    return $result;
  }

}
