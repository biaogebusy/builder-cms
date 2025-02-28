<?php

namespace Drupal\webform_views\WebformElementViews;

use Drupal\webform\Plugin\WebformElementInterface;

/**
 * Webform views handler for entity reference select webform elements.
 */
class WebformEntityReferenceSelectViews extends WebformEntityReferenceViews {

  /**
   * {@inheritdoc}
   */
  public function getElementViewsData(WebformElementInterface $element_plugin, array $element) {
    $views_data = parent::getElementViewsData($element_plugin, $element);

    $views_data['filter']['id'] = 'webform_submission_entity_reference_select_filter';

    return $views_data;
  }

}
