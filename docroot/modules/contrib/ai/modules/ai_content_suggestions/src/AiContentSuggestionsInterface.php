<?php

declare(strict_types=1);

namespace Drupal\ai_content_suggestions;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Interface for ai_content_suggestions plugins.
 */
interface AiContentSuggestionsInterface {

  /**
   * Returns the plugin label.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The label from the annotation.
   */
  public function label(): TranslatableMarkup;

  /**
   * Returns the plugin description.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The description from the annotation.
   */
  public function description(): TranslatableMarkup;

  /**
   * Returns the operation type.
   *
   * @return string
   *   The operation_type from the annotation.
   */
  public function operationType(): string;

  /**
   * Build the form to display for this plugin on the settings page.
   *
   * @param array $form
   *   The form to add the plugin settings to.
   */
  public function buildSettingsForm(array &$form): void;

  /**
   * Calls $this->providerPluginManager->getSimpleProviderModelOptions().
   *
   * @param bool $empty
   *   Whether to include the default empty option. TRUE by default.
   *
   * @return array
   *   An array of available models suitable for an #options element.
   */
  public function getModels(bool $empty = TRUE): array;

  /**
   * Calls $this->providerPluginManager->getDefaultProviderForOperationType().
   *
   * @return string|null
   *   The default model for the plugins operation, or NULL on error.
   */
  public function getDefaultModel(): ?string;

  /**
   * Checks within config to see if the plugin is currently enabled.
   *
   * @return bool
   *   TRUE if the plugin is enabled, FALSE if not.
   */
  public function isEnabled(): bool;

  /**
   * Checks whether the plugin can be used with the current set up.
   *
   * @return bool
   *   Whether the plugin can be used.
   */
  public function isAvailable(): bool;

  /**
   * Alter the entity edit form to add the interface for the plugin.
   *
   * @param array $form
   *   The form to be altered.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $fields
   *   An array of available fields from the content.
   */
  public function alterForm(array &$form, FormStateInterface $form_state, array $fields);

  /**
   * Rebuilds the form with the LLM response in the response section.
   *
   * @param array $form
   *   The current edit form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function updateFormWithResponse(array &$form, FormStateInterface $form_state): void;

  /**
   * Helper to get the AJAX Id for replacement text.
   *
   * @return string
   *   The valid, unique id.
   */
  public function getAjaxId(): string;

  /**
   * Helper to get a value from the form submission.
   *
   * @param string $form_field
   *   The field to obtain the value for.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The Drupal Form State.
   *
   * @return mixed|null
   *   The Value, or NULL on error.
   */
  public function getFormFieldValue(string $form_field, FormStateInterface $form_state): mixed;

  /**
   * Helper to get the selected target field from the submitted values.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The Drupal Form State.
   *
   * @return mixed
   *   The value, or NULL on error.
   */
  public function getTargetFieldValue(FormStateInterface $form_state): mixed;

  /**
   * Get the preferred provider if configured, else take the default one.
   *
   * @param string $operation_type
   *   The operation type.
   * @param string|null $preferred_model
   *   The preferred model.
   *
   * @return array
   *   The provider and model.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function getSetProvider(string $operation_type, string|null $preferred_model = NULL): array;

  /**
   * Helper to send a chat with prompt to the LLM.
   *
   * @param string $prompt
   *   The full prompt for the LLM.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|string
   *   The LLM response or error messages.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function sendChat(string $prompt): string|TranslatableMarkup;

}
