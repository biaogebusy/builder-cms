<?php

namespace Drupal\field_validation;

use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a base class for Constraint FieldValidationRuleBase.
 *
 * @see plugin_api
 */
abstract class ConstraintFieldValidationRuleBase extends ConfigurableFieldValidationRuleBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'validate_mode' => "default",
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
	$validateModes = [
      'default' => "Default",
      'direct' => "Direct",
    ];
    $form['validate_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Validate mode'),
      '#description' => $this->t('Default: Delegate it to field validation; Direct: Append the constraint to field directly, it will bypass the condition check.'),
      '#options' => $validateModes,
      '#default_value' => $this->configuration['validate_mode'] ?? "default",
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    $this->configuration['validate_mode'] = $form_state->getValue('validate_mode');
  }

  /**
   * Get the constraint options.
   *
   * @return array
   */
  public function getConstraintOptions(): array {
    $constraintOptions = $this->configuration;
    unset($constraintOptions['validate_mode']);
    $constraintOptions = array_filter($constraintOptions);
    return $constraintOptions;
  }

  /**
   * Get the constraint options which replaced with token.
   *
   * @return array
   */
  public function getReplacedConstraintOptions(array $params): array {
    $constraintOptions = $this->getConstraintOptions();

    return $constraintOptions;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValidationRule(FieldValidationRuleSetInterface $field_validation_rule_set) {

    return TRUE;
  }

  /**
   * Get the constraint name.
   *
   * @return string
   */
  public abstract function getConstraintName(): string;

  /**
   * Check if it is a property constraint.
   *
   * @return bool
   */
  public abstract function isPropertyConstraint(): bool;

}
