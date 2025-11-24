<?php

declare(strict_types=1);

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\gd;

use Drupal\Core\ImageToolkit\Attribute\ImageToolkitOperation;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\image_effects\Component\ColorUtility;
use Drupal\image_effects\Component\ImageUtility;
use Drupal\image_effects\Component\PositionedRectangle;
use Drupal\image_effects\Component\TextUtility;
use Drupal\image_effects\Plugin\ImageToolkit\Operation\FontOperationTrait;
use Drupal\image_effects\Plugin\ImageToolkit\Operation\TextToWrapperTrait;
use Drupal\system\Plugin\ImageToolkit\Operation\gd\GDImageToolkitOperationBase;

/**
 * Defines GD Text Overlay text-to-wrapper operation.
 */
#[ImageToolkitOperation(
  id: 'image_effects_gd_text_to_wrapper',
  toolkit: 'gd',
  operation: 'text_to_wrapper',
  label: new TranslatableMarkup('Overlays text over a wrapper image'),
  description: new TranslatableMarkup('Overlays text over a GD image.'),
)]
class TextToWrapper extends GDImageToolkitOperationBase {

  use FontOperationTrait;
  use GDOperationTrait;
  use TextToWrapperTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    // Determine if outline/shadow is required.
    $outline = $shadow = FALSE;
    if ($arguments['font_stroke_mode'] == 'outline' && ($arguments['font_outline_top'] || $arguments['font_outline_right'] || $arguments['font_outline_bottom'] || $arguments['font_outline_left']) && $arguments['font_stroke_color']) {
      $outline = TRUE;
    }
    elseif ($arguments['font_stroke_mode'] == 'shadow' && ($arguments['font_shadow_x_offset'] || $arguments['font_shadow_y_offset'] || $arguments['font_shadow_width'] || $arguments['font_shadow_height']) && $arguments['font_stroke_color']) {
      $shadow = TRUE;
    }

    // Add stroke to padding to ensure inner box includes entire font space.
    if ($outline) {
      $arguments['layout_padding_top'] += $arguments['font_outline_top'];
      $arguments['layout_padding_right'] += $arguments['font_outline_right'];
      $arguments['layout_padding_bottom'] += $arguments['font_outline_bottom'];
      $arguments['layout_padding_left'] += $arguments['font_outline_left'];
    }
    elseif ($shadow) {
      $arguments['layout_padding_top'] += ($arguments['font_shadow_y_offset'] < 0 ? -$arguments['font_shadow_y_offset'] : 0);
      $arguments['layout_padding_right'] += ($arguments['font_shadow_x_offset'] > 0 ? $arguments['font_shadow_x_offset'] : 0);
      $arguments['layout_padding_bottom'] += ($arguments['font_shadow_y_offset'] > 0 ? $arguments['font_shadow_y_offset'] : 0);
      $arguments['layout_padding_left'] += ($arguments['font_shadow_x_offset'] < 0 ? -$arguments['font_shadow_x_offset'] : 0);
      $shadow_width = ($arguments['font_shadow_x_offset'] != 0) ? $arguments['font_shadow_width'] + 1 : $arguments['font_shadow_width'];
      $shadow_height = ($arguments['font_shadow_y_offset'] != 0) ? $arguments['font_shadow_height'] + 1 : $arguments['font_shadow_height'];
      $net_right = $shadow_width + ($arguments['font_shadow_x_offset'] >= 0 ? 0 : (int) $arguments['font_shadow_x_offset']);
      $arguments['layout_padding_right'] += ($net_right > 0 ? $net_right : 0);
      $net_bottom = $shadow_height + ($arguments['font_shadow_y_offset'] >= 0 ? 0 : (int) $arguments['font_shadow_y_offset']);
      $arguments['layout_padding_bottom'] += ($net_bottom > 0 ? $net_bottom : 0);
    }

    // Perform text wrapping, if necessary.
    if ($arguments['text_maximum_width'] > 0) {
      $arguments['text_string'] = $this->wrapText(
        $arguments['text_string'],
        $arguments['font_size'],
        $arguments['font_uri'],
        $arguments['text_maximum_width'] - $arguments['layout_padding_left'] - $arguments['layout_padding_right'] - 1,
      );
    }

    // Load text lines to array elements.
    $text_lines = explode("\n", $arguments['text_string']);
    $num_lines = count($text_lines);

    // Calculate bounding boxes.
    // ---------------------------------------
    // Inner box - the exact bounding box of the text.
    // Outer box - the box where the inner box is - can be different because
    // of padding.
    // Wrapper - the canvas where the outer box is laid.
    // ---------------------------------------
    // Get inner box details, for horizontal text, unpadded.
    // If fixed width, set to configuration, otherwise get width from the font
    // bounding box.
    if ($arguments['text_fixed_width'] && !empty($arguments['text_maximum_width'])) {
      $inner_box_width = $arguments['text_maximum_width'] - $arguments['layout_padding_left'] - $arguments['layout_padding_right'];
    }
    else {
      $inner_box_width = $this->getTextWidth($arguments['text_string'], $arguments['font_size'], $arguments['font_uri']);
    }

    // Determine line height.
    $height_info = $this->getTextHeightInfo($arguments['font_size'], $arguments['font_uri']);
    $line_height = $height_info['height'];

    // Manage leading (line spacing), adding total line spacing to height.
    $inner_box_height = ($height_info['height'] * $num_lines) + ($arguments['text_line_spacing'] * ($num_lines - 1));

    // Get outer box.
    $outer_rect = new PositionedRectangle($inner_box_width + $arguments['layout_padding_right'] + $arguments['layout_padding_left'], $inner_box_height + $arguments['layout_padding_top'] + $arguments['layout_padding_bottom']);
    $outer_rect->rotate($arguments['font_angle']);
    $outer_rect->translate($outer_rect->getRotationOffset());

    // Get inner box.
    $inner_rect = new PositionedRectangle($inner_box_width, $inner_box_height);
    $inner_rect->translate([$arguments['layout_padding_left'], $arguments['layout_padding_top']]);
    $inner_rect->rotate($arguments['font_angle']);
    $inner_rect->translate($outer_rect->getRotationOffset());

    // Set image dimensions to allow fitting the text. Explicitly setting
    // extension to 'png' to ensure wrapper is full transparent alpha channel
    // enabled.
    $data = [
      'width' => $outer_rect->getBoundingWidth(),
      'height' => $outer_rect->getBoundingHeight(),
      'extension' => 'png',
      'is_temp' => FALSE,
    ];
    if (!$this->getToolkit()->apply('create_new', $data)) {
      return FALSE;
    }

    // Draw and fill the outer text box, if required.
    if ($arguments['layout_background_color']) {
      $data_rectangle = [
        'rectangle' => $outer_rect,
        'fill_color' => $arguments['layout_background_color'],
      ];
      $this->getToolkit()->apply('draw_rectangle', $data_rectangle);
    }

    // In debug mode, visually display the text boxes.
    if ($arguments['debug_visuals']) {
      // Inner box.
      $data = [
        'rectangle' => $inner_rect,
        'border_color' => $arguments['layout_background_color'] ?: '#FFFFFF',
        'border_color_luma' => TRUE,
      ];
      $this->getToolkit()->apply('draw_rectangle', $data);
      // Outer box.
      $data = [
        'rectangle' => $outer_rect,
        'border_color' => $arguments['layout_background_color'] ?: '#FFFFFF',
        'border_color_luma' => TRUE,
      ];
      $this->getToolkit()->apply('draw_rectangle', $data);
      // Wrapper.
      $data = [
        'rectangle' => new PositionedRectangle($this->getToolkit()->getWidth(), $this->getToolkit()->getHeight()),
        'border_color' => '#000000',
      ];
      $this->getToolkit()->apply('draw_rectangle', $data);
    }

    // Process each of the text lines.
    $current_y = 0;
    foreach ($text_lines as $text_line) {
      // This text line's width.
      $text_line_width = $this->getTextWidth($text_line, $arguments['font_size'], $arguments['font_uri']);
      $text_line_rect = new PositionedRectangle($text_line_width, $line_height);
      $text_line_rect->setPoint('basepoint', $height_info['basepoint']);

      // Manage text alignment within the line.
      $x_delta = $inner_rect->getWidth() - $text_line_rect->getWidth();
      $current_y += $line_height;
      switch ($arguments['text_align']) {
        case 'center':
          $x_offset = round($x_delta / 2);
          break;

        case 'right':
          $x_offset = $x_delta;
          break;

        case 'left':
        default:
          $x_offset = 0;
          break;

      }

      // Get details for the rotated/translated text line box.
      $text_line_rect->translate([
        $arguments['layout_padding_left'] + $x_offset,
        $arguments['layout_padding_top'] + $current_y - $line_height,
      ]);
      $text_line_rect->rotate($arguments['font_angle']);
      $text_line_rect->translate($outer_rect->getRotationOffset());

      // Overlay the text onto the image.
      $data = [
        'text'                     => $text_line,
        'basepoint'                => $text_line_rect->getPoint('basepoint'),
        'font_uri'                 => $arguments['font_uri'],
        'font_size'                => $arguments['font_size'],
        'font_angle'               => $arguments['font_angle'],
        'font_color'               => $arguments['font_color'],
        'font_stroke_mode'         => $arguments['font_stroke_mode'],
        'font_stroke_color'        => $arguments['font_stroke_color'],
        'font_outline_top'         => $arguments['font_outline_top'],
        'font_outline_right'       => $arguments['font_outline_right'],
        'font_outline_bottom'      => $arguments['font_outline_bottom'],
        'font_outline_left'        => $arguments['font_outline_left'],
        'font_shadow_x_offset'     => $arguments['font_shadow_x_offset'],
        'font_shadow_y_offset'     => $arguments['font_shadow_y_offset'],
        'font_shadow_width'        => $arguments['font_shadow_width'],
        'font_shadow_height'       => $arguments['font_shadow_height'],
      ];
      $this->getToolkit()->apply('text_overlay', $data);

      // In debug mode, display a polygon enclosing the text line.
      if ($arguments['debug_visuals']) {
        $this->drawDebugBox($text_line_rect, $arguments['layout_background_color'], TRUE);
      }

      // Add interline spacing (leading) before next iteration.
      $current_y += $arguments['text_line_spacing'];
    }

    // Finalise image.
    imagealphablending($this->getToolkit()->getImage(), TRUE);
    imagesavealpha($this->getToolkit()->getImage(), TRUE);

    // Resize the wrapper if needed.
    if ($arguments['layout_overflow_action'] == 'scaletext') {
      $this->resizeWrapper($arguments);
    }

    return TRUE;
  }

  /**
   * Resizes the text wrapping image.
   *
   * @param array $arguments
   *   An associative array of arguments.
   */
  protected function resizeWrapper(array $arguments): void {
    // Wrapper image dimensions.
    $original_wrapper_width = $this->getToolkit()->getWidth();
    $original_wrapper_height = $this->getToolkit()->getHeight();

    // Determine wrapper offset, based on placement option and direct
    // offset indicated in settings.
    $wrapper_xpos = ImageUtility::getKeywordOffset($arguments['layout_x_pos'], $arguments['canvas_width'], $original_wrapper_width) + $arguments['layout_x_offset'];
    $wrapper_ypos = ImageUtility::getKeywordOffset($arguments['layout_y_pos'], $arguments['canvas_height'], $original_wrapper_height) + $arguments['layout_y_offset'];

    // Position of wrapper's bottom right point.
    $xc_pos = $wrapper_xpos + $original_wrapper_width;
    $yc_pos = $wrapper_ypos + $original_wrapper_height;

    // Redetermine offset wrapper position and size based on
    // background image size.
    $wrapper_xpos = max(0, $wrapper_xpos);
    $wrapper_ypos = max(0, $wrapper_ypos);
    $xc_pos = min($arguments['canvas_width'], $xc_pos);
    $yc_pos = min($arguments['canvas_height'], $yc_pos);
    $wrapper_width = $xc_pos - $wrapper_xpos;
    $wrapper_height = $yc_pos - $wrapper_ypos;

    // If negative width/height, then the wrapper is totally
    // overflowing the background, and we cannot resize it.
    if ($wrapper_width < 0 || $wrapper_height < 0) {
      return;
    }

    // Determine if scaling needed. Take the side that is shrinking
    // most.
    $width_resize_index = $wrapper_width / $original_wrapper_width;
    $height_resize_index = $wrapper_height / $original_wrapper_height;
    if ($width_resize_index < 1 || $height_resize_index < 1) {
      if ($width_resize_index < $height_resize_index) {
        $wrapper_height = NULL;
      }
      else {
        $wrapper_width = NULL;
      }
      $this->getToolkit()->apply('scale', [
        'width' => $wrapper_width,
        'height' => $wrapper_height,
      ]);
    }
  }

  /**
   * Display a polygon enclosing the text line, and conspicuous points.
   *
   * Credit to Ruquay K Calloway.
   *
   * @param \Drupal\image_effects\Component\PositionedRectangle $rect
   *   A PositionedRectangle object, including basepoint.
   * @param string $rgba
   *   RGBA color of the rectangle.
   * @param bool $luma
   *   if TRUE, convert RGBA to best match using luma.
   *
   * @see http://ruquay.com/sandbox/imagettf
   */
  protected function drawDebugBox(PositionedRectangle $rect, string $rgba, bool $luma = FALSE): void {

    // Check color.
    if (!$rgba) {
      $rgba = '#000000FF';
    }
    elseif ($luma) {
      $rgba = ColorUtility::matchLuma($rgba);
    }

    // Retrieve points.
    $points = $this->getRectangleCorners($rect);

    // Draw box.
    $data = [
      'rectangle' => $rect,
      'border_color' => $rgba,
    ];
    $this->getToolkit()->apply('draw_rectangle', $data);

    // Draw diagonal.
    $data = [
      'x1' => $points[0],
      'y1' => $points[1],
      'x2' => $points[4],
      'y2' => $points[5],
      'color' => $rgba,
    ];
    $this->getToolkit()->apply('draw_line', $data);

    // Conspicuous points.
    $orange = '#FF6400FF';
    $yellow = '#FFFF00FF';
    $green = '#00FF00FF';
    $dotsize = 6;

    // Box corners.
    for ($i = 0; $i < 8; $i += 2) {
      $col = $i < 4 ? $orange : $yellow;
      $data = [
        'cx' => $points[$i],
        'cy' => $points[$i + 1],
        'width' => $dotsize,
        'height' => $dotsize,
        'color' => $col,
      ];
      $this->getToolkit()->apply('draw_ellipse', $data);
    }

    // Font baseline.
    $basepoint = $rect->getPoint('basepoint');
    $data = [
      'cx' => $basepoint[0],
      'cy' => $basepoint[1],
      'width' => $dotsize,
      'height' => $dotsize,
      'color' => $green,
    ];
    $this->getToolkit()->apply('draw_ellipse', $data);
  }

  /**
   * Wrap text for rendering at a given width.
   *
   * @param string $text
   *   Text string in UTF-8 encoding.
   * @param int $font_size
   *   Font size.
   * @param string $font_uri
   *   URI of the TrueType font to use.
   * @param int $maximum_width
   *   Maximum width allowed for each line.
   *
   * @return string
   *   Text string, with newline characters to separate each line.
   */
  protected function wrapText(string $text, int $font_size, string $font_uri, int $maximum_width): string {
    // State variables for the search interval.
    $end = 0;
    $begin = 0;
    $fit = $begin;

    // Note: we count in bytes for speed reasons, but maintain character
    // boundaries.
    while (TRUE) {
      // Find the next wrap point (always after trailing whitespace).
      $match = [];
      if (TextUtility::unicodePregMatch('/[' . TextUtility::PREG_CLASS_PUNCTUATION . '][' . TextUtility::PREG_CLASS_SEPARATOR . ']*|[' . TextUtility::PREG_CLASS_SEPARATOR . ']+/u', $text, $match, PREG_OFFSET_CAPTURE, $end)) {
        $end = $match[0][1] + mb_strlen($match[0][0]);
      }
      else {
        $end = mb_strlen($text);
      }

      // Fetch text, removing trailing white-space, and measure it.
      $line = preg_replace('/[' . TextUtility::PREG_CLASS_SEPARATOR . ']+$/u', '', mb_substr($text, $begin, $end - $begin));
      $width = $this->getTextWidth($line, $font_size, $font_uri);

      // See if line extends past the available space.
      if ($width > $maximum_width) {
        // If this is the first word, we need to truncate it.
        if ($fit == $begin) {
          // Cut off letters until it fits.
          while (mb_strlen($line) > 0 && $width > $maximum_width) {
            $line = mb_substr($line, 0, -1);
            $width = $this->getTextWidth($line, $font_size, $font_uri);
          }
          // If no fit was found, the image is too narrow.
          $fit = mb_strlen($line) ? $begin + mb_strlen($line) : $end;
        }
        // We have a valid fit for the next line. Insert a line-break and reset
        // the search interval.
        if (mb_substr($text, $fit - 1, 1) == ' ') {
          $first_part = mb_substr($text, 0, $fit - 1);
        }
        else {
          $first_part = mb_substr($text, 0, $fit);
        }
        $last_part = mb_substr($text, $fit);
        $text = $first_part . "\n" . $last_part;
        $begin = ++$fit;
        $end = $begin;
      }
      else {
        // We can fit this text. Wait for now.
        $fit = $end;
      }

      if ($end == mb_strlen($text)) {
        // All text fits. No more changes are needed.
        break;
      }
    }
    return $text;
  }

  /**
   * Return the width of a text using TrueType fonts.
   *
   * @param string $text
   *   A text string.
   * @param int $font_size
   *   The font size.
   * @param string $font_uri
   *   The font URI.
   *
   * @return int|null
   *   The width of the text in pixels.
   */
  protected function getTextWidth(string $text, int $font_size, string $font_uri): ?int {
    // Get fully qualified font file information.
    if (!$font_file = $this->getFontPath($font_uri)) {
      return NULL;
    }
    // Get the bounding box for $text to get width.
    $points = $this->imagettfbboxWrapper($font_size, 0, $font_file, $text);
    // Return bounding box width.
    return (abs($points[4] - $points[6]) + 1);
  }

  /**
   * Return the height and basepoint of a text using TrueType fonts.
   *
   * Need to calculate the height independently from primitive as
   * lack of descending/ascending characters will limit the height.
   * So to have uniformity we take a dummy string with ascending and
   * descending characters to set to max height possible.
   *
   * @param int $font_size
   *   The font size.
   * @param string $font_uri
   *   The font URI.
   *
   * @return array|null
   *   An associative array with the following keys:
   *   - 'height' the text height in pixels.
   *   - 'basepoint' an array of x, y coordinates of the font's basepoint.
   */
  protected function getTextHeightInfo(int $font_size, string $font_uri): ?array {
    // Get fully qualified font file information.
    if (!$font_file = $this->getFontPath($font_uri)) {
      return NULL;
    }
    // Get the bounding box for $text to get height.
    $points = $this->imagettfbboxWrapper((float) $font_size, 0, $font_file, 'bdfhkltgjpqyBDFHKLTGJPQY§@çÅÀÈÉÌÒÇ');
    $height = (abs($points[5] - $points[1]) + 1);
    return [
      'height' => $height,
      'basepoint' => [$points[6], -$points[7]],
    ];
  }

}
