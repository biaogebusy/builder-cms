<?php

namespace Drupal\webform\Element;

use Drupal\Core\Render\Element\RenderElementBase;

/**
 * Provides a render element for more.
 *
 * @FormElement("webform_more")
 */
class WebformMore extends RenderElementBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#theme' => 'webform_element_more',
      '#more' => '',
      '#more_title' => '',
      '#attributes' => [],
    ];
  }

}
