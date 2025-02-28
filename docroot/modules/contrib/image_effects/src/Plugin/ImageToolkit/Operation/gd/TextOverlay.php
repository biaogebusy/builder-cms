<?php

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\gd;

use Drupal\system\Plugin\ImageToolkit\Operation\gd\GDImageToolkitOperationBase;
use Drupal\image_effects\Plugin\ImageToolkit\Operation\FontOperationTrait;
use Drupal\image_effects\Plugin\ImageToolkit\Operation\TextOverlayTrait;

/**
 * Defines GD2 text overlay operation.
 *
 * @ImageToolkitOperation(
 *   id = "image_effects_gd_text_overlay",
 *   toolkit = "gd",
 *   operation = "text_overlay",
 *   label = @Translation("Text overlay"),
 *   description = @Translation("Overlays a given text into the image.")
 * )
 */
class TextOverlay extends GDImageToolkitOperationBase {

  use FontOperationTrait;
  use TextOverlayTrait;
  use GDOperationTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    $font_file = $this->getFontPath($arguments['font_uri']);

    // Overlays the text outline/shadow, if required.
    // Credit to John Ciacia.
    // @see http://www.johnciacia.com/2010/01/04/using-php-and-gd-to-add-border-to-text/
    $outline = $shadow = FALSE;
    if ($arguments['font_stroke_mode'] == 'outline' && ($arguments['font_outline_top'] || $arguments['font_outline_right'] || $arguments['font_outline_bottom'] || $arguments['font_outline_left']) && $arguments['font_stroke_color']) {
      $outline = TRUE;
    }
    elseif ($arguments['font_stroke_mode'] == 'shadow' && ($arguments['font_shadow_x_offset'] || $arguments['font_shadow_y_offset'] || $arguments['font_shadow_width'] || $arguments['font_shadow_height']) && $arguments['font_stroke_color']) {
      $shadow = TRUE;
    }
    if ($outline || $shadow) {
      $stroke_color = $this->allocateColorFromRgba($arguments['font_stroke_color']);
      if ($outline) {
        $stroke_x_pos = $arguments['basepoint'][0];
        $stroke_y_pos = $arguments['basepoint'][1];
        $stroke_top = $arguments['font_outline_top'];
        $stroke_right = $arguments['font_outline_right'];
        $stroke_bottom = $arguments['font_outline_bottom'];
        $stroke_left = $arguments['font_outline_left'];
      }
      elseif ($shadow) {
        $stroke_x_pos = $arguments['basepoint'][0] + $arguments['font_shadow_x_offset'];
        $stroke_y_pos = $arguments['basepoint'][1] + $arguments['font_shadow_y_offset'];
        $stroke_top = 0;
        $stroke_right = $arguments['font_shadow_width'];
        $stroke_bottom = $arguments['font_shadow_height'];
        $stroke_left = 0;
      }
      for ($c1 = ($stroke_x_pos - abs($stroke_left)); $c1 <= ($stroke_x_pos + abs($stroke_right)); $c1++) {
        for ($c2 = ($stroke_y_pos - abs($stroke_top)); $c2 <= ($stroke_y_pos + abs($stroke_bottom)); $c2++) {
          $bg = $this->imagettftextWrapper(
            $this->getToolkit()->getResource(),
            $arguments['font_size'],
            -$arguments['font_angle'],
            $c1,
            $c2,
            $stroke_color,
            $font_file,
            $arguments['text']
          );
          if ($bg == FALSE) {
            return FALSE;
          }
        }
      }
    }

    // Overlays the text.
    $this->imagettftextWrapper(
      $this->getToolkit()->getResource(),
      $arguments['font_size'],
      -$arguments['font_angle'],
      $arguments['basepoint'][0],
      $arguments['basepoint'][1],
      $this->allocateColorFromRgba($arguments['font_color']),
      $font_file,
      $arguments['text']
    );

    return TRUE;
  }

}
