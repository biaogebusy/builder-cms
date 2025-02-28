<?php

namespace Drupal\field_validation\Plugin\FieldValidationRule;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\field_validation\ConfigurableFieldValidationRuleBase;
use Drupal\field_validation\FieldValidationRuleSetInterface;

/**
 * OneOfSeveralValidationRule.
 *
 * @FieldValidationRule(
 *   id = "one_of_several_validation_rule",
 *   label = @Translation("Require at least one of several fields"),
 *   description = @Translation("Forces the user to specify / select at least one of several fields.")
 * )
 */
class OneOfSeveralValidationRule extends ConfigurableFieldValidationRuleBase {

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
    $flag = FALSE;

    /** @var \Drupal\field_validation\ConfigurableFieldValidationRuleInterface $rule */
    $rule = $params['rule'] ?? NULL;
    /** @var \Drupal\field_validation\Entity\FieldValidationRuleSet $ruleset */
    $ruleset = $params['ruleset'] ?? NULL;
    /** @var \Drupal\Core\TypedData\Validation\ExecutionContext $context */
    $context = $params['context'];

    $field_values = $this->getFieldColumnValue($params['items'], $rule->column);
    $field_values = array_flip($field_values);
    if (count($field_values) > 0) {
      $flag = TRUE;
    }

    if (!$flag) {
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

        /** @var \Drupal\node\Entity\Node $object */
        $entity = $context->getRoot()->getValue();

        $other_items = $entity->{$other_group_rule->getFieldName()};
        $other_field_values = $this->getFieldColumnValue($other_items, $other_group_rule->getColumn());
        $other_field_values = array_flip($other_field_values);
        if (count($other_field_values) > 0) {
          $flag = TRUE;
          break;
        }
      }
    }

    if (!$flag) {
      $context->addViolation($rule->getReplacedErrorMessage($params));
    }

    return TRUE;
  }

  /**
   *
   */
  private function getFieldColumnValue($items, $column = 'value'): array {
    $field_values = [];
    foreach ($items as $item) {
      if ($item instanceof FieldItemInterface) {
        $item = $item->getValue();
      }
      if (is_array($item) && isset($item[$column]) && $item[$column] != '') {
        $field_values[] = $item[$column];
      }
    }
    return $field_values;
  }

}
