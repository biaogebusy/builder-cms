<?php

namespace Drupal\dropai_provider\Plugin\AiProvider;

use Drupal\ai\Attribute\AiProvider;
use Drupal\ai\Base\AiProviderClientBase;
use Drupal\ai\Exception\AiResponseErrorException;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatInterface;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\dropai_provider\MockDropAiClient;
use Drupal\dropai_provider\MockDropAiClient as Client;
use Symfony\Component\Yaml\Yaml;

/**
 * Defines the DropAI Provider plugin.
 *
 * This class serves as an example of how to create an AI provider plugin
 * for a text-to-text model in Drupal. The implementation is a mock provider
 * that developers can use as a template or for testing purposes.
 */
#[AiProvider(
  id: 'dropai',
  label: new TranslatableMarkup('DropAI')
)]
class DropAiProvider extends AiProviderClientBase implements ChatInterface {

  use StringTranslationTrait;

  /**
   * The client object for interacting with the DropAI service.
   *
   * This client is used to make requests to the mock AI service.
   */
  protected Client $client;

  /**
   * The API key for authenticating with the DropAI service.
   */
  protected string $apiKey = '';

  /**
   * Flag to enable or disable moderation of responses.
   *
   * If enabled, the moderation step is applied before generating responses.
   *
   * @var bool
   */
  protected bool $moderation = TRUE;

  /**
   * Stores the system message if applicable.
   *
   * This is typically used for setting context or configuration for the AI.
   *
   * @var string|null
   */
  protected $systemMessage = NULL;

  /**
   * Retrieves the list of configured models supported by this provider.
   *
   * @param string|null $operation_type
   *   The operation type, e.g., "chat".
   * @param array $capabilities
   *   Specific capabilities to filter models by.
   *
   * @return array
   *   An array of supported model configurations.
   *
   * @throws \Drupal\ai\Exception\AiResponseErrorException
   *   Thrown if the models cannot be fetched.
   */
  public function getConfiguredModels(?string $operation_type = NULL, array $capabilities = []): array {
    $this->loadClient();

    try {
      $supported_models = $this->client->loadModels();
    }
    catch (\JsonException $e) {
      throw new AiResponseErrorException('Couldn\'t fetch models.');
    }

    return $supported_models;
  }

  /**
   * Checks if the provider is usable for a given operation type.
   *
   * @param string|null $operation_type
   *   The type of operation, e.g., "chat".
   * @param array $capabilities
   *   Additional capabilities to check against.
   *
   * @return bool
   *   TRUE if the provider can be used; FALSE otherwise.
   */
  public function isUsable(?string $operation_type = NULL, array $capabilities = []): bool {
    if (!$this->getConfig()->get('api_key')) {
      return FALSE;
    }

    if ($operation_type) {
      return in_array($operation_type, $this->getSupportedOperationTypes());
    }

    return TRUE;
  }

  /**
   * Returns the operation types supported by this provider.
   *
   * @return array
   *   An array of supported operation types, e.g., ['chat'].
   */
  public function getSupportedOperationTypes(): array {
    return ['chat'];
  }

  /**
   * Retrieves the configuration for this plugin.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   *   The configuration object.
   */
  public function getConfig(): ImmutableConfig {
    return $this->configFactory->get('dropai_provider.settings');
  }

  /**
   * Retrieves the API definition for this provider.
   *
   * The definition is loaded from a YAML file included with the module.
   *
   * @return array
   *   An array of API defaults defined in the YAML file.
   */
  public function getApiDefinition(): array {
    $definition = Yaml::parseFile(
      $this->moduleHandler->getModule('dropai_provider')
        ->getPath() . '/definitions/api_defaults.yml'
    );
    return $definition;
  }

  /**
   * Configures settings for a specific model.
   *
   * @param string $model_id
   *   The ID of the model being configured.
   * @param array $generalConfig
   *   General configuration options.
   *
   * @return array
   *   The final model settings.
   */
  public function getModelSettings(string $model_id, array $generalConfig = []): array {
    return $generalConfig;
  }

  /**
   * Sets the authentication method for the provider.
   *
   * @param mixed $authentication
   *   The API key or other credentials.
   */
  public function setAuthentication(mixed $authentication): void {
    $this->apiKey = $authentication;
    $this->client = NULL;
  }

  /**
   * Executes a chat operation with the AI model.
   *
   * @param array|string|ChatInput $input
   *   The input messages or configuration for the chat.
   * @param string $model_id
   *   The ID of the model to use.
   * @param array $tags
   *   Optional tags for additional metadata.
   *
   * @return \Drupal\ai\OperationType\Chat\ChatOutput
   *   The response from the AI model.
   *
   * @throws \Drupal\ai\Exception\AiResponseErrorException
   *   Thrown if unsupported roles are found in the input.
   */
  public function chat(array|string|ChatInput $input, string $model_id, array $tags = []): ChatOutput {
    $this->loadClient();

    $chat_input = $input;
    if ($input instanceof ChatInput) {
      foreach ($input->getMessages() as $message) {
        if (!in_array($message->getRole(), ['model', 'user'])) {
          $error_message = sprintf('The role %s, is not supported.', $message->getRole());
          throw new AiResponseErrorException($error_message);
        }
      }
    }

    $response = $this->client->model($model_id)->generate($chat_input);

    $message = new ChatMessage('', $response);

    return new ChatOutput($message, $response, []);
  }

  /**
   * Enables moderation for all future responses.
   */
  public function enableModeration(): void {
    $this->moderation = TRUE;
  }

  /**
   * Disables moderation for all future responses.
   */
  public function disableModeration(): void {
    $this->moderation = FALSE;
  }

  /**
   * Retrieves the raw client instance.
   *
   * @param string $api_key
   *   An optional API key to override the current one.
   *
   * @return \Drupal\dropai_provider\MockDropAiClient
   *   The client instance.
   */
  public function getClient(string $api_key = '') {
    if ($api_key) {
      $this->setAuthentication($api_key);
    }

    $this->loadClient();
    return $this->client;
  }

  /**
   * Loads the client for DropAI interactions.
   *
   * If the client has not been initialized, this method initializes it.
   */
  protected function loadClient(): void {
    $this->client = new MockDropAiClient();
  }

  /**
   * Retrieves the API key from the key module.
   *
   * @return string
   *   The API key value.
   */
  protected function loadApiKey(): string {
    return $this->keyRepository->getKey($this->getConfig()->get('api_key'))
      ->getKeyValue();
  }

  /**
   * Sets the configuration for this provider.
   *
   * @param array $configuration
   *   An array of configuration values.
   */
  public function setConfiguration(array $configuration): void {
    parent::setConfiguration($configuration);

    // Doing this just for demonstration.
    $this->systemMessage = $configuration['system_message'] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function loadModelsForm(array $form, $form_state, string $operation_type, string|NULL $model_id = NULL): array {
    // This function is called from getModelsTable() via DropAiConfigForm.
    // See DropAiConfigForm.php in DropAiConfigForm::buildForm()
    // to enable this function.
    $form = parent::loadModelsForm($form, $form_state, $operation_type, $model_id);
    $config = $this->loadModelConfig($operation_type, $model_id);

    $form['model_data']['example_endpoint'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Endpoint'),
      '#description' => $this->t('Example endpoint'),
      '#default_value' => $config['example_endpoint'] ?? '',
      '#required' => FALSE,
      '#weight' => -10,
    ];

    return $form;
  }

}
