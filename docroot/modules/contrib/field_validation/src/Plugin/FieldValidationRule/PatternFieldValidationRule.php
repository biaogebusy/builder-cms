<?php

namespace Drupal\field_validation\Plugin\FieldValidationRule;

use Drupal\Core\Form\FormStateInterface;
use Drupal\field_validation\ConfigurableFieldValidationRuleBase;
use Drupal\field_validation\FieldValidationRuleSetInterface;

/**
 * Provides functionality for PatternFieldValidationRule.
 *
 * @FieldValidationRule(
 *   id = "pattern_field_validation_rule",
 *   label = @Translation("Pattern"),
 *   description = @Translation("Pattern(regex lite).")
 * )
 */
class PatternFieldValidationRule extends ConfigurableFieldValidationRuleBase {

  /**
   * {@inheritdoc}
   */
  public function addFieldValidationRule(FieldValidationRuleSetInterface $field_validation_rule_set) {

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    $summary = parent::getSummary();

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'pattern' => "",
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['pattern'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Pattern'),
      '#description' => $this->t('Specify a pattern where: a - Represents an alpha character [a-zA-Z]; 9 - Represents a numeric character [0-9]; # - Represents an alphanumeric character [a-zA-Z0-9]. Example: aaa-999-999.'),
      '#default_value' => $this->configuration['pattern'],
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    $this->configuration['pattern'] = $form_state->getValue('pattern');
  }

  /**
   * Validate the pattern.
   */
  public function validate($params) {
    $value = $params['value'] ?? '';
    $rule = $params['rule'] ?? NULL;
    $context = $params['context'] ?? NULL;
    $settings = [];
    if (!empty($rule) && !empty($rule->configuration)) {
      $settings = $rule->configuration;
    }
    $pattern = $settings['pattern'] ?? '';
    // Escape regex control characters.
    $pattern = preg_quote($pattern, "/");
    $pattern = preg_replace('/a/', '[a-zA-Z]', $pattern);
    $pattern = preg_replace('/9/', '[0-9]', $pattern);
    $pattern = preg_replace('/#/', '[a-zA-Z0-9]', $pattern);
    if ($value != '' && (!preg_match('/^(' . $pattern . ')$/', $value))) {
      $context->addViolation($rule->getReplacedErrorMessage($params));
    }
  }

}
