<?php

namespace Drupal\field_validation\Plugin\FieldValidationRule;

use Drupal\Core\Form\FormStateInterface;
use Drupal\field_validation\ConfigurableFieldValidationRuleBase;
use Drupal\field_validation\FieldValidationRuleSetInterface;

/**
 * Provides the length field validation rule.
 *
 * @FieldValidationRule(
 *   id = "length_field_validation_rule",
 *   label = @Translation("length"),
 *   description = @Translation("Length.")
 * )
 */
class LengthFieldValidationRule extends ConfigurableFieldValidationRuleBase {

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
      'strip_tags' => FALSE,
      'trim' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
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
    $form['strip_tags'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Strip tags'),
      '#default_value' => $this->configuration['strip_tags'],
    ];
    $form['trim'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Trim'),
      '#default_value' => $this->configuration['trim'],
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
    $this->configuration['strip_tags'] = $form_state->getValue('strip_tags');
    $this->configuration['trim'] = $form_state->getValue('trim');
  }

  /**
   * Validate the length.
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
      $flag = TRUE;

      if (!empty($settings['strip_tags'])) {
        $value = strip_tags($value);
      }
      if (!empty($settings['trim'])) {
        $value = trim($value);
      }

      $length = mb_strlen($value, 'UTF-8');
      $token_data = $this->getTokenData($params);
      if (isset($settings['min']) && $settings['min'] != '') {
        $settings['min'] = $this->tokenService->replace($settings['min'], $token_data);
        $min = $settings['min'];
        if ($length < $min) {
          $flag = FALSE;
        }
      }
      if (isset($settings['max']) && $settings['max'] != '') {
        $settings['max'] = $this->tokenService->replace($settings['max'], $token_data);
        $max = $settings['max'];
        if ($length > $max) {
          $flag = FALSE;
        }
      }

      if (!$flag) {
        $context->addViolation($rule->getReplacedErrorMessage($params));
      }
    }
  }

}
