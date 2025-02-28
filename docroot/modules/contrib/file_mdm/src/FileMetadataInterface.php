<?php

namespace Drupal\file_mdm;

/**
 * Provides an interface for file metadata objects.
 */
interface FileMetadataInterface {

  /**
   * Metadata not loaded.
   */
  const NOT_LOADED = 0;

  /**
   * Metadata loaded by code.
   */
  const LOADED_BY_CODE = 1;

  /**
   * Metadata loaded from cache.
   */
  const LOADED_FROM_CACHE = 2;

  /**
   * Metadata loaded from file.
   */
  const LOADED_FROM_FILE = 3;

  /**
   * Gets the URI of the file.
   *
   * @return string|null
   *   The URI of the file, or a local path.
   */
  public function getUri();

  /**
   * Gets the local filesystem URI to the temporary file.
   *
   * @return string|null
   *   The URI, or a local path, of the temporary file.
   */
  public function getLocalTempPath();

  /**
   * Sets the local filesystem URI to the temporary file.
   *
   * @param string $temp_uri
   *   A URI to a temporary file.
   *
   * @return $this
   */
  public function setLocalTempPath($temp_uri);

  /**
   * Copies the file at URI to a local temporary file.
   *
   * @param string $temp_uri
   *   (optional) a URI to a temporary file. If NULL, a temp URI will be
   *   defined by the operation. Defaults to NULL.
   *
   * @return bool
   *   TRUE if the file was copied successfully, FALSE
   *   otherwise.
   */
  public function copyUriToTemp($temp_uri = NULL);

  /**
   * Copies the local temporary file to the destination URI.
   *
   * @return bool
   *   TRUE if the file was copied successfully, FALSE
   *   otherwise.
   */
  public function copyTempToUri();

  /**
   * Gets a FileMetadata plugin instance.
   *
   * @param string $metadata_id
   *   The id of the plugin whose instance is to be returned. If it is does
   *   not exist, an instance is created.
   *
   * @return \Drupal\file_mdm\Plugin\FileMetadataPluginInterface|null
   *   The FileMetadata plugin instance. NULL if no plugin is found.
   */
  public function getFileMetadataPlugin($metadata_id);

  /**
   * Returns a list of supported metadata keys.
   *
   * @param string $metadata_id
   *   The id of the FileMetadata plugin.
   * @param mixed $options
   *   (optional) Allows specifying additional options to control the list of
   *   metadata keys returned.
   *
   * @return array
   *   A simple array of metadata keys supported.
   */
  public function getSupportedKeys($metadata_id, $options = NULL);

  /**
   * Gets a metadata element.
   *
   * @param string $metadata_id
   *   The id of the FileMetadata plugin.
   * @param mixed|null $key
   *   A key to determine the metadata element to be returned. If NULL, the
   *   entire metadata will be returned.
   *
   * @return mixed
   *   The value of the element specified by $key. If $key is NULL, the entire
   *   metadata.
   */
  public function getMetadata($metadata_id, $key = NULL);

  /**
   * Removes a metadata element.
   *
   * @param string $metadata_id
   *   The id of the FileMetadata plugin.
   * @param mixed $key
   *   A key to determine the metadata element to be removed.
   *
   * @return bool
   *   TRUE if metadata was removed successfully, FALSE otherwise.
   */
  public function removeMetadata($metadata_id, $key);

  /**
   * Sets a metadata element.
   *
   * @param string $metadata_id
   *   The id of the FileMetadata plugin.
   * @param mixed $key
   *   A key to determine the metadata element to be changed.
   * @param mixed $value
   *   The value to change the metadata element to.
   *
   * @return bool
   *   TRUE if metadata was changed successfully, FALSE otherwise.
   */
  public function setMetadata($metadata_id, $key, $value);

  /**
   * Checks if file metadata has been already loaded.
   *
   * @param string $metadata_id
   *   The id of the FileMetadata plugin.
   *
   * @return bool
   *   TRUE if metadata is loaded, FALSE otherwise.
   */
  public function isMetadataLoaded($metadata_id);

  /**
   * Loads file metadata.
   *
   * @param string $metadata_id
   *   The id of the FileMetadata plugin.
   * @param mixed $metadata
   *   The file metadata associated to the file at URI.
   *
   * @return bool
   *   TRUE if metadata was loaded successfully, FALSE otherwise.
   */
  public function loadMetadata($metadata_id, $metadata);

  /**
   * Loads file metadata from a cache entry.
   *
   * @param string $metadata_id
   *   The id of the FileMetadata plugin.
   *
   * @return bool
   *   TRUE if metadata was loaded successfully, FALSE otherwise.
   */
  public function loadMetadataFromCache($metadata_id);

  /**
   * Caches metadata for file at URI.
   *
   * Uses the 'file_mdm' cache bin.
   *
   * @param string $metadata_id
   *   The id of the FileMetadata plugin.
   * @param array $tags
   *   (optional) An array of cache tags to save to cache.
   *
   * @return bool
   *   TRUE if metadata was saved successfully, FALSE otherwise.
   */
  public function saveMetadataToCache($metadata_id, array $tags = []);

  /**
   * Saves metadata to file at URI.
   *
   * @param string $metadata_id
   *   The id of the FileMetadata plugin.
   *
   * @return bool
   *   TRUE if metadata was saved successfully, FALSE otherwise.
   */
  public function saveMetadataToFile($metadata_id);

}
