<?php

namespace Drupal\bootstrap_styles\Element;

use Drupal\Core\Render\Element\RenderElement;

/**
 * Provides a video background render element.
 *
 * @RenderElement("bs_video_background")
 */
class VideoBackground extends RenderElement {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#theme' => 'bs_video_background',
      '#attributes' => [],
      '#video_background_url' => '',
      '#children' => [],
    ];
  }

}
