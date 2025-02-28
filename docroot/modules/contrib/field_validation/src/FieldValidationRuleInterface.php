<?php

namespace Drupal\field_validation;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Defines the interface for Field Validation.
 *
 * @see \Drupal\field_validation\Annotation\FieldValidationRule
 * @see \Drupal\field_validation\FieldValidationRuleBase
 * @see \Drupal\field_validation\ConfigurableFieldValidationRuleInterface
 * @see \Drupal\field_validation\ConfigurableFieldValidationRuleBase
 * @see \Drupal\field_validation\FieldValidationRuleManager
 * @see plugin_api
 */
interface FieldValidationRuleInterface extends PluginInspectionInterface, ConfigurableInterface, DependentPluginInterface {

  /**
   * Applies a field_validation_rule to the field_validation_rule_set.
   *
   * @param \Drupal\field_validation\FieldValidationRuleSetInterface $field_validation_rule_set
   *   An field_validation_rule_set object.
   *
   * @return bool
   *   TRUE on success. FALSE if unable to add the
   *   field_validation_rule to the field_validation_rule_set.
   */
  public function addFieldValidationRule(FieldValidationRuleSetInterface $field_validation_rule_set);

  /**
   * Get the derivative after adding this field_validation_rule.
   *
   * @param string $extension
   *   The field_validation_rule extension the derivative has before adding.
   *
   * @return string
   *   The field_validation_rule extension after adding.
   */
  public function getDerivativeExtension($extension);

  /**
   * Get the summary of the configuration of the field_validation_rule.
   *
   * @return array
   *   A render array.
   */
  public function getSummary();

  /**
   * Returns the field_validation_rule label.
   *
   * @return string
   *   The field_validation_rule label.
   */
  public function label();

  /**
   * Returns the unique ID representing the field_validation_rule.
   *
   * @return string
   *   The field_validation_rule ID.
   */
  public function getUuid();

  /**
   * Returns the weight of the field_validation_rule.
   *
   * @return int|string
   *   Either the integer weight of the field_validation_rule
   *   or an empty string.
   */
  public function getWeight();

  /**
   * Sets the weight for this field_validation_rule.
   *
   * @param int $weight
   *   The weight for this field_validation_rule.
   *
   * @return $this
   */
  public function setWeight($weight);

  /**
   * Returns the title of the field_validation_rule.
   *
   * @return string
   *   Either the string of the field_validation_rule.
   */
  public function getTitle();

  /**
   * Sets the title for this field_validation_rule.
   *
   * @param int $title
   *   The title for this field_validation_rule.
   *
   * @return $this
   */
  public function setTitle($title);

  /**
   * Returns the field name of the field_validation_rule.
   *
   * @return string
   *   The field name of the field_validation_rule.
   */
  public function getFieldName();

  /**
   * Sets the field name for this field_validation_rule.
   *
   * @param int $field_name
   *   The field name for this field_validation_rule.
   *
   * @return $this
   */
  public function setFieldName($field_name);

  /**
   * Returns the column of the field_validation_rule.
   *
   * @return string
   *   The column of the field_validation_rule.
   */
  public function getColumn();

  /**
   * Sets the column for this field_validation_rule.
   *
   * @param int $column
   *   The column for this field_validation_rule.
   *
   * @return $this
   */
  public function setColumn($column);

  /**
   * Returns the error message of the field_validation_rule.
   *
   * @return string
   *   The error message of the field_validation_rule.
   */
  public function getErrorMessage();

  /**
   * Sets the error_message for this field_validation_rule.
   *
   * @param int $error_message
   *   The error message for this field_validation_rule.
   *
   * @return $this
   */
  public function setErrorMessage($error_message);

  /**
   * Validate the value.
   *
   * @param array $params
   *   The context params passed for validate.
   */
  public function validate($params);

  /**
   * Returns a list of the user roles this rule is applicable for.
   *
   * @return string[]
   *   The applicable roles. Empty array when the rule is applicable for any
   *   user (all roles).
   */
  public function getApplicableRoles();

  /**
   * Set the user roles this rule is applicable for.
   *
   * @param string[] $roles
   *   The applicable roles.
   *
   * @return $this
   */
  public function setApplicableRoles(array $roles);

  /**
   * Returns the condition this rule is applicable for.
   *
   * @return array
   *   The applicable condition.
   */
  public function getCondition();

  /**
   * Set the condition this rule is applicable for.
   *
   * @param array $condition
   *   The applicable condition.
   *
   * @return $this
   */
  public function setCondition(array $condition);

}
