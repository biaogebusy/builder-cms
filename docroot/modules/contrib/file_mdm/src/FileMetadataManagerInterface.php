<?php

namespace Drupal\file_mdm;

/**
 * Provides an interface for file metadata manager objects.
 */
interface FileMetadataManagerInterface {

  /**
   * Determines if the URI is currently in use by the manager.
   *
   * @param string $uri
   *   The URI to a file.
   *
   * @return bool
   *   TRUE if the URI is in use, FALSE otherwise.
   */
  public function has($uri);

  /**
   * Returns a FileMetadata object for the URI, creating it if necessary.
   *
   * @param string $uri
   *   The URI to a file.
   *
   * @return \Drupal\file_mdm\FileMetadataInterface|null
   *   The FileMetadata object for the specified URI.
   */
  public function uri($uri);

  /**
   * Deletes the all the cached metadata for the URI.
   *
   * @param string $uri
   *   The URI to a file.
   *
   * @return bool
   *   TRUE if the cached metadata was removed, FALSE in case of error.
   */
  public function deleteCachedMetadata($uri);

  /**
   * Releases the FileMetadata object for the URI.
   *
   * @param string $uri
   *   The URI to a file.
   *
   * @return bool
   *   TRUE if the FileMetadata for the URI was removed from the manager,
   *   FALSE otherwise.
   */
  public function release($uri);

  /**
   * Returns the count of FileMetadata objects currently in use.
   *
   * @return int
   *   The number of FileMetadata objects currently in use.
   */
  public function count();

}
