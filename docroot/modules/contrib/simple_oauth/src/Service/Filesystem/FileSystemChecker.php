<?php

namespace Drupal\simple_oauth\Service\Filesystem;

/**
 * @internal
 */
class FileSystemChecker {

  /**
   * {@inheritdoc}
   */
  public function isExtensionEnabled($extension) {
    return @extension_loaded($extension);
  }

  /**
   * {@inheritdoc}
   */
  public function isDirectory($uri) {
    return @is_dir($uri);
  }

  /**
   * {@inheritdoc}
   */
  public function isWritable($uri) {
    return @is_writable($uri);
  }

  /**
   * {@inheritdoc}
   */
  public function fileExist($uri) {
    return @file_exists($uri);
  }

  /**
   * {@inheritdoc}
   */
  public function write($uri, $content) {
    return @file_put_contents($uri, $content);
  }

  /**
   * {@inheritdoc}
   */
  public function isReadable($uri) {
    return @is_readable($uri);
  }

}
