<?php

namespace Drupal\entity_print\Element;

use Drupal\Component\Utility\Html;
use Drupal\Core\Render\Element\Link;

/**
 * The print link.
 *
 * @RenderElement("print_link")
 */
class PrintLink extends Link {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $info = parent::getInfo();
    return [
      '#theme_wrappers' => [
        'container' => [
          '#attributes' => ['class' => ['print__wrapper']],
        ],
      ],
      '#attributes' => ['class' => ['print__link']],
    ] + $info;
  }

  /**
   * {@inheritdoc}
   */
  public static function preRenderLink($element) {
    if (isset($element['#export_type'])) {
      $element['#attributes']['class'][] = 'print__link--' . $element['#export_type'];
      $element['#theme_wrappers']['container']['#attributes']['class'][] = 'print__wrapper--' . Html::cleanCssIdentifier($element['#export_type']);
    }

    return parent::preRenderLink($element);
  }

}
