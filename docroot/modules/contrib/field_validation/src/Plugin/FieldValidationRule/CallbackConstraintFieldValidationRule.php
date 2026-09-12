<?php

namespace Drupal\field_validation\Plugin\FieldValidationRule;

use Drupal\Core\Form\FormStateInterface;
use Drupal\field_validation\ConstraintFieldValidationRuleBase;
use Drupal\field_validation\FieldValidationRuleSetInterface;

/**
 * Provides funcationality for CallbackConstraintFieldValidationRule.
 *
 * @FieldValidationRule(
 *   id = "callback_constraint_rule",
 *   label = @Translation("Callback constraint"),
 *   description = @Translation("Callback constraint.")
 * )
 */
class CallbackConstraintFieldValidationRule extends ConstraintFieldValidationRuleBase {

  /**
   * {@inheritdoc}
   */
  public function getConstraintName(): string{
    return "Callback";
  }

  /**
   * {@inheritdoc}
   */
  public function isPropertyConstraint(): bool{
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'value' => NULL,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['value'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Callback'),
      '#description' => $this->t('A static callable in the form "Class::method". This will be invoked directly, so only enter callbacks you trust; anyone who can edit this rule effectively gains the ability to execute the referenced PHP code.'),
      '#default_value' => $this->configuration['value'],
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    $this->configuration['value'] = $form_state->getValue('value');
  }

  /**
   * {@inheritdoc}
   */
  public function getConstraintOptions(): array {
    $constraintOptions = [];

    // Symfony's Callback constraint constructor parameter is named
    // $callback, not $value. Drupal's ConstraintFactory spreads this array
    // as named arguments, so a 'value' key here fatals with "Unknown named
    // parameter $value" instead of ever reaching the callback comparison.
    $constraintOptions['callback'] = explode("::", $this->configuration['value']);
    return $constraintOptions;
  }

  /**
   * {@inheritdoc}
   */
  public function getReplacedConstraintOptions(array $params): array {
    $constraintOptions = $this->getConstraintOptions();

    return $constraintOptions;
  }

}
