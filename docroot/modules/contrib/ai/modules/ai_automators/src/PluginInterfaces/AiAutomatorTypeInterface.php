<?php

namespace Drupal\ai_automators\PluginInterfaces;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Interface for automator type modifiers.
 */
interface AiAutomatorTypeInterface {

  /**
   * Does it need a prompt.
   *
   * @return bool
   *   If it needs a prompt or not.
   */
  public function needsPrompt();

  /**
   * Advanced mode.
   *
   * @return bool
   *   If tokens are available or not.
   */
  public function advancedMode();

  /**
   * Help text.
   *
   * @return string
   *   Help text to show.
   */
  public function helpText();

  /**
   * Allowed inputs.
   *
   * @return array
   *   The array of field inputs to allow.
   */
  public function allowedInputs();

  /**
   * Returns the text that will be placed as placeholder in the textarea.
   *
   * @return string
   *   The text.
   */
  public function placeholderText();

  /**
   * Return the Tokens.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being worked on.
   *
   * @return array
   *   Token with replacement as key and description as value.
   */
  public function tokens(ContentEntityInterface $entity);

  /**
   * Adds extra form fields to configuration.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being worked on.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition interface.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state.
   * @param array $defaultValues
   *   The default values.
   *
   * @return array
   *   Form array with key starting with automator_{type}.
   */
  public function extraFormFields(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, FormStateInterface $formState, array $defaultValues = []);

  /**
   * Adds extra advanced form fields to configuration.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being worked on.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition interface.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state.
   * @param array $defaultValues
   *   The default values.
   *
   * @return array
   *   Form array with key starting with automator_{type}.
   */
  public function extraAdvancedFormFields(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, FormStateInterface $formState, array $defaultValues = []);

  /**
   * Valiudate the config values.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state.
   */
  public function validateConfigValues($form, FormStateInterface $formState);

  /**
   * Checks if the value is empty on complex field types.
   *
   * @param array $value
   *   The value response.
   * @param array $automatorConfig
   *   The automator config.
   *
   * @return mixed
   *   Return empty array if empty.
   */
  public function checkIfEmpty(array $value, array $automatorConfig = []);

  /**
   * Check if the rule is allowed based on config.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being worked on.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition interface.
   *
   * @return bool
   *   If its allowed or not.
   */
  public function ruleIsAllowed(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition);

  /**
   * Generate the Tokens.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being worked on.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition interface.
   * @param array $automatorConfig
   *   The automator config.
   * @param int $delta
   *   The delta in the values.
   *
   * @return array
   *   Token key and token value.
   */
  public function generateTokens(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig, $delta);

  /**
   * Generates a response.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being worked on.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition interface.
   * @param array $automatorConfig
   *   The automator config.
   *
   * @return array
   *   An array of values.
   */
  public function generate(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig);

  /**
   * Verifies a value.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being worked on.
   * @param mixed $value
   *   The value returned.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition interface.
   * @param array $automatorConfig
   *   The automator config.
   *
   * @return bool
   *   True if verified, otherwise false.
   */
  public function verifyValue(ContentEntityInterface $entity, $value, FieldDefinitionInterface $fieldDefinition, array $automatorConfig);

  /**
   * Stores one or many values.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being worked on.
   * @param array $values
   *   The array of mixed value(s) returned.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition interface.
   * @param array $automatorConfig
   *   The automator config.
   *
   * @return bool|void
   *   True if verified, otherwise false.
   */
  public function storeValues(ContentEntityInterface $entity, array $values, FieldDefinitionInterface $fieldDefinition, array $automatorConfig);

}
