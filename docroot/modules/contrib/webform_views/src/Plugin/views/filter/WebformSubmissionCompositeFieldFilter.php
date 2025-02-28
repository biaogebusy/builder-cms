<?php

namespace Drupal\webform_views\Plugin\views\filter;

/**
 * Filter based on value of a composite of a webform submission.
 *
 * @ViewsFilter("webform_submission_composite_field_filter")
 */
class WebformSubmissionCompositeFieldFilter extends WebformSubmissionFieldFilter {

  protected function getWebformElement() {
    $element = parent::getWebformElement();

    // Nest into the sub-element.
    $element = $element['#webform_composite_elements'][$this->definition['webform_submission_property']];
    return $element;
  }

}
