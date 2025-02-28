<?php

namespace Drupal\field_validation\Plugin\FieldValidationRule;

use Drupal\Core\Form\FormStateInterface;
use Drupal\field_validation\ConfigurableFieldValidationRuleBase;
use Drupal\field_validation\FieldValidationRuleSetInterface;

/**
 * Provides the numeric field validation rule.
 *
 * @FieldValidationRule(
 *   id = "numeric_field_validation_rule",
 *   label = @Translation("Numeric"),
 *   description = @Translation("Verifies that user-entered values are numeric, with the option to specify min/max/step.")
 * )
 */
class NumericFieldValidationRule extends ConfigurableFieldValidationRuleBase {

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
      'step' => NULL,
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
    $form['step'] = [
      '#title' => $this->t('Step'),
      '#description' => $this->t('The step scale factor. Must be positive.'),
      '#type' => 'textfield',
      '#default_value' => $this->configuration['step'],
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
    $this->configuration['step'] = $form_state->getValue('step');
  }

  /**
   * Validate the field.
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
      $flag = TRUE;
      if (!is_numeric($value)) {
        $flag = FALSE;
      }
      else {
        $token_data = $this->getTokenData($params);
        if (isset($settings['min']) && $settings['min'] != '') {
          $settings['min'] = $this->tokenService->replace($settings['min'], $token_data);
          $min = $settings['min'];
          if ($value < $min) {
            $flag = FALSE;
          }
        }
        if (isset($settings['max']) && $settings['max'] != '') {
          $settings['max'] = $this->tokenService->replace($settings['max'], $token_data);
          $max = $settings['max'];
          if ($value > $max) {
            $flag = FALSE;
          }
        }
        if (isset($settings['step']) && strtolower($settings['step']) != 'any') {
          // Check that the input is an allowed multiple
          // of #step (offset by #min if #min is set).
          $offset = $settings['min'] ?? 0.0;
          $settings['step'] = $this->tokenService->replace($settings['step'], $token_data);
          $step = $settings['step'];
          // The logic code was copied from Drupal 8 core.
          if ($step > 0 && !$this->valid_number_step($value, $step, $offset)) {
            $flag = FALSE;
          }
        }
      }
      if (!$flag) {
        $context->addViolation($rule->getReplacedErrorMessage($params));
      }
    }
  }

  /**
   * Verifies that a number is a multiple of a given step.
   *
   * The implementation assumes it is dealing with IEEE 754 double precision
   * floating point numbers that are used by PHP on most systems.
   *
   * This is based on the number/range verification methods of webkit.
   *
   * @param $value
   *   The value that needs to be checked.
   * @param $step
   *   The step scale factor. Must be positive.
   * @param $offset
   *   (optional) An offset, to which the difference must be a multiple of the
   *   given step.
   *
   * @return bool
   *   TRUE if no step mismatch has occured, or FALSE otherwise.
   *
   * @see http://opensource.apple.com/source/WebCore/WebCore-1298/html/NumberInputType.cpp
   */
  public function valid_number_step($value, $step, $offset = 0.0) {
    $double_value = (double) abs($value - $offset);

    // The fractional part of a double has 53 bits.
    // The greatest number that could
    // be represented with that is 2^53. If the given
    // value is even bigger than
    // $step * 2^53, then dividing by $step will result
    // in a very small remainder.
    // Since that remainder can't even be represented
    // with a single precision
    // float the following computation of the remainder
    // makes no sense and we can
    // safely ignore it instead.
    if ($double_value / pow(2.0, 53) > $step) {
      return TRUE;
    }

    // Now compute that remainder of a division by $step.
    $remainder = (double) abs($double_value - $step * round($double_value / $step));

    // $remainder is a double precision floating point number.
    // Remainders that can't be represented with single precision
    // floats are acceptable. The fractional part of a float has 24 bits.
    // That means remainders smaller than
    // $step * 2^-24 are acceptable.
    $computed_acceptable_error = (double) ($step / pow(2.0, 24));

    return $computed_acceptable_error >= $remainder || $remainder >= ($step - $computed_acceptable_error);
  }

}
