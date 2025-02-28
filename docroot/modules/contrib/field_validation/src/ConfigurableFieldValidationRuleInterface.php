<?php

namespace Drupal\field_validation;

use Drupal\Core\Plugin\PluginFormInterface;

/**
 * Defines the interface for configurable FieldValidationRule.
 *
 * @see \Drupal\field_validation\Annotation\FieldValidationRule
 * @see \Drupal\field_validation\ConfigurableFieldValidationRuleBase
 * @see \Drupal\field_validation\FieldValidationRuleInterface
 * @see \Drupal\field_validation\FieldValidationRuleBase
 * @see \Drupal\field_validation\FieldValidationRuleManager
 * @see plugin_api
 */
interface ConfigurableFieldValidationRuleInterface extends FieldValidationRuleInterface, PluginFormInterface {
}
