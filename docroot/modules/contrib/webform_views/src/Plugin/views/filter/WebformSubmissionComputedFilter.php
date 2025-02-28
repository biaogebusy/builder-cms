<?php

namespace Drupal\webform_views\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;

/**
 * Filter based on value of a computed webform element.
 *
 * @ViewsFilter("webform_submission_computed_filter")
 */
class WebformSubmissionComputedFilter extends WebformSubmissionFieldFilter {

  /**
   * {@inheritdoc}
   */
  public function valueForm(&$form, FormStateInterface $form_state) {
    parent::valueForm($form, $form_state);

    unset($form['value']['#value']);
  }

  /**
   * {@inheritdoc}
   */
  public function operators() {
    $operators = parent::operators();

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
