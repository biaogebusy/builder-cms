<?php

namespace Drupal\webform_views\WebformElementViews;

use Drupal\webform\Plugin\WebformElementInterface;

/**
 * Webform views handler for computed webform elements.
 */
class WebformComputed extends WebformElementViewsAbstract {

  /**
   * {@inheritdoc}
   */
  public function getElementViewsData(WebformElementInterface $element_plugin, array $element) {
    $views_data = parent::getElementViewsData($element_plugin, $element);

    if (isset($element['#store']) && $element['#store']) {
      $views_data['filter'] = [
        'id' => 'webform_submission_computed_filter',
        'real field' => 'value',
      ];
    }

    return $views_data;
  }

}
