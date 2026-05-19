<?php

namespace Drupal\simple_oauth\Service\Filesystem;

/**
 * The file system validator interface.
 *
 * @internal
 */
interface FilesystemValidatorInterface {

  /**
   * Validate {@var $ext_name} extension exist.
   *
   * @param string $extension
   *   The extension name. This parameter is case-insensitive.
   *
   * @throws \Drupal\simple_oauth\Service\Exception\ExtensionNotLoadedException
   */
  public function validateOpensslExtensionExist(string $extension): void;

  /**
   * Validate that {@var $paths} are directories.
   *
   * @param array $paths
   *   List of URIs.
   *
   * @throws \Drupal\simple_oauth\Service\Exception\FilesystemValidationException
   */
  public function validateAreDirs(array $paths): void;

  /**
   * Validate that {@var $paths} are writable.
   *
   * @param array $paths
   *   List of URIs.
   *
   * @throws \Drupal\simple_oauth\Service\Exception\FilesystemValidationException
   */
  public function validateAreWritable(array $paths): void;

  /**
   * Validate that {@var $paths} are not the file public path.
   *
   * @param array $paths
   *   List of URIs.
   *
   * @throws \Drupal\simple_oauth\Service\Exception\FilesystemValidationException
   */
  public function validateNotFilePublicPath(array $paths): void;

}
