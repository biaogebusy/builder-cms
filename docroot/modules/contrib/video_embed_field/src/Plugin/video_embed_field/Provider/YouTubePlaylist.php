<?php

namespace Drupal\video_embed_field\Plugin\video_embed_field\Provider;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Url;
use Drupal\video_embed_field\ProviderPluginBase;
use GuzzleHttp\ClientInterface;

/**
 * A YouTube playlist video provider.
 *
 * @VideoEmbedProvider(
 *   id = "youtube_playlist",
 *   title = @Translation("YouTube Playlist")
 * )
 */
class YouTubePlaylist extends ProviderPluginBase {

  /**
   * The ID of the first video to play.
   *
   * @var string
   */
  protected $firstVideoId;

  /**
   * The index of the first video to play.
   *
   * @var string
   */
  protected $firstVideoIndex;

  /**
   * {@inheritdoc}
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
    parent::__construct($configuration, $plugin_id, $plugin_definition, $http_client, $file_system, $config_factory, $cache, $logger_factory);
    $this->firstVideoId = $this->getFirstVideoIdFromInput($configuration['input']);
    $this->firstVideoIndex = $this->getFirstVideoIndexFromInput($configuration['input']);
  }

  /**
   * {@inheritdoc}
   */
  public function renderEmbedCode($width, $height, $autoplay, $title_format = NULL, $use_title_fallback = TRUE) {
    $embed_code = [
      '#type' => 'video_embed_iframe',
      '#provider' => 'youtube_playlist',
      '#url' => 'https://www.youtube.com/embed/videoseries',
      '#query' => [
        'list' => $this->getVideoId(),
      ],
      '#attributes' => [
        'width' => $width,
        'height' => $height,
        'frameborder' => '0',
        'allowfullscreen' => 'allowfullscreen',
      ],
    ];
    $title = $this->getName($title_format, $use_title_fallback);
    if (isset($title)) {
      $embed_code['#attributes']['title'] = $title;
    }
    if (!empty($this->getFirstVideoId())) {
      $embed_code['#query']['v'] = $this->getFirstVideoId();
    }
    if (!empty($this->getFirstVideoIndex())) {
      $embed_code['#query']['index'] = $this->getFirstVideoIndex();
    }
    return $embed_code;
  }

  /**
   * {@inheritdoc}
   */
  public function getRemoteThumbnailUrl() {
    return sprintf('http://img.youtube.com/vi/%s/hqdefault.jpg', static::getUrlComponent($this->getInput(), 'video_id'));
  }

  /**
   * {@inheritdoc}
   */
  public static function getIdFromInput($input) {
    return static::getUrlComponent($input, 'id');
  }

  /**
   * Get the ID of the first video to play from input.
   *
   * @return string
   *   The ID of the first video to play.
   */
  public static function getFirstVideoIdFromInput($input) {
    return static::getUrlComponent($input, 'video_id');
  }

  /**
   * Get the ID of the first video to play.
   *
   * @return string
   *   The ID of the first video to play.
   */
  protected function getFirstVideoId() {
    return $this->firstVideoId;
  }

  /**
   * Get the index of the first video to play from input.
   *
   * @return string
   *   The index of the first video to play.
   */
  public static function getFirstVideoIndexFromInput($input) {
    return static::getUrlComponent($input, 'index');
  }

  /**
   * Get the index of the first video to play.
   *
   * @return string
   *   The index of the first video to play.
   */
  protected function getFirstVideoIndex() {
    return $this->firstVideoIndex;
  }

  /**
   * Get a component from the URL.
   *
   * @param string $input
   *   The input URL.
   * @param string $component
   *   The component from the regex to get.
   *
   * @return string
   *   The value of the match in the regex.
   */
  protected static function getUrlComponent($input, $component) {
    preg_match('/^https?:\/\/(?:www\.)?youtube\.com\/(?:watch|playlist)\?(?=.*v=(?<video_id>[0-9A-Za-z_-]*))?(?=.*list=(?<id>[A-Za-z0-9_-]*)?)(?=.*index=(?<index>[\d]*))?/', $input, $matches);
    return $matches[$component] ?? FALSE;
  }

  /**
   * Get the youtube oembed data.
   *
   * @return array|null
   *   An object of data from the oembed endpoint or NULL if download failed..
   */
  protected function oEmbedData(): ?array {
    $normalized_uri = sprintf('https://www.youtube.com/playlist?list=%s', $this->videoId);
    $url = Url::fromUri('https://www.youtube.com/oembed', ['query' => ['url' => $normalized_uri]]);
    return $this->downloadJsonData($url->toString());
  }

  /**
   * {@inheritdoc}
   */
  public function getName($title_format = NULL, $use_title_fallback = TRUE) {
    return $this->formatTitle(
      $this->oEmbedData()['title'] ?? NULL,
      $title_format,
      $use_title_fallback
    );
  }

}
