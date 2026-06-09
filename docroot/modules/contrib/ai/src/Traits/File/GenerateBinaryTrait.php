<?php

namespace Drupal\ai\Traits\File;

/**
 * Trait to add the possibility to store output as binary strings.
 *
 * @package Drupal\ai\Traits\File
 */
trait GenerateBinaryTrait {

  /**
   * Generate binary string.
   *
   * @return string
   *   The binary string.
   */
  public function getAsBinary(): string {
    return $this->getBinary();
  }

}
