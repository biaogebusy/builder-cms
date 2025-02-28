<?php

namespace Drupal\webform_views\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;

/**
 * Filter for "select or other" kind of webform elements.
 *
 * @ViewsFilter("webform_submission_select_other_filter")
 */
class WebformSubmissionSelectOtherFilter extends WebformSubmissionFieldFilter {

  /**
   * {@inheritdoc}
   */
  public function valueForm(&$form, FormStateInterface $form_state) {
    parent::valueForm($form, $form_state);

    if (!isset($form['value']['#empty_option'])) {
      $form['value']['#empty_option'] = $this->t('- Any -');
    }
  }

}
