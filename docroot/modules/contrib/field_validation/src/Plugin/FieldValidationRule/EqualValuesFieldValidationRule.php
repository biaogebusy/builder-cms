<?php

namespace Drupal\field_validation\Plugin\FieldValidationRule;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\field_validation\ConfigurableFieldValidationRuleBase;
use Drupal\field_validation\FieldValidationRuleSetInterface;

/**
 * Provides functionality for EqualValuesFieldValidationRule.
 *
 * @FieldValidationRule(
 *   id = "equal_values_field_validation_rule",
 *   label = @Translation("Equal values on multiple fields"),
 *   description = @Translation("Verifies that all specified fields contain equal values.")
 * )
 */
class EqualValuesFieldValidationRule extends ConfigurableFieldValidationRuleBase {

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
    $summary += parent::getSummary();

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'data' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['data'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Group name'),
      '#description' => $this->t('Specify the group name for those fields, it should be the same across those fields. Validation rules with the same group name work together.'),
      '#default_value' => $this->configuration['data'] ?? '',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    $this->configuration['data'] = $form_state->getValue('data');
  }

  /**
   * {@inheritdoc}
   */
  public function validate($params) {
    $flag = TRUE;
    $items = $params['items'] ?? [];

    /** @var \Drupal\field_validation\ConfigurableFieldValidationRuleInterface $rule */
    $rule = $params['rule'] ?? NULL;
    /** @var \Drupal\field_validation\Entity\FieldValidationRuleSet $ruleset */
    $ruleset = $params['ruleset'] ?? NULL;
    /** @var \Drupal\Core\TypedData\Validation\ExecutionContext $context */
    $context = $params['context'];

    /** @var \Drupal\node\Entity\Node $object */
    $entity = $context->getRoot()->getValue();

    $group_name = $rule->configuration['data'];
    /** @var \Drupal\field_validation\FieldValidationRuleInterface $other_group_rule */
    foreach ($ruleset->getFieldValidationRules() as $other_group_rule) {
      if ($other_group_rule->getPluginId() !== $rule->getPluginId() || $other_group_rule->getUuid() === $rule->getUuid()) {
        continue;
      }

      $configuration = $other_group_rule->getConfiguration();
      if ($configuration['data']['data'] !== $group_name) {
        continue;
      }

      foreach ($items as $delta => $item) {
        $item = $item->getValue();
        $item_value = $item[$rule->getColumn()] ?? "";

        $other_items = $entity->{$other_group_rule->getFieldName()}->getValue();
        $other_item_value = $other_items[$delta][$other_group_rule->getColumn()] ?? "";

        if ($item_value !== $other_item_value) {
          $flag = FALSE;
          break;
        }
      }
    }

    if (!$flag) {
      $context->addViolation($rule->getReplacedErrorMessage($params));
    }

  }

}
