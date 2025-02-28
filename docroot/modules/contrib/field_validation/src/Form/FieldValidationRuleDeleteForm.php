<?php

namespace Drupal\field_validation\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\field_validation\FieldValidationRuleSetInterface;

/**
 * Form for deleting a fieldValidationRule.
 */
class FieldValidationRuleDeleteForm extends ConfirmFormBase {

  /**
   * The fieldValidationRuleSet containing the fieldValidationRule to be deleted.
   *
   * @var \Drupal\field_validation\FieldValidationRuleSetInterface
   */
  protected $fieldValidationRuleSet;

  /**
   * The fieldValidationRule to be deleted.
   *
   * @var \Drupal\field_validation\FieldValidationRuleInterface
   */
  protected $fieldValidationRule;

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete the @rule rule from the %ruleset fieldValidationRuleSet?', ['%ruleset' => $this->fieldValidationRuleSet->label(), '@rule' => $this->fieldValidationRule->label()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return $this->fieldValidationRuleSet->toUrl('edit-form');
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'field_validation_rule_delete_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, FieldValidationRuleSetInterface $field_validation_rule_set = NULL, $field_validation_rule = NULL) {
    $this->fieldValidationRuleSet = $field_validation_rule_set;
    $this->fieldValidationRule = $this->fieldValidationRuleSet->getFieldValidationRule($field_validation_rule);

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->fieldValidationRuleSet->deleteFieldValidationRule($this->fieldValidationRule);
    $this->messenger()->addMessage($this->t('The rule %name has been deleted.', ['%name' => $this->fieldValidationRule->label()]));
    $form_state->setRedirectUrl($this->fieldValidationRuleSet->toUrl('edit-form'));
  }

}
