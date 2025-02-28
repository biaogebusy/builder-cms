<?php

namespace Drupal\charts;

/**
 * Contains helper method to help with the generation of random color.
 */
trait ColorHelperTrait {

  /**
   * Provide a random color.
   *
   * @return string
   *   A random color.
   */
  public static function randomColor(): string {
    return sprintf('#%06X', mt_rand(0, 0xFFFFFF));
  }

}
