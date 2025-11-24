<?php

namespace Drupal\video_embed_field\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Url;

/**
 * Plugin implementation of the video field formatter.
 *
 * @FieldFormatter(
 *   id = "video_embed_field_video_url",
 *   label = @Translation("Video Url"),
 *   field_types = {
 *     "video_embed_field"
 *   }
 * )
 */
class VideoUrl extends Video {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    foreach ($items as $delta => $item) {
      $provider = $this->providerManager->loadProviderFromInput($item->value);

      if (!$provider) {
        $elements[$delta] = ['#theme' => 'video_embed_field_missing_provider'];
        continue;
      }
      // For Youtube playlists, link to the playlist page.
      if ($provider->getPluginId() == 'youtube_playlist') {
        $video_url = Url::fromUri(str_replace('watch', 'playlist', $item->value));
      }
      else {
        $video_url = Url::fromUri($item->value);
      }

      // Add the video url to the elements:
      $elements[$delta] = [
        '#markup' => $video_url->toString(),
        '#url' => $video_url,
      ];

    }
    return $elements;
  }

}
