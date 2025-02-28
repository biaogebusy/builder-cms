<?php

namespace Drupal\webform_views\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;

/**
 * Filter for term checkboxes webform element.
 *
 * @ViewsFilter("webform_submission_term_checkboxes_filter")
 */
class WebformSubmissionTermCheckboxesFilter extends WebformSubmissionFieldFilter {

  /**
   * {@inheritdoc}
   */
  public function valueForm(&$form, FormStateInterface $form_state) {
    parent::valueForm($form, $form_state);
    $form['value']['#attached']['library'][] = 'webform_views/filter.webform_term_checkboxes';
  }

  /**
   * {@inheritdoc}
   */
  public function opEqual($field) {
    $value = array_values(array_filter($this->value));
    if (empty($value)) {
      return;
    }
    $this->ensureMyTable();

    $this->query->addWhere($this->options['group'], "$this->tableAlias.$this->realField", $value, $this->operator == '=' ? 'IN' : 'NOT IN');
  }

}
