<?php

namespace Drupal\field_validation;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface defining a FieldValidationRuleSet entity.
 */
interface FieldValidationRuleSetInterface extends ConfigEntityInterface {

  /**
   * Returns the FieldValidationRuleSet.
   *
   * @return string
   *   The name of the FieldValidationRuleSet.
   */
  public function getName();

  /**
   * Sets the name of the FieldValidationRuleSet.
   *
   * @param string $name
   *   The name of the FieldValidationRuleSet.
   *
   * @return \Drupal\field_validation\FieldValidationRuleSetInterface
   *   The class instance this method is called on.
   */
  public function setName($name);

  /**
   * Returns a specific FieldValidationRule.
   *
   * @param string $field_validation_rule
   *   The FieldValidationRule ID.
   *
   * @return \Drupal\field_validation\FieldValidationRuleInterface
   *   The FieldValidationRule object.
   */
  public function getFieldValidationRule($field_validation_rule);

  /**
   * Returns the  field_validation_rules for this field_validation_rule_set.
   *
   * @return \Drupal\field_validation\FieldValidationRulePluginCollection|\Drupal\field_validation\FieldValidationRuleInterface[]
   *   The FieldValidationRule plugin collection.
   */
  public function getFieldValidationRules();

  /**
   * Saves a FieldValidationRule for this Field Validation ruleset.
   *
   * @param array $configuration
   *   An array of FieldValidationRule configuration.
   *
   * @return string
   *   The FieldValidationRule ID.
   */
  public function addFieldValidationRule(array $configuration);

  /**
   * Deletes a field_validation_rule from this FieldValidationRuleSet.
   *
   * @param \Drupal\field_validation\FieldValidationRuleInterface $field_validation_rule
   *   The FieldValidationRule object.
   *
   * @return $this
   */
  public function deleteFieldValidationRule(FieldValidationRuleInterface $field_validation_rule);

  /**
   * Returns the entity type of FieldValidationRuleSet.
   *
   * @return string
   *   The entity type of the FieldValidationRuleSet.
   */
  public function getAttachedEntityType();

  /**
   * Sets the entity_type of the FieldValidationRuleSet.
   *
   * @param string $entity_type
   *   The entity type of the FieldValidationRuleSet.
   *
   * @return \Drupal\field_validation\FieldValidationRuleSetInterface
   *   The class instance this method is called on.
   */
  public function setAttachedEntityType($entity_type);

  /**
   * Returns the bundle of FieldValidationRuleSet.
   *
   * @return string
   *   The bundle of the FieldValidationRuleSet.
   */
  public function getAttachedBundle();

  /**
   * Sets the bundle of the FieldValidationRuleSet.
   *
   * @param string $bundle
   *   The bundle of the FieldValidationRuleSet.
   *
   * @return \Drupal\field_validation\FieldValidationRuleSetInterface
   *   The class instance this method is called on.
   */
  public function setAttachedBundle($bundle);

}
