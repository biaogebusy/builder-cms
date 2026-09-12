<?php

namespace Drupal\webform\Element;

use Drupal\Core\Render\Element\RenderElementBase;

/**
 * Provides a render element for horizontal rule.
 *
 * @FormElement("webform_horizontal_rule")
 */
class WebformHorizontalRule extends RenderElementBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#theme' => 'webform_horizontal_rule',
    ];
  }

}
