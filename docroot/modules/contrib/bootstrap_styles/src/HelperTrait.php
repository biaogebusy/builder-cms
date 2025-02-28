<?php

namespace Drupal\bootstrap_styles;

use Drupal\Core\Render\Markup;

/**
 * A Trait for helper methods.
 */
trait HelperTrait {

  /**
   * Generates the svg markup from path.
   *
   * @param string $path
   *   The path to the svg icon.
   *
   * @return array
   *   Array of the SVG icon markup.
   */
  public function getSvgIconMarkup(string $path) {
    $svg = file_get_contents(DRUPAL_ROOT . '/' . $path);
    $svg = preg_replace(['/<\?xml.*\?>/i', '/<!DOCTYPE((.|\n|\r)*?)">/i'], '', $svg);
    $svg = trim($svg);
    return Markup::create($svg);
  }

}
