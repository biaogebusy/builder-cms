# Develop a new AI Provider

## What is an AI Provider?
An AI Provider is a Drupal module that connects with the [AI Core](https://drupal.org/project/ai) module to bring AI services into your project. The AI Core module serves as the central hub, enabling your custom module to interact with various AI providers without needing to adapt to each one individually.

As shown in the image below, AI Core links to multiple AI Providers, such as OpenAI, Gemini, Anthropic, or any custom AI service. Each provider acts as a plugin that supplies AI capabilities, which are standardized by AI Core. This way, your custom module only needs to interact with AI Core, which handles the complexities of each specific provider.

![AI Core module and providers](https://miro.medium.com/v2/resize:fit:4800/format:webp/0*YEUnqeZ9mExt3UI3)

This structure allows you to switch between AI providers or add new ones easily. Additionally, it enables you to use both public AI providers and your private models together within the same system.

From a low-level technical perspective, an AI Provider is a plugin implementation using [Drupal’s Plugin](https://www.drupal.org/docs/drupal-apis/plugin-api) API that can be discovered by the AI Core module. At a higher level, an AI Provider is a Drupal module that combines three core components:

1. A settings form for configuring the provider.
2. Actual implementation of the provider plugin interface.
3. Definitions for API defaults.

## How do I start?

We'll walk through the steps required to create an AI provider module for a fictional AI service, DropAI, which will implement a text-to-text AI capability.

### Step 1: Create a New Drupal Module

The first step is to create a new Drupal module for your AI provider. You have to have at least two dependencies for your module — the AI module obviously, and it’s a good idea to have a Key module. The key module is used in the Drupal ecosystem to securely store sensitive data, like API keys, which is critical for managing authentication details for our provider. Although you are free to handle authentication and manage keys the way you like, it’s a good practice to use the Key module.

Below is an example of the `dropai_provider.info.yml` file:

```yaml
name: 'DropAI Provider'
type: module
description: 'DropAI provider for AI module.'
package: AI Providers
core_version_requirement: ^10 || ^11
dependencies:
  - ai:ai
  - key:key
```

### Step 2: Create a Configuration Mechanism

Now, let’s create a configuration mechanism for our module.

A configuration form is necessary to allow site administrators to set up stuff for the provider — an API key for authentication, or other settings like moderation rules.

This standard Drupal config form will serve as the interface for entering and managing these settings.

I recommend you to generate this form (and the entire new module) with drush, however here is a draft of this form with API key setting, so that you understand how this might look like.

```php
<?php

declare(strict_types=1);

namespace Drupal\dropai_provider\Form;

use Drupal\ai\AiProviderPluginManager;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure DropAI Provider settings.
 */
final class DropAiConfigForm extends ConfigFormBase {

  /**
   * Config settings.
   */
  const CONFIG_NAME = 'dropai_provider.settings';

  /**
   * The AI provider manager.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected $aiProviderManager;

  /**
   * Module Handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a new AnthropicConfigForm object.
   */
  final public function __construct(AiProviderPluginManager $ai_provider_manager, ModuleHandlerInterface $module_handler) {
    $this->aiProviderManager = $ai_provider_manager;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  final public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ai.provider'),
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'dropai_provider_dropai_config';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['dropai_provider.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config(static::CONFIG_NAME);

    $form['api_key'] = [
      '#type' => 'key_select',
      '#title' => $this->t('DropAI API Key'),
      '#description' => $this->t('The DropAI API Key.'),
      '#default_value' => $config->get('api_key'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Do a check if it's getting set up or disabled.
    if ($form_state->getValue('api_key')) {
      // Here we set the default providers per the operation for our provider.
      $this->aiProviderManager->defaultIfNone('chat', 'dropai', 'drop-ai-text-model-1');
    }
    else {
      // We notify 3rd party modules that it has been disabled.
      $this->aiProviderManager->providerDisabled('dropai');
    }

    $this->config('dropai_provider.settings')
      ->set('api_key', $form_state->getValue('api_key'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
```

It's recommended (like all other providers do) to put the form under the `admin/config/ai`.

After this, you will have the simple config form for your provider to set the authentication key (first you create the key in the Key module and then select it for your provider).

### Step 3: Implement the plugin

Here is the plugin implementation for our fictional provider. We implement *ChatInterface* operation only. If your provider supports other operations, like text to image or voice to text, you will need to implement other interfaces as well (those can be found in the AI module itself).

Most parts of the code are mock, to be used as a starter. You will need to modify it and implement based on your provider.

You can see the entire codebase in the [dropai module](https://git.drupalcode.org/project/ai/-/tree/1.0.x/docs/examples/dropai_provider) and find the parts where we load the api key for nothing (to demonstrate the idea), you will use it to authenticate with your provider. Also there is a mock provider client implemented in the example module, which generates dummy text responses — you will need to use the specific client for your provider. Many of the popular providers already have the PHP libraries and it's better to use them.

Following is the plugin implementation for the provider — the central part of the provider. You can use comments to understand more.

```php
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
   */
  protected bool $moderation = TRUE;

  /**
   * Stores the system message if applicable.
   *
   * This is typically used for setting context or configuration for the AI.
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
   * @throws AiResponseErrorException
   *   Thrown if the models cannot be fetched.
   */
  public function getConfiguredModels(string $operation_type = NULL, array $capabilities = []): array {
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
  public function isUsable(string $operation_type = NULL, array $capabilities = []): bool {
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
   * @return ImmutableConfig
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
   * @return ChatOutput
   *   The response from the AI model.
   *
   * @throws AiResponseErrorException
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
   * @return Client
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
  }

}
```

Finally you will need to define some API defaults, which reflect the parameters that your provider supports — for example the temperature, or topN, that most AI providers have. But you need to follow the documentation of the AI service, you are building the provider for. [Here](https://git.drupalcode.org/project/ai/-/tree/1.0.x/docs/examples/dropai_provider/definitions/api_defaults.yml) you can check the example for this.

After all of this, you will be able to see your newly implemented provider in the AI Explorer of the AI module and use it like other providers.

### Rate Limits
If your provider has rate limits, you can set them on the `ChatOutput` object returned by the `chat` method using `setRateLimits()`. Create a `ChatProviderLimitsDto` object and set the appropriate values, such as maximum requests, remaining requests, and reset time. This information can then be consumed through `getRateLimits()` and used by the AI module to manage and display rate limit status to users.
