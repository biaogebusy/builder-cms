<?php

namespace Drupal\ai\Traits\File;

/**
 * Trait to add the possibility to store output base64 encoded strings.
 *
 * @package Drupal\ai\Traits\File
 */
trait GenerateBase64Trait {

  /**
   * Generate base64 encoded string.
   *
   * @param string|null $data_url_scheme
   *   Add a data url scheme, like 'data:image/png'.
   *
   * @return string
   *   A base64 encoded string.
   */
  public function getAsBase64EncodedString(?string $data_url_scheme = NULL): string {
    $base64 = base64_encode($this->getBinary());
    if (!is_null($data_url_scheme)) {
      $base64 = $data_url_scheme . $base64;
    }
    elseif ($this->getMimeType()) {
      $base64 = 'data:' . $this->getMimeType() . ';base64,' . $base64;
    }

    return $base64;
  }

  /**
   * Set from a base64 encoded string.
   *
   * @param string $base64_string
   *   The base64 encoded string.
   *
   * @return void
   *   Nothing.
   */
  public function setFromBase64EncodedString(string $base64_string): void {
    // Remove data url scheme if exists.
    if (preg_match('/^data:(.*);base64,/', $base64_string, $matches)) {
      $this->setMimeType($matches[1]);
      $base64_string = str_replace($matches[0], '', $base64_string);
    }
    $this->binary = base64_decode($base64_string);
  }

}
