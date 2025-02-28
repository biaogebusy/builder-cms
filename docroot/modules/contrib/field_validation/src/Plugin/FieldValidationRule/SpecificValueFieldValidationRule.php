<?php

namespace Drupal\field_validation\Plugin\FieldValidationRule;

use Drupal\Core\Form\FormStateInterface;
use Drupal\field_validation\ConfigurableFieldValidationRuleBase;
use Drupal\field_validation\FieldValidationRuleSetInterface;

/**
 * Provides functionality for SpecificvalueFieldValidationRule.
 *
 * @FieldValidationRule(
 *   id = "specific_value_field_validation_rule",
 *   label = @Translation("Specific value(s)"),
 *   description = @Translation("Specific value(s).")
 * )
 */
class SpecificValueFieldValidationRule extends ConfigurableFieldValidationRuleBase {

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
      'setting' => "",
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['setting'] = [
      '#type' => 'textarea',
      '#title' => $this->t('(Key) value'),
      '#description' => $this->t('Specify the specific value(s) you want the field to contain. Separate multiple options by a comma. For fields that have keys, use the key value instead.'),
      '#default_value' => $this->configuration['setting'],
      '#required' => TRUE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    $this->configuration['setting'] = $form_state->getValue('setting') ?: "";
  }

  /**
   * Validate the field Specific value.
   */
  public function validate($params) {
    $value = $params['value'] ?? '';
    $rule = $params['rule'] ?? NULL;
    $context = $params['context'] ?? NULL;
    $settings = [];
    if (!empty($rule) && !empty($rule->configuration)) {
      $settings = $rule->configuration;
    }

    if ($value != '') {
      $flag = FALSE;
      $specific_values = explode(',', $settings['setting']);
      $specific_values = array_map('trim', $specific_values);

      if (in_array($value, $specific_values)) {
        $flag = TRUE;
      }

      if (!$flag) {
        $context->addViolation($rule->getReplacedErrorMessage($params));
      }
    }
  }

}
