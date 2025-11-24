<?php

namespace Drupal\field_validation\Plugin\FieldValidationRule;

use Drupal\Core\Form\FormStateInterface;
use Drupal\field_validation\ConstraintFieldValidationRuleBase;
use Drupal\field_validation\FieldValidationRuleSetInterface;

/**
 * Provides funcationality for LessThanOrEqualConstraintFieldValidationRule.
 *
 * @FieldValidationRule(
 *   id = "less_than_or_equal_constraint_rule",
 *   label = @Translation("LessThanOrEqual constraint"),
 *   description = @Translation("LessThanOrEqual constraint.")
 * )
 */
class LessThanOrEqualConstraintFieldValidationRule extends ConstraintFieldValidationRuleBase {

  /**
   * {@inheritdoc}
   */
  public function getConstraintName(): string{
    return "LessThanOrEqual";
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
      'value' => NULL,
      'message' => NULL,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    //copied from core.
    $message = 'This value should be less than or equal to %compared_value.';

    $form['value'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Value'),
      '#default_value' => $this->configuration['value'],
      '#required' => TRUE,
    ];

    $form['message'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Message'),
      '#default_value' => $this->configuration['message'] ?? $message,
      '#maxlength' => 255,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    $this->configuration['value'] = $form_state->getValue('value');
    $this->configuration['message'] = $form_state->getValue('message');
  }

  /**
   * {@inheritdoc}
   */
  public function getReplacedConstraintOptions(array $params): array {
    $constraintOptions = $this->getConstraintOptions();

    $data = $this->getTokenData($params);
    if (empty($data)) {
      return $constraintOptions;
    }

    $constraintOptions['value'] = $this->tokenService->replace($constraintOptions['value'], $data);
    return $constraintOptions;
  }

}
