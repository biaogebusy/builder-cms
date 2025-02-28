<?php

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation;

/**
 * Base trait for image toolkit operations that require font handling.
 */
trait FontOperationTrait {

  /**
   * Return the path of the font file.
   *
   * The imagettf* GD functions, and ImageMagick toolkit, do not allow use of
   * URIs to specify files. Always resolve the font file to a local path.
   *
   * @param string $font_uri
   *   The font URI.
   *
   * @return string
   *   The local path of the font file.
   */
  protected function getFontPath($font_uri) {
    if (!$font_uri) {
      throw new \InvalidArgumentException('Font file not specified');
    }

    // Determine if the $font_uri is a real URI or a local path.
    $uri_wrapper = \Drupal::service('stream_wrapper_manager')->getViaUri($font_uri);

    // If local path, return it.
    if ($uri_wrapper === FALSE) {
      return $font_uri;
    }

    // Determine if a local path can be resolved for the URI. If so, return it.
    $local_path = $uri_wrapper->realpath();
    if ($local_path !== FALSE) {
      return $local_path;
    }

    // If no local path available, the file may be stored in a remote file
    // system. Use the file metadata manager service to copy the file to local
    // temp and keep it there for further access within same request. It is not
    // necessary to load its metadata.
    $file = \Drupal::service('file_metadata_manager')->uri($font_uri);
    $local_path = $file->getLocalTempPath();
    if ($local_path !== NULL) {
      return $local_path;
    }
    elseif ($file->copyUriToTemp() === TRUE) {
      return $file->getLocalTempPath();
    }

    // None of the above worked, file can not be accessed.
    throw new \InvalidArgumentException("Cannot access font file '$font_uri'");
  }

}
