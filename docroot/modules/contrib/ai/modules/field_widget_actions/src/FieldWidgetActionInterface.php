<?php

namespace Drupal\field_widget_actions;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\WidgetInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Interface for field widget actions.
 */
interface FieldWidgetActionInterface extends PluginInspectionInterface, ConfigurableInterface {

  /**
   * Get the widget types.
   *
   * @return string[]
   *   The list of widget types.
   */
  public function getWidgetTypes(): array;

  /**
   * Get the field types.
   *
   * @return string[]
   *   The list of field types.
   */
  public function getFieldTypes(): array;

  /**
   * Gets plugin label.
   *
   * @return string
   *   The label.
   */
  public function getLabel(): string;

  /**
   * Gets plugin description.
   *
   * @return string
   *   The description.
   */
  public function getDescription(): string;

  /**
   * Gets widget plugin instance.
   *
   * @return \Drupal\Core\Field\WidgetInterface|null
   *   The widget plugin instance if available.
   */
  public function getWidget(): ?WidgetInterface;

  /**
   * Sets widget plugin instance.
   *
   * @param \Drupal\Core\Field\WidgetInterface|null $widget
   *   The widget plugin instance.
   */
  public function setWidget(?WidgetInterface $widget): void;

  /**
   * Gets field definition.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface|null
   *   The field definition.
   */
  public function getFieldDefinition(): ?FieldDefinitionInterface;

  /**
   * Sets field definition.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface|null $fieldDefinition
   *   The field definition.
   */
  public function setFieldDefinition(?FieldDefinitionInterface $fieldDefinition): void;

  /**
   * Checks whether the functionality of the plugin is available.
   *
   * @return bool
   *   TRUE if the plugin is available, FALSE - otherwise.
   */
  public function isAvailable(): bool;

  /**
   * Gets the libraries that should be added to the field widget form.
   *
   * @return array
   *   The list of libraries to add to the field widget.
   */
  public function getLibraries(): array;

  /**
   * Alters the complete field widget form.
   *
   * @param array $form
   *   The form to alter.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $context
   *   The widget form element context.
   */
  public function completeFormAlter(array &$form, FormStateInterface $form_state, array $context = []);

  /**
   * Alters the single element of field widget form.
   *
   * @param array $form
   *   The form to alter.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $context
   *   The widget form element context.
   */
  public function singleElementFormAlter(array &$form, FormStateInterface $form_state, array $context = []);

  /**
   * Field widget action configuration form constructor.
   *
   * This form is not the usual plugin subform, it will be part of third party
   * settings form of form display.
   *
   * @param array $form
   *   An associative array containing the initial structure of the plugin form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form. Calling code should pass on a subform
   *   state created through
   *   \Drupal\Core\Form\SubformState::createForSubform().
   * @param string|null $action_id
   *   The id of the parent element that will have this form.
   *
   * @return array
   *   The form structure.
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, $action_id = NULL);

}
