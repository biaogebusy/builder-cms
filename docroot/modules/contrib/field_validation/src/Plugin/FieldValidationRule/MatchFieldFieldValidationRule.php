<?php

namespace Drupal\field_validation\Plugin\FieldValidationRule;

use Drupal\Core\Form\FormStateInterface;
use Drupal\field_validation\ConfigurableFieldValidationRuleBase;
use Drupal\field_validation\FieldValidationRuleSetInterface;

/**
 * MatchFieldFieldValidationRule.
 *
 * @FieldValidationRule(
 *   id = "match_field_field_validation_rule",
 *   label = @Translation("Match against a field"),
 *   description = @Translation("Validate that user-entered data matches against a field, for example must match user's realname.")
 * )
 */
class MatchFieldFieldValidationRule extends ConfigurableFieldValidationRuleBase {

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
      'entity_type' => "",
      'bundle' => "",
      'field_name' => "",
      'column' => "",
      'reverse' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['entity_type'] = [
      '#title' => $this->t('Entity type'),
      '#description' => $this->t('Machine name. Entity type of the field that to be matched against.'),
      '#type' => 'textfield',
      '#default_value' => $this->configuration['entity_type'],
      '#required' => TRUE,
    ];

    $form['bundle'] = [
      '#title' => $this->t('Bundle'),
      '#description' => $this->t('Machine name. Bundle of the field that to be matched against.'),
      '#type' => 'textfield',
      '#default_value' => $this->configuration['bundle'],
      '#required' => TRUE,
    ];

    $form['field_name'] = [
      '#title' => $this->t('Field name'),
      '#description' => $this->t('Machine name. Name of the field that to be matched against.'),
      '#type' => 'textfield',
      '#default_value' => $this->configuration['field_name'],
      '#required' => TRUE,
    ];

    $form['column'] = [
      '#title' => $this->t('Column'),
      '#description' => $this->t('Column of the field that to be matched against.'),
      '#type' => 'textfield',
      '#default_value' => $this->configuration['column'],
    ];

    $form['reverse'] = [
      '#title' => $this->t('Reverse'),
      '#description' => $this->t('If it is checked, it means must not match the field.'),
      '#type' => 'checkbox',
      '#default_value' => $this->configuration['reverse'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    $this->configuration['entity_type'] = $form_state->getValue('entity_type');
    $this->configuration['bundle'] = $form_state->getValue('bundle');
    $this->configuration['field_name'] = $form_state->getValue('field_name');
    $this->configuration['column'] = $form_state->getValue('column');
    $this->configuration['reverse'] = $form_state->getValue('reverse') ?: FALSE;
  }

  /**
   *
   */
  public function validate($params) {
    $value = $params['value'] ?? '';
    $rule = $params['rule'] ?? NULL;
    $context = $params['context'] ?? NULL;
    $column = $rule->getColumn();

    $settings = [];
    if (!empty($rule) && !empty($rule->configuration)) {
      $settings = $rule->configuration;
    }

    $entity_type = $settings['entity_type'] ?? '';
    $bundle = $settings['bundle'] ?? '';
    $field_name = $settings['field_name'] ?? '';
    $column = $settings['column'] ?? '';
    $reverse = $settings['reverse'] ?? FALSE;
    if (empty($entity_type) || empty($bundle) || empty($field_name)) {
      return;
    }

    $count = 0;
    $flag = TRUE;

    $query = \Drupal::entityQuery($entity_type);
    $query->addTag('field_validation');
    $query->accessCheck(FALSE);
    // Add bundle condition.
    $entity_type_plugin = \Drupal::entityTypeManager()->getDefinition($entity_type, FALSE);
    $bundle_key = $entity_type_plugin->getKey('bundle');
    if (!empty($bundle_key)) {
      $query->condition($bundle_key, $bundle);
    }
    // Support column if not empty.
    if (!empty($column)) {
      $field_name = $field_name . "." . $column;
    }
    // Add field condition.
    $query->condition($field_name, $value);
    $count = $query->range(0, 1)
      ->count()
      ->execute();

    if (!$count) {
      $flag = FALSE;
    }

    // Reverse.
    if ($reverse) {
      $flag = $flag ? FALSE : TRUE;
    }

    if (!$flag) {
      $context->addViolation($rule->getReplacedErrorMessage($params));
    }
  }

}
