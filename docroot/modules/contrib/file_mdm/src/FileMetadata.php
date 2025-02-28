<?php

namespace Drupal\file_mdm;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file_mdm\Plugin\FileMetadataPluginManager;
use Psr\Log\LoggerInterface;

/**
 * A file metadata object.
 */
class FileMetadata implements FileMetadataInterface {

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
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The URI of the file.
   *
   * @var string
   */
  protected $uri = '';

  /**
   * The hash used to reference the URI.
   *
   * @var string
   */
  protected $hash;

  /**
   * The local filesystem path to the file.
   *
   * This is used to allow accessing local copies of files stored remotely, to
   * minimise remote calls and allow functions that cannot access remote stream
   * wrappers to operate locally.
   *
   * @var string
   */
  protected $localTempPath;

  /**
   * The array of FileMetadata plugins for this URI.
   *
   * @var \Drupal\file_mdm\Plugin\FileMetadataPluginInterface[]
   */
  protected $plugins = [];

  /**
   * Constructs a FileMetadata object.
   *
   * @param \Drupal\file_mdm\Plugin\FileMetadataPluginManager $plugin_manager
   *   The file metadata plugin manager.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param string $uri
   *   The URI of the file.
   * @param string $hash
   *   The hash used to reference the URI by file_mdm.
   */
  public function __construct(FileMetadataPluginManager $plugin_manager, LoggerInterface $logger, FileSystemInterface $file_system, $uri, $hash) {
    $this->pluginManager = $plugin_manager;
    $this->logger = $logger;
    $this->fileSystem = $file_system;
    $this->uri = $uri;
    $this->hash = $hash;
  }

  /**
   * {@inheritdoc}
   */
  public function getUri() {
    return $this->uri;
  }

  /**
   * {@inheritdoc}
   */
  public function getLocalTempPath() {
    return $this->localTempPath;
  }

  /**
   * {@inheritdoc}
   */
  public function setLocalTempPath($temp_uri) {
    $this->localTempPath = $temp_uri;
    foreach ($this->plugins as $plugin) {
      $plugin->setLocalTempPath($this->localTempPath);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function copyUriToTemp($temp_uri = NULL) {
    if ($temp_uri === NULL) {
      $temp_uri = $this->fileSystem->tempnam('temporary://', 'file_mdm_');
      $this->fileSystem->unlink($temp_uri);
      $temp_uri .= '.' . pathinfo($this->getUri(), PATHINFO_EXTENSION);
    }
    if ($temp_path = $this->fileSystem->copy($this->getUri(), $this->fileSystem->realpath($temp_uri), FileSystemInterface::EXISTS_REPLACE)) {
      $this->setLocalTempPath($temp_path);
    }
    return (bool) $temp_path;
  }

  /**
   * {@inheritdoc}
   */
  public function copyTempToUri() {
    if (($temp_path = $this->getLocalTempPath()) === NULL) {
      return FALSE;
    }
    return (bool) $this->fileSystem->copy($temp_path, $this->getUri(), FileSystemInterface::EXISTS_REPLACE);
  }

  /**
   * {@inheritdoc}
   */
  public function getFileMetadataPlugin($metadata_id) {
    if (!isset($this->plugins[$metadata_id])) {
      try {
        $this->plugins[$metadata_id] = $this->pluginManager->createInstance($metadata_id);
        $this->plugins[$metadata_id]->setUri($this->uri);
        $this->plugins[$metadata_id]->setLocalTempPath($this->localTempPath ?: $this->uri);
        $this->plugins[$metadata_id]->setHash($this->hash);
      }
      catch (PluginNotFoundException $e) {
        return NULL;
      }
    }
    return $this->plugins[$metadata_id];
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedKeys($metadata_id, $options = NULL) {
    try {
      if ($plugin = $this->getFileMetadataPlugin($metadata_id)) {
        $keys = $plugin->getSupportedKeys($options);
      }
      else {
        $keys = NULL;
      }
    }
    catch (\Exception $e) {
      $this->logger->error('Error getting supported keys for @metadata metadata for @uri. Message: @message', [
        '@metadata' => $metadata_id ?? '',
        '@uri' => $this->uri ?? '',
        '@message' => $e->getMessage() ?? '',
      ]);
      $keys = NULL;
    }
    return $keys;
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadata($metadata_id, $key = NULL) {
    try {
      if ($plugin = $this->getFileMetadataPlugin($metadata_id)) {
        $metadata = $plugin->getMetadata($key);
      }
      else {
        $metadata = NULL;
      }
    }
    catch (\Exception $e) {
      $this->logger->error('Error getting @metadata_id@key metadata for @uri. Message: @message', [
        '@metadata_id' => $metadata_id ?? '',
        '@key' => $key ? ' ('. var_export($key, TRUE) . ')' : '',
        '@uri' => $this->uri ?? '',
        '@message' => $e->getMessage() ?? '',
      ]);
      $metadata = NULL;
    }
    return $metadata;
  }

  /**
   * {@inheritdoc}
   */
  public function removeMetadata($metadata_id, $key) {
    try {
      if ($plugin = $this->getFileMetadataPlugin($metadata_id)) {
        return $plugin->removeMetadata($key);
      }
    }
    catch (\Exception $e) {
      $this->logger->error('Error deleting @key from @metadata_id metadata for @uri. Message: @message', [
        '@metadata_id' => $metadata_id ?? '',
        '@key' => $key ? var_export($key, TRUE) : '',
        '@uri' => $this->uri ?? '',
        '@message' => $e->getMessage() ?? '',
      ]);
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function setMetadata($metadata_id, $key, $value) {
    try {
      if ($plugin = $this->getFileMetadataPlugin($metadata_id)) {
        return $plugin->setMetadata($key, $value);
      }
    }
    catch (\Exception $e) {
      $this->logger->error('Error setting @metadata_id@key metadata for @uri. Message: @message', [
        '@metadata_id' => $metadata_id ?? '',
        '@key' => $key ? ' (' . var_export($key, TRUE) . ')' : '',
        '@uri' => $this->uri ?? '',
        '@message' => $e->getMessage() ?? '',
      ]);
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function isMetadataLoaded($metadata_id) {
    if ($plugin = $this->getFileMetadataPlugin($metadata_id)) {
      return $plugin->isMetadataLoaded();
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function loadMetadata($metadata_id, $metadata) {
    if ($plugin = $this->getFileMetadataPlugin($metadata_id)) {
      return $plugin->loadMetadata($metadata);
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function loadMetadataFromCache($metadata_id) {
    if ($plugin = $this->getFileMetadataPlugin($metadata_id)) {
      return $plugin->loadMetadataFromCache();
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function saveMetadataToCache($metadata_id, array $tags = []) {
    if ($plugin = $this->getFileMetadataPlugin($metadata_id)) {
      return $plugin->saveMetadataToCache($tags);
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function saveMetadataToFile($metadata_id) {
    if ($plugin = $this->getFileMetadataPlugin($metadata_id)) {
      return $plugin->saveMetadataToFile();
    }
    return FALSE;
  }

}
