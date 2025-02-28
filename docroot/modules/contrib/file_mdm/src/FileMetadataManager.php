<?php

namespace Drupal\file_mdm;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\file_mdm\Plugin\FileMetadataPluginManager;
use Psr\Log\LoggerInterface;

/**
 * A service class to provide file metadata.
 */
class FileMetadataManager implements FileMetadataManagerInterface {

  use StringTranslationTrait;

  /**
   * The FileMetadata plugin manager.
   *
   * @var \Drupal\file_mdm\Plugin\FileMetadataPluginManager
   */
  protected $pluginManager;

  /**
   * The file_mdm logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The cache service.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * The stream wrapper manager service.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface
   */
  protected $streamWrapperManager;

  /**
   * The array of FileMetadata objects currently in use.
   *
   * @var \Drupal\file_mdm\FileMetadataInterface[]
   */
  protected $files = [];

  /**
   * Constructs a FileMetadataManager object.
   *
   * @param \Drupal\file_mdm\Plugin\FileMetadataPluginManager $plugin_manager
   *   The FileMetadata plugin manager.
   * @param \Psr\Log\LoggerInterface $logger
   *   The file_mdm logger.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_service
   *   The cache service.
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $stream_wrapper_manager
   *   The stream wrapper manager service.
   */
  public function __construct(FileMetadataPluginManager $plugin_manager, LoggerInterface $logger, ConfigFactoryInterface $config_factory, FileSystemInterface $file_system, CacheBackendInterface $cache_service, StreamWrapperManagerInterface $stream_wrapper_manager) {
    $this->pluginManager = $plugin_manager;
    $this->logger = $logger;
    $this->configFactory = $config_factory;
    $this->fileSystem = $file_system;
    $this->cache = $cache_service;
    $this->streamWrapperManager = $stream_wrapper_manager;
  }

  /**
   * Returns an hash for the URI, used internally by the manager.
   *
   * @param string $uri
   *   The URI to a file.
   *
   * @return string
   *   An hash string.
   */
  protected function calculateHash($uri) {
    // Sanitize URI removing duplicate slashes, if any.
    // @see http://stackoverflow.com/questions/12494515/remove-unnecessary-slashes-from-path
    $uri = preg_replace('/([^:])(\/{2,})/', '$1/', $uri);
    // If URI is invalid and no local file path exists, return NULL.
    if (!$this->streamWrapperManager->isValidUri($uri) && !$this->fileSystem->realpath($uri)) {
      return NULL;
    }
    // Return a hash of the URI.
    return hash('sha256', $uri);
  }

  /**
   * {@inheritdoc}
   */
  public function has($uri) {
    $hash = $this->calculateHash($uri);
    return $hash ? isset($this->files[$hash]) : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function uri($uri) {
    if (!$hash = $this->calculateHash($uri)) {
      return NULL;
    }
    if (!isset($this->files[$hash])) {
      $this->files[$hash] = new FileMetadata($this->pluginManager, $this->logger, $this->fileSystem, $uri, $hash);
    }
    return $this->files[$hash];
  }

  /**
   * {@inheritdoc}
   */
  public function deleteCachedMetadata($uri) {
    if (!$hash = $this->calculateHash($uri)) {
      return FALSE;
    }
    foreach (array_keys($this->pluginManager->getDefinitions()) as $plugin_id) {
      $this->cache->delete("hash:{$plugin_id}:{$hash}");
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function release($uri) {
    if (!$hash = $this->calculateHash($uri)) {
      return FALSE;
    }
    if (isset($this->files[$hash])) {
      unset($this->files[$hash]);
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function count() {
    return count($this->files);
  }

}
