<?php

namespace Drupal\field_validation\Plugin\FieldValidationRule;

use Drupal\Core\Form\FormStateInterface;
use Drupal\field_validation\ConfigurableFieldValidationRuleBase;
use Drupal\field_validation\FieldValidationRuleSetInterface;

/**
 * IntegerFieldValidationRule.
 *
 * @FieldValidationRule(
 *   id = "integer_field_validation_rule",
 *   label = @Translation("Integer"),
 *   description = @Translation("Integer values.")
 * )
 */
class IntegerFieldValidationRule extends ConfigurableFieldValidationRuleBase {

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
      'min' => NULL,
      'max' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['min'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Minimum value'),
      '#default_value' => $this->configuration['min'],
      '#required' => TRUE,
    ];
    $form['max'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Maximum value'),
      '#default_value' => $this->configuration['max'],
      '#required' => TRUE,
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
  }

  /**
   *
   */
  public function validate($params) {
    $value = $params['value'] ?? '';
    $rule = $params['rule'] ?? NULL;
    $context = $params['context'] ?? NULL;
    $settings = [];
    if (!empty($rule) && !empty($rule->configuration)) {
      $settings = $rule->configuration;
    }

    if ($value !== '' && !is_null($value)) {
      $options = [];
      if (isset($settings['min']) && $settings['min'] != '') {
        $min = $settings['min'];
        $options['options']['min_range'] = $min;
      }
      if (isset($settings['max']) && $settings['max'] != '') {
        $max = $settings['max'];
        $options['options']['max_range'] = $max;
      }

      if (FALSE === filter_var($value, FILTER_VALIDATE_INT, $options)) {
        $context->addViolation($rule->getReplacedErrorMessage($params));
      }

    }
  }

}
