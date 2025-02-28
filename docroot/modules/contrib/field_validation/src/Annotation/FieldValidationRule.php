<?php

namespace Drupal\field_validation\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a field validation rule annotation object.
 *
 * Plugin Namespace: Plugin\FieldValidationRule.
 *
 * For a working example, see
 * \Drupal\field_validation\Plugin\FieldValidationRule\LengthFieldValidationRule
 *
 * @see hook_field_validation_rule_info_alter()
 * @see \Drupal\field_validation\ConfigurableFieldValidationRuleInterface
 * @see \Drupal\field_validation\ConfigurableFieldValidationRuleBase
 * @see \Drupal\field_validation\FieldValidationRuleInterface
 * @see \Drupal\field_validation\FieldValidationRuleBase
 * @see \Drupal\field_validation\FieldValidationRuleManager
 * @see plugin_api
 *
 * @Annotation
 */
class FieldValidationRule extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable name of the field validation rule.
   *
   * @ingroup plugin_translatable
   *
   * @var \Drupal\Core\Annotation\Translation
   */
  public $label;

  /**
   * A brief description of the field validation rule.
   *
   * This will be shown when adding or configuring this field validation rule.
   *
   * @ingroup plugin_translatable
   *
   * @var \Drupal\Core\Annotation\Translation
   */
  public $description = '';

}
