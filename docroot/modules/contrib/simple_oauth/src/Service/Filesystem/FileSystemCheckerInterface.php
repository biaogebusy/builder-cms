<?php

namespace Drupal\simple_oauth\Service\Filesystem;

/**
 * The file system checker interface.
 *
 * @internal
 */
interface FileSystemCheckerInterface {

  /**
   * Find out whether an extension is loaded.
   *
   * @param string $extension
   *   The extension name. This parameter is case-insensitive.
   *
   * @return bool
   *   Returns true if the extension identified by extension is loaded,
   *   false otherwise.
   */
  public function isExtensionEnabled(string $extension): bool;

  /**
   * Tells whether the filename is a directory.
   *
   * @param string $file_path
   *   Path to the file.
   *
   * @return bool
   *   Returns true if the filename exists and is a directory, false otherwise.
   */
  public function isDirectory(string $file_path): bool;

  /**
   * Tells whether the filename is writable.
   *
   * @param string $file_path
   *   The file path being checked.
   *
   * @return bool
   *   Returns true if the filename exists and is writable.
   */
  public function isWritable(string $file_path): bool;

  /**
   * Checks whether a file or directory exists.
   *
   * @param string $file_path
   *   Path to the file or directory.
   *
   * @return bool
   *   Returns true if the file or directory specified by file path exists;
   *   false otherwise.
   */
  public function fileExist(string $file_path): bool;

  /**
   * Write data to a file.
   *
   * @param string $file_path
   *   Path to the file where to write the data.
   * @param mixed $data
   *   The data to write. Can be either a string, an array or a stream resource.
   *
   * @return false|int
   *   Returns the number of bytes that were written to the file,
   *   or false on failure.
   */
  public function write(string $file_path, mixed $data): mixed;

  /**
   * Tells whether a file exists and is readable.
   *
   * @param string $file_path
   *   Path to the file.
   *
   * @return bool
   *   Returns true if the file or directory specified by file path exists and
   *   is readable, false otherwise.
   */
  public function isReadable(string $file_path): bool;

}
