<?php

namespace Drupal\video_embed_field\Plugin\video_embed_field\Provider;

use Drupal\Core\Url;
use Drupal\video_embed_field\ProviderPluginBase;

/**
 * A YouTube provider plugin.
 *
 * @VideoEmbedProvider(
 *   id = "youtube",
 *   title = @Translation("YouTube")
 * )
 */
class YouTube extends ProviderPluginBase {

  /**
   * {@inheritdoc}
   */
  public function renderEmbedCode($width, $height, $autoplay, $title_format = NULL, $use_title_fallback = TRUE) {
    $embed_code = [
      '#type' => 'video_embed_iframe',
      '#provider' => 'youtube',
      '#url' => sprintf('https://www.youtube.com/embed/%s', $this->getVideoId()),
      '#query' => [
        'autoplay' => $autoplay,
        'start' => $this->getTimeIndex(),
        'rel' => '0',
        // Video needs to be muted if autoplay is set.
        'mute' => $autoplay,
      ],
      '#attributes' => [
        'width' => $width,
        'height' => $height,
        'frameborder' => '0',
        'allowfullscreen' => 'allowfullscreen',
        'referrerpolicy' => 'strict-origin-when-cross-origin',
      ],
    ];
    $title = $this->getName($title_format, $use_title_fallback);
    if (isset($title)) {
      $embed_code['#attributes']['title'] = $title;
    }
    if ($language = $this->getLanguagePreference()) {
      $embed_code['#query']['cc_lang_pref'] = $language;
    }
    return $embed_code;
  }

  /**
   * Get the time index for when the given video starts.
   *
   * @return int
   *   The time index where the video should start based on the URL.
   */
  protected function getTimeIndex() {
    preg_match('/[&\?]t=((?<hours>\d+)h)?((?<minutes>\d+)m)?(?<seconds>\d+)s?/', $this->getInput(), $matches);

    $hours = !empty($matches['hours']) ? $matches['hours'] : 0;
    $minutes = !empty($matches['minutes']) ? $matches['minutes'] : 0;
    $seconds = !empty($matches['seconds']) ? $matches['seconds'] : 0;

    return $hours * 3600 + $minutes * 60 + $seconds;
  }

  /**
   * Extract the language preference from the URL for use in closed captioning.
   *
   * @return string|false
   *   The language preference if one exists or FALSE if one could not be found.
   */
  protected function getLanguagePreference() {
    preg_match('/[&\?]hl=(?<language>[a-z\-]*)/', $this->getInput(), $matches);
    return $matches['language'] ?? FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getRemoteThumbnailUrl() {
    $url = 'http://img.youtube.com/vi/%s/%s.jpg';
    $high_resolution = sprintf($url, $this->getVideoId(), 'maxresdefault');
    $backup = sprintf($url, $this->getVideoId(), 'mqdefault');
    try {
      $this->httpClient->head($high_resolution);
      return $high_resolution;
    }
    catch (\Exception $e) {
      return $backup;
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getIdFromInput($input) {
    preg_match('/^https?:\/\/(www\.)?((?!.*list=)youtube\.com\/(watch\?.*v=|live\/|embed\/|shorts\/)|youtu\.be\/)(?<id>[0-9A-Za-z_-]*)/', $input, $matches);
    return $matches['id'] ?? FALSE;
  }

  /**
   * Get the Youtube oembed data.
   *
   * @return array|null
   *   An array of data from the oembed endpoint or NULL if download failed.
   */
  protected function oEmbedData(): ?array {
    $normalized_url = sprintf('https://www.youtube.com/watch?v=%s', $this->videoId);
    $oembed_url = Url::fromUri('https://www.youtube.com/oembed', ['query' => ['url' => $normalized_url]]);
    return $this->downloadJsonData($oembed_url->toString());
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
