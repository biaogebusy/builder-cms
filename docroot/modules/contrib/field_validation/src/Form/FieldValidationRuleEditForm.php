<?php

namespace Drupal\field_validation\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\field_validation\FieldValidationRuleSetInterface;

/**
 * Provides an edit form for fieldValidationRule.
 */
class FieldValidationRuleEditForm extends FieldValidationRuleFormBase {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, FieldValidationRuleSetInterface $field_validation_rule_set = NULL, $field_validation_rule = NULL, $field_name = '') {
    $form = parent::buildForm($form, $form_state, $field_validation_rule_set, $field_validation_rule);

    $form['#title'] = $this->t('Edit %label rule', ['%label' => $this->fieldValidationRule->label()]);
    $form['actions']['submit']['#value'] = $this->t('Update rule');

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareFieldValidationRule($field_validation_rule) {
    return $this->fieldValidationRuleSet->getFieldValidationRule($field_validation_rule);
  }

}
