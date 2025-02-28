<?php

namespace Drupal\file_mdm\Plugin\FileMetadata;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\file_mdm\FileMetadataException;
use Drupal\file_mdm\FileMetadataInterface;
use Drupal\file_mdm\Plugin\FileMetadataPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Abstract implementation of a base File Metadata plugin.
 */
abstract class FileMetadataPluginBase extends PluginBase implements FileMetadataPluginInterface {

  /**
   * The cache service.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The stream wrapper manager service.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface
   */
  protected $streamWrapperManager;

  /**
   * The URI of the file.
   *
   * @var string
   */
  protected $uri;

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
   * The hash used to reference the URI.
   *
   * @var string
   */
  protected $hash;

  /**
   * The metadata of the file.
   *
   * @var mixed
   */
  protected $metadata = NULL;

  /**
   * The metadata loading status.
   *
   * @var int
   */
  protected $isMetadataLoaded = FileMetadataInterface::NOT_LOADED;

  /**
   * Track if metadata has been changed from version on file.
   *
   * @var bool
   */
  protected $hasMetadataChangedFromFileVersion = FALSE;

  /**
   * Track if file metadata on cache needs update.
   *
   * @var bool
   */
  protected $hasMetadataChangedFromCacheVersion = FALSE;

  /**
   * Constructs a FileMetadataPluginBase plugin.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_service
   *   The cache service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $stream_wrapper_manager
   *   The stream wrapper manager service.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, CacheBackendInterface $cache_service, ConfigFactoryInterface $config_factory, StreamWrapperManagerInterface $stream_wrapper_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->cache = $cache_service;
    $this->configFactory = $config_factory;
    $this->streamWrapperManager = $stream_wrapper_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('cache.file_mdm'),
      $container->get('config.factory'),
      $container->get('stream_wrapper_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultConfiguration() {
    return [
      'cache' => [
        'override' => FALSE,
        'settings' => [
          'enabled' => TRUE,
          'expiration' => 172800,
          'disallowed_paths' => [],
        ],
      ],
    ];
  }

  /**
   * Gets the configuration object for this plugin.
   *
   * @param bool $editable
   *   If TRUE returns the editable configuration object.
   *
   * @return \Drupal\Core\Config\ImmutableConfig|\Drupal\Core\Config\Config
   *   The ImmutableConfig of the Config object for this plugin.
   */
  protected function getConfigObject($editable = FALSE) {
    $plugin_definition = $this->getPluginDefinition();
    $config_name = $plugin_definition['provider'] . '.file_metadata_plugin.' . $plugin_definition['id'];
    return $editable ? $this->configFactory->getEditable($config_name) : $this->configFactory->get($config_name);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['override'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Override main caching settings'),
      '#default_value' => $this->configuration['cache']['override'],
    ];
    $form['cache_details'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#collapsible' => FALSE,
      '#title' => $this->t('Metadata caching'),
      '#tree' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="' . $this->getPluginId() . '[override]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form['cache_details']['settings'] = [
      '#type' => 'file_mdm_caching',
      '#default_value' => $this->configuration['cache']['settings'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    // @codingStandardsIgnoreStart
    $this->configuration['cache']['override'] = (bool) $form_state->getValue([$this->getPluginId(), 'override']);
    $this->configuration['cache']['settings'] = $form_state->getValue([$this->getPluginId(), 'cache_details', 'settings']);
    // @codingStandardsIgnoreEnd

    $config = $this->getConfigObject(TRUE);
    $config->set('configuration', $this->configuration);
    if ($config->getOriginal('configuration') != $config->get('configuration')) {
      $config->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setUri($uri) {
    if (!$uri) {
      throw new FileMetadataException('Missing $uri argument', $this->getPluginId(), __FUNCTION__);
    }
    $this->uri = $uri;
    return $this;
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
  public function setLocalTempPath($temp_path) {
    $this->localTempPath = $temp_path;
    return $this;
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
  public function setHash($hash) {
    if (!$hash) {
      throw new FileMetadataException('Missing $hash argument', $this->getPluginId(), __FUNCTION__);
    }
    $this->hash = $hash;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isMetadataLoaded() {
    return $this->isMetadataLoaded;
  }

  /**
   * {@inheritdoc}
   */
  public function loadMetadata($metadata) {
    $this->metadata = $metadata;
    $this->hasMetadataChangedFromFileVersion = TRUE;
    $this->hasMetadataChangedFromCacheVersion = TRUE;
    $this->deleteCachedMetadata();
    if ($this->metadata === NULL) {
      $this->isMetadataLoaded = FileMetadataInterface::NOT_LOADED;
    }
    else {
      $this->isMetadataLoaded = FileMetadataInterface::LOADED_BY_CODE;
      $this->saveMetadataToCache();
    }
    return (bool) $this->metadata;
  }

  /**
   * {@inheritdoc}
   */
  public function loadMetadataFromFile() {
    if (!file_exists($this->getLocalTempPath())) {
      // File does not exists.
      throw new FileMetadataException("File at '{$this->getLocalTempPath()}' does not exist", $this->getPluginId(), __FUNCTION__);
    }
    $this->hasMetadataChangedFromFileVersion = FALSE;
    if (($this->metadata = $this->doGetMetadataFromFile()) === NULL) {
      $this->isMetadataLoaded = FileMetadataInterface::NOT_LOADED;
      $this->deleteCachedMetadata();
    }
    else {
      $this->isMetadataLoaded = FileMetadataInterface::LOADED_FROM_FILE;
      $this->saveMetadataToCache();
    }
    return (bool) $this->metadata;
  }

  /**
   * Gets file metadata from the file at URI/local path.
   *
   * @return mixed
   *   The metadata retrieved from the file.
   *
   * @throws \Drupal\file_mdm\FileMetadataException
   *   In case there were significant errors reading from file.
   */
  abstract protected function doGetMetadataFromFile();

  /**
   * {@inheritdoc}
   */
  public function loadMetadataFromCache() {
    $plugin_id = $this->getPluginId();
    $this->hasMetadataChangedFromFileVersion = FALSE;
    $this->hasMetadataChangedFromCacheVersion = FALSE;
    if ($this->isUriFileMetadataCacheable() !== FALSE && ($cache = $this->cache->get("hash:{$plugin_id}:{$this->hash}"))) {
      $this->metadata = $cache->data;
      $this->isMetadataLoaded = FileMetadataInterface::LOADED_FROM_CACHE;
    }
    else {
      $this->metadata = NULL;
      $this->isMetadataLoaded = FileMetadataInterface::NOT_LOADED;
    }
    return (bool) $this->metadata;
  }

  /**
   * Checks if file metadata should be cached.
   *
   * @return array|bool
   *   The caching settings array retrieved from configuration if file metadata
   *   is cacheable, FALSE otherwise.
   */
  protected function isUriFileMetadataCacheable() {
    // Check plugin settings first, if they override general settings.
    if ($this->configuration['cache']['override']) {
      $settings = $this->configuration['cache']['settings'];
      if (!$settings['enabled']) {
        return FALSE;
      }
    }

    // Use general settings if they are not overridden by plugin.
    if (!isset($settings)) {
      $settings = $this->configFactory->get('file_mdm.settings')->get('metadata_cache');
      if (!$settings['enabled']) {
        return FALSE;
      }
    }

    // URIs without valid scheme, and temporary:// URIs are not cached.
    if (!$this->streamWrapperManager->isValidUri($this->getUri()) || $this->streamWrapperManager->getScheme($this->getUri()) === 'temporary') {
      return FALSE;
    }

    // URIs falling into disallowed paths are not cached.
    foreach ($settings['disallowed_paths'] as $pattern) {
      $p = "#^" . strtr(preg_quote($pattern, '#'), ['\*' => '.*', '\?' => '.']) . "$#i";
      if (preg_match($p, $this->getUri())) {
        return FALSE;
      }
    }

    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadata($key = NULL) {
    if (!$this->getUri()) {
      throw new FileMetadataException("No URI specified", $this->getPluginId(), __FUNCTION__);
    }
    if (!$this->hash) {
      throw new FileMetadataException("No hash specified", $this->getPluginId(), __FUNCTION__);
    }
    if ($this->metadata === NULL) {
      // Metadata has not been loaded yet. Try loading it from cache first.
      $this->loadMetadataFromCache();
    }
    if ($this->metadata === NULL && $this->isMetadataLoaded !== FileMetadataInterface::LOADED_FROM_FILE) {
      // Metadata has not been loaded yet. Try loading it from file if URI is
      // defined and a read attempt was not made yet.
      $this->loadMetadataFromFile();
    }
    return $this->doGetMetadata($key);
  }

  /**
   * Gets a metadata element.
   *
   * @param mixed|null $key
   *   A key to determine the metadata element to be returned. If NULL, the
   *   entire metadata will be returned.
   *
   * @return mixed|null
   *   The value of the element specified by $key. If $key is NULL, the entire
   *   metadata. If no metadata is available, return NULL.
   */
  abstract protected function doGetMetadata($key = NULL);

  /**
   * {@inheritdoc}
   */
  public function setMetadata($key, $value) {
    if ($key === NULL) {
      throw new FileMetadataException("No metadata key specified for file at '{$this->getUri()}'", $this->getPluginId(), __FUNCTION__);
    }
    if (!$this->metadata && !$this->getMetadata()) {
      throw new FileMetadataException("No metadata loaded for file at '{$this->getUri()}'", $this->getPluginId(), __FUNCTION__);
    }
    if ($this->doSetMetadata($key, $value)) {
      $this->hasMetadataChangedFromFileVersion = TRUE;
      if ($this->isMetadataLoaded === FileMetadataInterface::LOADED_FROM_CACHE) {
        $this->hasMetadataChangedFromCacheVersion = TRUE;
      }
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Sets a metadata element.
   *
   * @param mixed $key
   *   A key to determine the metadata element to be changed.
   * @param mixed $value
   *   The value to change the metadata element to.
   *
   * @return bool
   *   TRUE if metadata was changed successfully, FALSE otherwise.
   */
  abstract protected function doSetMetadata($key, $value);

  /**
   * {@inheritdoc}
   */
  public function removeMetadata($key) {
    if ($key === NULL) {
      throw new FileMetadataException("No metadata key specified for file at '{$this->getUri()}'", $this->getPluginId(), __FUNCTION__);
    }
    if (!$this->metadata && !$this->getMetadata()) {
      throw new FileMetadataException("No metadata loaded for file at '{$this->getUri()}'", $this->getPluginId(), __FUNCTION__);
    }
    if ($this->doRemoveMetadata($key)) {
      $this->hasMetadataChangedFromFileVersion = TRUE;
      if ($this->isMetadataLoaded === FileMetadataInterface::LOADED_FROM_CACHE) {
        $this->hasMetadataChangedFromCacheVersion = TRUE;
      }
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Removes a metadata element.
   *
   * @param mixed $key
   *   A key to determine the metadata element to be removed.
   *
   * @return bool
   *   TRUE if metadata was removed successfully, FALSE otherwise.
   */
  abstract protected function doRemoveMetadata($key);

  /**
   * {@inheritdoc}
   */
  public function isSaveToFileSupported() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function saveMetadataToFile() {
    if (!$this->isSaveToFileSupported()) {
      throw new FileMetadataException('Write metadata to file is not supported', $this->getPluginId(), __FUNCTION__);
    }
    if ($this->metadata === NULL) {
      return FALSE;
    }
    if ($this->hasMetadataChangedFromFileVersion) {
      // Clears cache so that next time metadata will be fetched from file.
      $this->deleteCachedMetadata();
      return $this->doSaveMetadataToFile();
    }
    return FALSE;
  }

  /**
   * Saves metadata to file at URI.
   *
   * @return bool
   *   TRUE if metadata was saved successfully, FALSE otherwise.
   */
  protected function doSaveMetadataToFile() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function saveMetadataToCache(array $tags = []) {
    if ($this->metadata === NULL) {
      return FALSE;
    }
    if (($cache_settings = $this->isUriFileMetadataCacheable()) === FALSE) {
      return FALSE;
    }
    if ($this->isMetadataLoaded !== FileMetadataInterface::LOADED_FROM_CACHE || ($this->isMetadataLoaded === FileMetadataInterface::LOADED_FROM_CACHE && $this->hasMetadataChangedFromCacheVersion)) {
      $tags = Cache::mergeTags($tags, $this->getConfigObject()->getCacheTags());
      $tags = Cache::mergeTags($tags, $this->configFactory->get('file_mdm.settings')->getCacheTags());
      $expire = $cache_settings['expiration'] === -1 ? Cache::PERMANENT : time() + $cache_settings['expiration'];
      $this->cache->set("hash:{$this->getPluginId()}:{$this->hash}", $this->getMetadataToCache(), $expire, $tags);
      $this->hasMetadataChangedFromCacheVersion = FALSE;
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Gets metadata to save to cache.
   *
   * @return mixed
   *   The metadata to be cached.
   */
  protected function getMetadataToCache() {
    return $this->metadata;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteCachedMetadata() {
    if ($this->isUriFileMetadataCacheable() === FALSE) {
      return FALSE;
    }
    $plugin_id = $this->getPluginId();
    $this->cache->delete("hash:{$plugin_id}:{$this->hash}");
    $this->hasMetadataChangedFromCacheVersion = FALSE;
    return TRUE;
  }

}
