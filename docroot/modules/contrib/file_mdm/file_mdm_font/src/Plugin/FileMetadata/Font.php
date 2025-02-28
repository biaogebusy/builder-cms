<?php

namespace Drupal\file_mdm_font\Plugin\FileMetadata;

use Drupal\file_mdm\FileMetadataException;
use Drupal\file_mdm\Plugin\FileMetadata\FileMetadataPluginBase;
use FontLib\Font as LibFont;
use FontLib\Table\Type\name;

/**
 * FileMetadata plugin for TTF/OTF/WOFF font information.
 *
 * Uses the 'PHP Font Lib' library (PhenX/php-font-lib).
 *
 * @FileMetadata(
 *   id = "font",
 *   title = @Translation("Font"),
 *   help = @Translation("File metadata plugin for TTF/OTF/WOFF font information, using the PHP Font Lib."),
 * )
 */
class Font extends FileMetadataPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getSupportedKeys($options = NULL) {
    return array_merge(['FontType', 'FontWeight'], array_values(name::$nameIdCodes));
  }

  /**
   * {@inheritdoc}
   */
  protected function doGetMetadataFromFile() {
    $font = LibFont::load($this->getLocalTempPath());
    // @todo ::parse raises 'Undefined offset' notices in phenx/php-font-lib
    // 0.5, suppress errors while upstream is fixed.
    @$font->parse();
    $keys = $this->getSupportedKeys();
    $metadata = [];
    foreach ($keys as $key) {
      $l_key = strtolower($key);
      switch ($l_key) {
        case 'fonttype':
          $metadata[$l_key] = $font->getFontType();
          break;

        case 'fontweight':
          $metadata[$l_key] = $font->getFontWeight();
          break;

        default:
          $code = array_search($l_key, array_map('strtolower', name::$nameIdCodes), TRUE);
          if ($value = $font->getNameTableString($code)) {
            $metadata[$l_key] = $value;
          }
          break;

      }
    }
    return $metadata;
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
    if (!is_string($key)) {
      throw new FileMetadataException("Invalid metadata key specified", $this->getPluginId(), $method);
    }
    if (!in_array(strtolower($key), array_map('strtolower', $this->getSupportedKeys()), TRUE)) {
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
      $l_key = strtolower($key);
      if (in_array($l_key, array_map('strtolower', $this->getSupportedKeys()), TRUE)) {
        return isset($this->metadata[$l_key]) ? $this->metadata[$l_key] : NULL;
      }
      return NULL;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function doSetMetadata($key, $value) {
    throw new FileMetadataException('Changing font metadata is not supported', $this->getPluginId());
  }

  /**
   * {@inheritdoc}
   */
  protected function doRemoveMetadata($key) {
    throw new FileMetadataException('Deleting font metadata is not supported', $this->getPluginId());
  }

}
