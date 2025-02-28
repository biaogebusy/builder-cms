<?php

namespace Drupal\webform_views\Plugin\views\filter;

/**
 * Filter based on value of a webform submission for 'hidden' element type.
 *
 * @ViewsFilter("webform_submission_hidden_filter")
 */
class WebformSubmissionHiddenFilter extends WebformSubmissionFieldFilter {

  /**
   * {@inheritdoc}
   */
  function operators() {
    $operators = parent::operators();

    // Replace all occurrences of "use the element type itself" (which would be
    // hidden and thus, simply pointless) with "textfield".
    foreach ($operators as $k => $v) {
      if (isset($operators[$k]['webform_views_element_type'])
        && $operators[$k]['webform_views_element_type'] == WebformSubmissionFieldFilter::ELEMENT_TYPE
      ) {
        $operators[$k]['webform_views_element_type'] = 'textfield';
      }
    }

    return $operators;
  }

}
