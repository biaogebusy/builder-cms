<?php

namespace Drupal\ai;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Defines an interface for AI provider services.
 */
interface AiProviderInterface extends PluginInspectionInterface {

  /**
   * Constant for the default provider/model option value.
   */
  public const DEFAULT_MODEL_VALUE = '__default__';

  /**
   * Provides associative array with a list of models' IDs.
   *
   * Keyed with model IDs, valued with human-readable names
   * and optionally filtered by type.
   *
   * @param string|null $operation_type
   *   The operation type.
   * @param array $capabilities
   *   The capabilities to filter by.
   *
   * @return array
   *   The list of models.
   */
  public function getConfiguredModels(?string $operation_type = NULL, array $capabilities = []): array;

  /**
   * Returns if the provider is setup and ready to use for the type.
   *
   * @param string|null $operation_type
   *   Operation type string.
   * @param array $capabilities
   *   The capabilities to filter by.
   *
   * @return bool
   *   Returns TRUE if the provider is setup and ready to use.
   */
  public function isUsable(?string $operation_type = NULL, array $capabilities = []): bool;

  /**
   * Returns the supported operation types for this provider.
   *
   * @return string[]
   *   List of supported operation types.
   */
  public function getSupportedOperationTypes(): array;

  /**
   * Returns the supported capabilities for this provider.
   *
   * @return array
   *   List of supported capabilities.
   */
  public function getSupportedCapabilities(): array;

  /**
   * Returns array of available configuration parameters for given type.
   *
   * @param string $operation_type
   *   Operation type as defined in OperationTypeInterface.
   * @param string $model_id
   *   LLMs ID as returned from getConfiguredModels().
   *
   * @return array
   *   List of all available configurations for given model.
   */
  public function getAvailableConfiguration(string $operation_type, string $model_id): array;

  /**
   * Returns array of default configuration values for given model.
   *
   * @param string $operation_type
   *   Operation type as defined in OperationTypeInterface.
   * @param string $model_id
   *   LLMs ID as returned from getConfiguredModels().
   *
   * @return array
   *   List of configuration values set for given model.
   */
  public function getDefaultConfigurationValues(string $operation_type, string $model_id): array;

  /**
   * Returns input example for given model.
   *
   * @param string $operation_type
   *   Operation type as defined in OperationTypeInterface.
   * @param string $model_id
   *   LLMs ID as returned from getConfiguredModels().
   *
   * @return array|mixed|null
   *   Example of input variable for given model.
   */
  public function getInputExample(string $operation_type, string $model_id): mixed;

  /**
   * Returns authentication data structure for given model.
   *
   * @param string $operation_type
   *   The operation type for the request.
   * @param string $model_id
   *   LLMs ID as returned from getConfiguredModels().
   *
   * @return array|mixed|null
   *   Example of authentication variable for given model.
   */
  public function getAuthenticationExample(string $operation_type, string $model_id): mixed;

  /**
   * Set authentication data for the AI provider.
   *
   * @param mixed $authentication
   *   Authentication data.
   */
  public function setAuthentication(mixed $authentication): void;

  /**
   * Set configuration data for the AI provider.
   *
   * @param array $configuration
   *   Configuration data.
   */
  public function setConfiguration(array $configuration): void;

  /**
   * Get configuration data for the AI provider.
   *
   * @return array
   *   Configuration data.
   */
  public function getConfiguration(): array;

  /**
   * Set one tag form the AI Provider.
   *
   * @param string $tag
   *   The tag to set.
   */
  public function setTag(string $tag): void;

  /**
   * Get all tags from the AI Provider.
   *
   * @return array
   *   The tags.
   */
  public function getTags(): array;

  /**
   * Helper to clear all tags prior to rebuilding them.
   */
  public function resetTags(): void;

  /**
   * Set debug data for the AI Provider.
   *
   * @param string $key
   *   The key to set.
   * @param mixed $value
   *   The value to set.
   */
  public function setDebugData(string $key, mixed $value): void;

  /**
   * Get debug data from the AI Provider.
   *
   * @return array
   *   The debug data.
   */
  public function getDebugData(): array;

  /**
   * Remove one tag from the AI Provider.
   *
   * @param string $tag
   *   The tag to remove.
   */
  public function removeTag(string $tag): void;

  /**
   * Load the models form for the provider.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $operation_type
   *   The operation type to generate a response for.
   * @param string|null $model_id
   *   The model id.
   *
   * @return array
   *   The form array.
   */
  public function loadModelsForm(array $form, $form_state, string $operation_type, string|null $model_id = NULL): array;

  /**
   * Validate the models form for the provider.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function validateModelsForm(array $form, $form_state): void;

  /**
   * Method to check if the provider has predefined models.
   *
   * If set to false it means that the system generates models for it.
   *
   * @return bool
   *   True if the provider has predefined models.
   */
  public function hasPredefinedModels(): bool;

  /**
   * Returns an array of setup data for the provider.
   *
   * The data should be an array of arrays with the following keys:
   * - key_config_name: The key for setting an api key via key module. Can be
   *   empty. If empty, its not setup.
   * - default_models: An assoc array of operation type and model id, for
   *   setting the default models.
   *
   * @return array
   *   The setup data.
   */
  public function getSetupData(): array;

}
