<?php

namespace Drupal\field_validation\Plugin\FieldValidationRule;

use Drupal\Core\Form\FormStateInterface;
use Drupal\field_validation\ConfigurableFieldValidationRuleBase;
use Drupal\field_validation\FieldValidationRuleSetInterface;

/**
 * ItemCountFieldValidationRule.
 *
 * @FieldValidationRule(
 *   id = "item_count_field_validation_rule",
 *   label = @Translation("Min/max item count"),
 *   description = @Translation("Verifies the number of user-entered values in
 *   a multi value field, with the option to specify a minimum and/or maximum.")
 * )
 */
class ItemCountFieldValidationRule extends ConfigurableFieldValidationRuleBase {

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
      '#type'          => 'textfield',
      '#title'         => 'Minimum field values',
      '#default_value' => $this->configuration['min'],
      '#required'      => TRUE,
    ];
    $form['max'] = [
      '#type'          => 'textfield',
      '#title'         => 'Maximum field values',
      '#default_value' => $this->configuration['max'],
      '#required'      => FALSE,
    ];
    $form['tip'] = [
      '#type' => 'item',
      '#title' => $this->t('Tip'),
      '#markup' => $this->t( 'It is unusable with an unlimited value field, if you need this feature, you should set the #limit_validation_errors of add more button to empty array by custom code.'),
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
   * {@inheritdoc}
   */
  public function validate($params) {
    $rule = isset($params['rule']) ? $params['rule'] : NULL;
    $context = isset($params['context']) ? $params['context'] : NULL;
    $settings = [];

    if (!empty($rule) && !empty($rule->configuration)) {
      $settings = $rule->configuration;
    }

    $count = 0;
    foreach ($params['items'] as $item) {
      if (!$item->isEmpty()) {
        $count++;
      }
    }

    $min = intval($settings['min']);
    if ($count < $min) {
      $context->addViolation($rule->getReplacedErrorMessage($params));
    }

    $max = intval($settings['max']);
    if ($max > 0 && $count > $max) {
      $context->addViolation($rule->getReplacedErrorMessage($params));
    }

    return TRUE;
  }

}