<?php

namespace Drupal\field_validation;

use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a base class for configurable FieldValidationRule.
 *
 * @see \Drupal\field_validation\Annotation\FieldValidationRule
 * @see \Drupal\field_validation\ConfigurableFieldValidationRuleInterface
 * @see \Drupal\field_validation\FieldValidationRuleInterface
 * @see \Drupal\field_validation\FieldValidationRuleBase
 * @see \Drupal\field_validation\FieldValidationRuleManager
 * @see plugin_api
 */
abstract class ConfigurableFieldValidationRuleBase extends FieldValidationRuleBase implements ConfigurableFieldValidationRuleInterface {

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
  }

}
