<?php

namespace Drupal\bootstrap_styles\Element;

use Drupal\Core\Render\Element\RenderElementBase;

/**
 * Provides a video background render element.
 *
 * @RenderElementBase("bs_video_background")
 */
class VideoBackground extends RenderElementBase {

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
