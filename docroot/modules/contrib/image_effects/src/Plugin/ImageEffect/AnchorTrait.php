<?php

namespace Drupal\image_effects\Plugin\ImageEffect;

/**
 * Trait for anchor related functionalities.
 */
trait AnchorTrait {

  /**
   * Returns an array of options for anchoring an image.
   *
   * @return array
   *   The array of anchor options.
   */
  protected function anchorOptions() {
    return [
      'left-top' => $this->t('Top left'),
      'center-top' => $this->t('Top center'),
      'right-top' => $this->t('Top right'),
      'left-center' => $this->t('Center left'),
      'center-center' => $this->t('Center'),
      'right-center' => $this->t('Center right'),
      'left-bottom' => $this->t('Bottom left'),
      'center-bottom' => $this->t('Bottom center'),
      'right-bottom' => $this->t('Bottom right'),
    ];
  }

}
