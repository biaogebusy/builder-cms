<?php

namespace Drupal\video_embed_field\Plugin\video_embed_field\Provider;

use Drupal\Core\Url;
use Drupal\video_embed_field\ProviderPluginBase;

/**
 * A Vimeo provider plugin.
 *
 * @VideoEmbedProvider(
 *   id = "vimeo",
 *   title = @Translation("Vimeo")
 * )
 */
class Vimeo extends ProviderPluginBase {

  /**
   * {@inheritdoc}
   */
  public function renderEmbedCode($width, $height, $autoplay, $title_format = NULL, $use_title_fallback = TRUE) {
    $iframe = [
      '#type' => 'video_embed_iframe',
      '#provider' => 'vimeo',
      '#url' => sprintf('https://player.vimeo.com/video/%s', $this->getVideoId()),
      '#query' => [
        'autoplay' => $autoplay,
        // Video needs to be muted if autoplay is set.
        'muted' => $autoplay,
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
      $iframe['#attributes']['title'] = $title;
    }
    if ($time_index = $this->getTimeIndex()) {
      $iframe['#fragment'] = sprintf('t=%s', $time_index);
    }
    return $iframe;
  }

  /**
   * {@inheritdoc}
   */
  public function getRemoteThumbnailUrl() {
    return $this->oEmbedData()['thumbnail_url'] ?? '';
  }

  /**
   * Get the vimeo oembed data.
   *
   * @return array|null
   *   An array of data from the oembed endpoint.
   */
  protected function oEmbedData(): ?array {
    $normalized_url = sprintf('https://vimeo.com/%s', $this->videoId);
    $url = Url::fromUri('https://vimeo.com/api/oembed.json', ['query' => ['url' => $normalized_url]]);
    return $this->downloadJsonData($url->toString());
  }

  /**
   * {@inheritdoc}
   */
  public static function getIdFromInput($input) {
    preg_match('/^https?:\/\/(www\.|player\.)?vimeo\.com\/(video\/)?(channels\/[a-zA-Z0-9]*\/)?(progressive_redirect\/playback\/)?(?<id>[0-9]*)(\/[a-zA-Z0-9]+)?(\#t=(\d+)s)?$/', $input, $matches);
    return $matches['id'] ?? FALSE;
  }

  /**
   * Get the time index from the URL.
   *
   * @return string|false
   *   A time index parameter to pass to the frame or FALSE if none is found.
   */
  protected function getTimeIndex() {
    preg_match('/\#t=(?<time_index>(\d+)s)$/', $this->input, $matches);
    return $matches['time_index'] ?? FALSE;
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
