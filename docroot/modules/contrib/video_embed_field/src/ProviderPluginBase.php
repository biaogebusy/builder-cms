<?php

namespace Drupal\video_embed_field;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A base for the provider plugins.
 */
abstract class ProviderPluginBase extends PluginBase implements ProviderPluginInterface, ContainerFactoryPluginInterface {

  /**
   * The directory where thumbnails are stored.
   *
   * @var string
   */
  protected $thumbsDirectory;

  /**
   * The ID of the video.
   *
   * @var string
   */
  protected $videoId;

  /**
   * The input that caused the embed provider to be selected.
   *
   * @var string
   */
  protected $input;

  /**
   * An http client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * A cache backend interface.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Create a plugin with the given input.
   *
   * @param array $configuration
   *   The configuration of the plugin.
   * @param string $plugin_id
   *   The plugin id.
   * @param array $plugin_definition
   *   The plugin definition.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   An HTTP client.
   * @param \Drupal\Core\File\FileSystemInterface|null $file_system
   *   The file system service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface|null $config_factory
   *   The system file configuration.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   A cache backend interface.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   *
   * @throws \Exception
   */
  public function __construct(
    $configuration,
    $plugin_id,
    $plugin_definition,
    ClientInterface $http_client,
    ?FileSystemInterface $file_system = NULL,
    ?ConfigFactoryInterface $config_factory = NULL,
    ?CacheBackendInterface $cache = NULL,
    ?LoggerChannelFactoryInterface $logger_factory = NULL,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    if (!static::isApplicable($configuration['input'])) {
      throw new \Exception('Tried to create a video provider plugin with invalid input.');
    }
    $this->input = $configuration['input'];
    $this->videoId = $this->getIdFromInput($configuration['input']);
    $this->httpClient = $http_client;
    $this->fileSystem = $file_system ?: self::getDrupalFileSystem();
    $this->configFactory = $config_factory ?: self::getConfigFactory();
    $this->cache = $cache ?: self::getCacheBackend();
    $this->loggerFactory = $logger_factory ?: self::getLoggerFactory();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    // @phpstan-ignore-next-line
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('http_client'),
      $container->get('file_system'),
      $container->get('config.factory'),
      $container->get('cache.default'),
      $container->get('logger.factory'),
    );
  }

  /**
   * Returns Drupal file_system service for backward compatibility.
   *
   * @return \Drupal\Core\File\FileSystemInterface
   *   The file system service.
   */
  private static function getDrupalFileSystem() {
    return \Drupal::service('file_system');
  }

  /**
   * Returns Drupal config factory for backward compatibility.
   *
   * @return \Drupal\Core\Config\ConfigFactoryInterface
   *   The system file configuration.
   */
  private static function getConfigFactory() {
    return \Drupal::service('config.factory');
  }

  /**
   * Returns Drupal default cache backend for backward compatibility.
   *
   * @return \Drupal\Core\Cache\CacheBackendInterface
   *   The cache backend service.
   */
  private static function getCacheBackend() {
    return \Drupal::service('cache.default');
  }

  /**
   * Returns Drupal logger factory service for backward compatibility.
   *
   * @return \Drupal\Core\Logger\LoggerChannelFactoryInterface
   *   The logger factory.
   */
  private static function getLoggerFactory() {
    return \Drupal::service('logger.factory');
  }

  /**
   * Get the ID of the video.
   *
   * @return string
   *   The video ID.
   */
  protected function getVideoId() {
    return $this->videoId;
  }

  /**
   * Get the file system service.
   *
   * @return \Drupal\Core\File\FileSystemInterface
   *   The file system service.
   */
  protected function getFileSystem() {
    return $this->fileSystem;
  }

  /**
   * Get the input which caused this plugin to be selected.
   *
   * @return string
   *   The raw input from the user.
   */
  protected function getInput() {
    return $this->input;
  }

  /**
   * Download JSON data from the site (e.g., oEmbed data).
   *
   * @param string $url
   *   The URL to fetch the data from.
   *
   * @return array|null
   *   The JSON data as an object or NULL if the JSON could not be decoded.
   */
  protected function downloadJsonData($url): ?array {
    $cid = 'video_embed_field:' . md5($url);
    if ($cache = $this->cache->get($cid)) {
      return $cache->data;
    }

    $method = 'GET';
    try {
      $response = $this->httpClient->request($method, $url);
      $code = $response->getStatusCode();
      if ($code == 200) {
        $body = $response->getBody()->getContents();
        $data = Json::decode($body);

        // Check if the JSON was valid.
        if (json_last_error() === JSON_ERROR_NONE) {
          $this->cache->set($cid, $data);
          return $data;
        }
      }
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('video_embed_field')->warning('There was an error downloading metadata. Message: @message.', ['@message' => $e->getMessage()]);
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable($input) {
    $id = static::getIdFromInput($input);
    return !empty($id);
  }

  /**
   * Get the thumbnails local storage directory uri.
   *
   * @return string
   *   The thumbnails storage directory uri.
   */
  protected function getThumbsDirectory() {
    if (!isset($this->thumbsDirectory)) {
      $config_system_file = $this->configFactory->get('system.file');
      $default_scheme = $config_system_file->get('default_scheme');
      $this->thumbsDirectory = $default_scheme . '://video_thumbnails';
    }
    return $this->thumbsDirectory;
  }

  /**
   * {@inheritdoc}
   */
  public function renderThumbnail($image_style, $link_url) {
    $output = [
      '#theme' => 'image',
      '#uri' => $this->getLocalThumbnailUri(),
    ];

    if (!empty($image_style)) {
      $output['#theme'] = 'image_style';
      $output['#style_name'] = $image_style;
    }

    if ($link_url) {
      $output = [
        '#type' => 'link',
        '#title' => $output,
        '#url' => $link_url,
      ];
    }
    return $output;
  }

  /**
   * {@inheritdoc}
   */
  public function downloadThumbnail() {
    $file_system = $this->getFileSystem();
    if ($file_system) {
      $local_uri = $this->getLocalThumbnailUri();
      $thumbs_directory = $this->getThumbsDirectory();
      if (!file_exists($local_uri)) {
        $file_system->prepareDirectory(
          $thumbs_directory, FileSystemInterface::CREATE_DIRECTORY);
        try {
          $thumbnail = $this->httpClient->request('GET', $this->getRemoteThumbnailUrl());
          $file_system->saveData((string) $thumbnail->getBody(), $local_uri);
        }
        catch (\Exception $e) {
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getLocalThumbnailUri() {
    return $this->getThumbsDirectory() . '/' . $this->getVideoId() . '.jpg';
  }

  /**
   * {@inheritdoc}
   */
  public function getName($title_format = NULL, $use_title_fallback = TRUE) {
    return $this->formatTitle($this->getVideoId(), $title_format, $use_title_fallback);
  }

  /**
   * Format a video title.
   *
   * @param string|null $title
   *   The title of the video; could be empty.
   * @param string|null $title_format
   *   The format to use for the title; could contain the tokens `@title` and
   *   `@provider`.
   * @param bool $use_title_fallback
   *   Whether to use a fallback for the title or not. Defaults to TRUE.
   *
   * @return \Drupal\Component\Render\FormattableMarkup|null
   *   The formatted title, or NULL if the title was empty and
   *   $use_title_fallback was TRUE, or no title format was given.
   */
  protected function formatTitle($title, $title_format, $use_title_fallback = TRUE) {
    if (!isset($title) || !strlen($title) || !isset($title_format)) {
      if ($use_title_fallback) {
        $title = $this->getVideoId();
      }
    }

    if (isset($title)) {
      return new FormattableMarkup($title_format, [
        '@provider' => $this->getPluginDefinition()['title'],
        '@title' => $title,
      ]);
    }
    else {
      return NULL;
    }
  }

}
