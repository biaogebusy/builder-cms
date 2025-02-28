<?php

namespace Drupal\file_mdm;

/**
 * Exception thrown by file_mdm and plugins on failures.
 */
class FileMetadataException extends \Exception {

  /**
   * Constructs a FileMetadataException object.
   */
  public function __construct($message, $plugin_id = NULL, $method = NULL, \Exception $previous = NULL) {
    $msg = $message;
    $msg .= $plugin_id ? " (plugin: {$plugin_id})" : "";
    $msg .= $method ? " (method: {$method})" : "";
    parent::__construct($msg, 0, $previous);
  }

}
