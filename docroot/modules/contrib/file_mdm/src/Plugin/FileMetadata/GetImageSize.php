<?php

namespace Drupal\file_mdm\Plugin\FileMetadata;

use Drupal\file_mdm\FileMetadataException;

/**
 * FileMetadata plugin for getimagesize.
 *
 * @FileMetadata(
 *   id = "getimagesize",
 *   title = @Translation("Getimagesize"),
 *   help = @Translation("File metadata plugin for PHP getimagesize()."),
 * )
 */
class GetImageSize extends FileMetadataPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getSupportedKeys($options = NULL) {
    return [0, 1, 2, 3, 'mime', 'channels', 'bits'];
  }

  /**
   * {@inheritdoc}
   */
  protected function doGetMetadataFromFile() {
    if ($data = @getimagesize($this->getLocalTempPath())) {
      return $data;
    }
    else {
      return NULL;
    }
  }

  /**
   * Validates a file metadata key.
   *
   * @return bool
   *   TRUE if the key is valid.
   *
   * @throws \Drupal\file_mdm\FileMetadataException
   *   In case the key is invalid.
   */
  protected function validateKey($key, $method) {
    if (!is_int($key) && !is_string($key)) {
      throw new FileMetadataException("Invalid metadata key specified", $this->getPluginId(), $method);
    }
    if (!in_array($key, $this->getSupportedKeys(), TRUE)) {
      throw new FileMetadataException("Invalid metadata key '{$key}' specified", $this->getPluginId(), $method);
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  protected function doGetMetadata($key = NULL) {
    if ($key === NULL) {
      return $this->metadata;
    }
    else {
      $this->validateKey($key, __FUNCTION__);
      return isset($this->metadata[$key]) ? $this->metadata[$key] : NULL;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function doSetMetadata($key, $value) {
    $this->validateKey($key, __FUNCTION__);
    $this->metadata[$key] = $value;
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  protected function doRemoveMetadata($key) {
    $this->validateKey($key, __FUNCTION__);
    if (isset($this->metadata[$key])) {
      unset($this->metadata[$key]);
    }
    return TRUE;
  }

}
