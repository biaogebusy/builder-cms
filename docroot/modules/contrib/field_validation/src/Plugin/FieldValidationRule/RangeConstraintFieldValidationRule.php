<?php

namespace Drupal\field_validation\Plugin\FieldValidationRule;

use Drupal\Core\Form\FormStateInterface;
use Drupal\field_validation\ConstraintFieldValidationRuleBase;
use Drupal\field_validation\FieldValidationRuleSetInterface;

/**
 * Provides funcationality for EmailFieldValidationRule.
 *
 * @FieldValidationRule(
 *   id = "range_constraint_rule",
 *   label = @Translation("Range constraint"),
 *   description = @Translation("Range constraint.")
 * )
 */
class RangeConstraintFieldValidationRule extends ConstraintFieldValidationRuleBase {

  /**
   * {@inheritdoc}
   */
  public function getConstraintName(): string{
    return "Range";
  }

  /**
   * {@inheritdoc}
   */
  public function isPropertyConstraint(): bool{
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'min' => NULL,
      'max' => NULL,
      'maxMessage' => NULL,
      'minMessage' => NULL,
      'invalidMessage' => NULL,
      'notInRangeMessage' => NULL,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    //copied from core.
    $maxMessage = 'This value should be %limit or more.';
    $minMessage = 'This value should be %limit or less.';
    $invalidMessage = 'This value should be a valid number.';
    $notInRangeMessage = 'This value should be between %min and %max.';
	
    $form['min'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Min'),
      '#default_value' => $this->configuration['min'],
      '#required' => TRUE,
    ];
    $form['max'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Max'),
      '#default_value' => $this->configuration['max'],
      '#required' => TRUE,
    ];
    $form['maxMessage'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Max Message'),
      '#default_value' => $this->configuration['maxMessage'] ?? $maxMessage,
      '#maxlength' => 255,
    ];
    $form['minMessage'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Min Message'),
      '#default_value' => $this->configuration['minMessage'] ?? $minMessage,
      '#maxlength' => 255,	  
    ];
    $form['invalidMessage'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Invalid Message'),
      '#default_value' => $this->configuration['invalidMessage'] ?? $invalidMessage,
      '#maxlength' => 255,
    ];
    $form['notInRangeMessage'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Not In Range Message'),
      '#default_value' => $this->configuration['notInRangeMessage'] ?? $notInRangeMessage,
      '#maxlength' => 255,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    $this->configuration['min'] = $form_state->getValue('min');
    $this->configuration['max'] = $form_state->getValue('max');
    $this->configuration['maxMessage'] = $form_state->getValue('maxMessage');
    $this->configuration['minMessage'] = $form_state->getValue('minMessage');
    $this->configuration['invalidMessage'] = $form_state->getValue('invalidMessage');
    $this->configuration['notInRangeMessage'] = $form_state->getValue('notInRangeMessage');
  }

}
