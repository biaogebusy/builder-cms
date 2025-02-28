<?php

namespace Drupal\bootstrap_layout_builder\Element;

use Drupal\Core\Render\Element\RenderElement;

/**
 * Provides a container wrapper element.
 *
 * @RenderElement("blb_container")
 */
class Container extends RenderElement {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#theme' => 'blb_container',
      '#attributes' => [],
      '#children' => [],
    ];
  }

}
