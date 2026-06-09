<?php

namespace Drupal\ai\Base;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Site\Settings;
use Drupal\ai\AiProviderInterface;
use Drupal\ai\Enum\AiModelCapability;
use Drupal\ai\Exception\AiSetupFailureException;
use Drupal\ai\OperationType\Chat\ChatModelForm;
use Drupal\ai\OperationType\Embeddings\EmbeddingsModelForm;
use Drupal\ai\OperationType\GenericType\AbstractModelFormBase;
use Drupal\ai\Traits\OperationType\ChatTrait;
use Drupal\ai\Traits\OperationType\EmbeddingsTrait;
use Drupal\ai\Utility\CastUtility;
use Drupal\key\KeyRepositoryInterface;
use Psr\Http\Client\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Service to handle API requests server.
 */
abstract class AiProviderClientBase implements AiProviderInterface, ContainerFactoryPluginInterface {

  use ChatTrait;
  use EmbeddingsTrait;

  /**
   * Logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected LoggerChannelFactoryInterface $loggerFactory;

  /**
   * The HTTP client.
   *
   * @var \Psr\Http\Client\ClientInterface
   */
  protected ClientInterface $httpClient;

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * Available configurations for this LLM provider.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $config;

  /**
   * Cache backend interface.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected CacheBackendInterface $cacheBackend;

  /**
   * Key repository.
   *
   * @var \Drupal\key\KeyRepositoryInterface
   */
  protected KeyRepositoryInterface $keyRepository;

  /**
   * Module Handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected FileSystemInterface $fileSystem;

  /**
   * The configuration to add to the call.
   *
   * @var array
   */
  public array $configuration = [];

  /**
   * The tags for the prompt.
   *
   * @var array
   */
  protected array $tags = [];

  /**
   * Extra debug data that can be added to events.
   *
   * @var array
   */
  protected array $debugData = [];

  /**
   * Streamed output wanted.
   *
   * @var bool
   */
  protected bool $streamed = FALSE;

  /**
   * Sets a chat system role.
   *
   * @var string
   */
  protected string $chatSystemRole = '';

  /**
   * Has predefined models.
   *
   * If the provider needs to load models after its installed you should set
   * this to FALSE.
   *
   * @var bool
   */
  protected bool $hasPredefinedModels = TRUE;

  /**
   * The plugin definition.
   *
   * @var \Drupal\Core\Plugin\PluginDefinitionInterface|array
   */
  protected $pluginDefinition;

  /**
   * The plugin ID.
   *
   * @var string
   */
  protected string $pluginId;

  /**
   * Constructs a new AiClientBase abstract class.
   *
   * @param string $plugin_id
   *   Plugin ID.
   * @param mixed $plugin_definition
   *   Plugin definition.
   * @param \Psr\Http\Client\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The config factory.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The cache backend.
   * @param \Drupal\key\KeyRepositoryInterface $key_repository
   *   The key repository.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system.
   */
  final public function __construct(
    string $plugin_id,
    mixed $plugin_definition,
    ClientInterface $http_client,
    ConfigFactoryInterface $config_factory,
    LoggerChannelFactoryInterface $logger_factory,
    CacheBackendInterface $cache_backend,
    KeyRepositoryInterface $key_repository,
    ModuleHandlerInterface $module_handler,
    EventDispatcherInterface $event_dispatcher,
    FileSystemInterface $file_system,
  ) {
    $this->pluginDefinition = $plugin_definition;
    $this->pluginId = $plugin_id;
    $this->httpClient = $http_client;
    $this->configFactory = $config_factory;
    $this->loggerFactory = $logger_factory;
    $this->moduleHandler = $module_handler;
    $this->config = $this->getConfig();
    $this->cacheBackend = $cache_backend;
    $this->keyRepository = $key_repository;
    $this->eventDispatcher = $event_dispatcher;
    $this->fileSystem = $file_system;
  }

  /**
   * Load from dependency injection container.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $client_options = $configuration['http_client_options'] ?? [];

    return new static(
      $plugin_id,
      $plugin_definition,
      $container->get('http_client_factory')->fromOptions($client_options + [
        'timeout' => (int) ($container->get('config.factory')->get('ai.settings')->get('request_timeout') ?: 60),
      ]),
      $container->get('config.factory'),
      $container->get('logger.factory'),
      $container->get('cache.default'),
      $container->get('key.repository'),
      $container->get('module_handler'),
      $container->get('event_dispatcher'),
      $container->get('file_system')
    );
  }

  /**
   * Returns configuration of the Client.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   *   Configuration of module.
   */
  public function getConfig(): ImmutableConfig {
    $module_name = $this->pluginDefinition['provider'];
    return $this->configFactory->get($module_name . '.settings');
  }

  /**
   * Returns array of API definition.
   *
   * @return array
   *   The plugin configuration array.
   */
  public function getApiDefinition(): array {
    $module_name = $this->pluginDefinition['provider'];
    $module_path = $this->moduleHandler->getModule($module_name)->getPath();
    $definition_file = $module_path . '/definitions/api_defaults.yml';

    if (file_exists($definition_file)) {
      try {
        return Yaml::parseFile($definition_file);
      }
      catch (\Exception $e) {
        $this->loggerFactory->get('ai')->error(
          'Failed to parse API definition file @file: @message', [
            '@file' => $definition_file,
            '@message' => $e->getMessage(),
          ]
        );
        return [];
      }
    }

    return [];
  }

  /**
   * Returns array of models custom settings.
   *
   * @param string $model_id
   *   The model ID.
   * @param array $generalConfig
   *   The general configuration.
   *
   * @return array
   *   The plugin configuration array.
   */
  abstract public function getModelSettings(string $model_id, array $generalConfig = []): array;

  /**
   * {@inheritDoc}
   */
  public function getPluginId(): string {
    return $this->pluginId;
  }

  /**
   * {@inheritDoc}
   */
  public function getPluginDefinition() {
    return $this->pluginDefinition;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguredModels(?string $operation_type = NULL, array $capabilities = []): array {
    // Default returns nothing.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedCapabilities(): array {
    return [];
  }

  /**
   * Does this provider support this capability.
   *
   * @param enum $capability
   *   The capability to check.
   *
   * @return bool
   *   TRUE if the capability is supported.
   */
  public function supportsCapability(string $capability): bool {
    return in_array($capability, $this->getSupportedCapabilities());
  }

  /**
   * Does this model support these capabilities.
   *
   * @param string $operation_type
   *   The operation type to check for.
   * @param string $model_id
   *   The model ID.
   * @param \Drupal\ai\Enum\AiModelCapability[] $capabilities
   *   The capabilities to check.
   *
   * @return bool
   *   TRUE if the capability is supported.
   */
  public function modelSupportsCapabilities(string $operation_type, string $model_id, array $capabilities): bool {
    $list = $this->getConfiguredModels($operation_type, $capabilities);
    return isset($list[$model_id]);
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration): void {
    $this->configuration = $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration(): array {
    return $this->configuration;
  }

  /**
   * Sets the chat system role.
   *
   * @param string $message
   *   The system role message.
   *
   * @deprecated in ai:1.2.0 and is removed from ai:2.0.0. Please use
   * setSystemPrompt() in the ChatInput class instead.
   * @see https://www.drupal.org/project/ai/issues/3535820
   */
  public function setChatSystemRole(string $message): void {
    $this->chatSystemRole = $message;
    $this->setDebugData('chat_system_role', $message);
  }

  /**
   * Gets the chat system role.
   *
   * @deprecated in ai:1.2.0 and is removed from ai:2.0.0. Please use
   * getSystemPrompt() in the ChatInput class instead.
   * @see https://www.drupal.org/project/ai/issues/3535820
   *
   * @return string
   *   The chat system role message.
   */
  public function getChatSystemRole(): string {
    return $this->chatSystemRole;
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableConfiguration(string $operation_type, string $model_id): array {
    $generalConfig = $this->getApiDefinition()[$operation_type]['configuration'] ?? [];
    $modelConfig = $this->getModelSettings($model_id, $generalConfig);
    return $modelConfig;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultConfigurationValues(string $operation_type, string $model_id): array {
    $configs = $this->getAvailableConfiguration($operation_type, $model_id);
    $defaults = [];
    foreach ($configs as $key => $values) {
      if (isset($values['default']) && !empty($values['required'])) {
        $defaults[$key] = CastUtility::typeCast($values['type'], $values['default']);
      }
    }
    return $defaults;
  }

  /**
   * Get cast of configuration values.
   */

  /**
   * {@inheritdoc}
   */
  public function getInputExample(string $operation_type, string $model_id): mixed {
    return $this->config->get('api_defaults')[$operation_type]['input'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getAuthenticationExample(string $operation_type, string $model_id): mixed {
    return $this->config->get('api_defaults')[$operation_type]['authentication'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function setTag(string $tag): void {
    $this->tags[] = $tag;
  }

  /**
   * {@inheritdoc}
   */
  public function getTags(): array {
    return $this->tags;
  }

  /**
   * {@inheritdoc}
   */
  public function removeTag(string $tag): void {
    $this->tags = array_values(array_diff($this->tags, [$tag]));
  }

  /**
   * {@inheritdoc}
   */
  public function getSetupData(): array {
    return [];
  }

  /**
   * Reset the tags.
   */
  public function resetTags(): void {
    $this->tags = [];
  }

  /**
   * {@inheritdoc}
   */
  public function loadModelsForm(array $form, $form_state, string $operation_type, string|null $model_id = NULL): array {
    $config = $this->loadModelConfig($operation_type, $model_id);
    switch ($operation_type) {
      case 'chat':
        return ChatModelForm::form($form, $form_state, $config, $operation_type);

      case 'embeddings':
        return EmbeddingsModelForm::form($form, $form_state, $config, $operation_type);

      default:
        return AbstractModelFormBase::form($form, $form_state, $config, $operation_type);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateModelsForm(array $form, $form_state): void {
    // Model ID has to be alphanumeric, hyphens or underscore.
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $form_state->getValue('model_id'))) {
      $form_state->setErrorByName('model_id', 'Model ID can only contain letters, numbers, hyphens and underscores.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setDebugData(string $key, mixed $value): void {
    $this->debugData[$key] = $value;
  }

  /**
   * {@inheritdoc}
   */
  public function getDebugData(): array {
    return $this->debugData;
  }

  /**
   * {@inheritDoc}
   */
  public function hasPredefinedModels(): bool {
    return $this->hasPredefinedModels;
  }

  /**
   * Post setup. Currently used in Drupal CMS.
   */
  public function postSetup(): void {
    // Do nothing by default.
  }

  /**
   * Load config for provider, operation type and model.
   *
   * @param string $operation_type
   *   The operation type to generate a response for.
   * @param string|null $model_id
   *   ID of model as set in getConfiguredModels().
   *
   * @return array
   *   The configuration array.
   */
  public function loadModelConfig(string $operation_type, string|NULL $model_id): array {
    if ($model_id) {
      $configs = $this->getModelsConfig();
      if (isset($configs[$this->getPluginId()][$operation_type][$model_id])) {
        $config = $configs[$this->getPluginId()][$operation_type][$model_id];
      }
      else {
        $config['model_id'] = $model_id;
        $config['label'] = $this->getConfiguredModels($operation_type)[$model_id];
        foreach (AiModelCapability::cases() as $capability) {
          $config[$capability->value] = $this->modelSupportsCapabilities($operation_type, $model_id, [$capability]);
        }
        $config['max_input_tokens'] = $this->getMaxInputTokens($model_id);
        $config['max_output_tokens'] = $this->getMaxOutputTokens($model_id);
      }
      $config['new_model'] = FALSE;
    }
    else {
      $config = [
        'new_model' => TRUE,
      ];
    }
    $config['has_predefined_models'] = $this->hasPredefinedModels;
    $config['has_overriden_settings'] = Settings::get('ai_override_models') ?? FALSE;
    return $config;
  }

  /**
   * Set the streamed output.
   *
   * @param bool $streamed
   *   Streamed output or not.
   *
   * @deprecated in ai:1.2.0 and is removed from ai:2.0.0. Use the method
   * setStreamedOutput() on the ChatInput object instead.
   * @see https://www.drupal.org/project/ai/issues/3535821
   */
  public function streamedOutput(bool $streamed = TRUE): void {
    $this->streamed = $streamed;
    // We add for debugging that its streamed.
    $this->setDebugData('is_streamed', $streamed);
  }

  /**
   * Get if we should stream the output.
   *
   * @deprecated in ai:1.2.0 and is removed from ai:2.0.0. Use the method
   * isStreamedOutput() on the ChatInput object instead.
   * @see https://www.drupal.org/project/ai/issues/3535821
   *
   * @return bool
   *   TRUE if the output should be streamed, FALSE otherwise.
   */
  public function isStreamedOutput(): bool {
    return $this->streamed;
  }

  /**
   * Normalize the configuration before runtime.
   *
   * @param string $operation_type
   *   The operation type to generate a response for.
   * @param string $model_id
   *   ID of model as set in getConfiguredModels().
   */
  public function normalizeConfiguration(string $operation_type, $model_id): array {
    $values = $this->getDefaultConfigurationValues($operation_type, $model_id);
    foreach ($this->configuration as $key => $value) {
      $values[$key] = $value;
    }
    return $values;
  }

  /**
   * Load the provider API key from the key module.
   *
   * @return string
   *   The API key.
   *
   * @throws \Drupal\ai\Exception\AiSetupFailureException
   *   Thrown when API key could not be loaded or empty.
   */
  protected function loadApiKey(): string {
    $api_key = $this->keyRepository->getKey($this->getConfig()->get('api_key') ?? '')?->getKeyValue();
    if (!$api_key) {
      throw new AiSetupFailureException(
        sprintf(
          'Could not load the %s API key, please check your environment settings or your setup key.',
          $this->getPluginDefinition()['label']),
      );
    }
    return $api_key;
  }

  /**
   * Get the models configuration.
   *
   * @return array
   *   The models configuration.
   */
  public function getModelsConfig(): array {
    return $this->configFactory->get('ai.settings')?->get('models') ?? [];
  }

  /**
   * Get model information.
   *
   * @param string $operation_type
   *   The operation type.
   * @param string $model_id
   *   The model ID.
   *
   * @return array
   *   The model information.
   */
  public function getModelInfo(string $operation_type, string $model_id): array {
    // Check first override.
    $models = $this->getModelsConfig();
    if (isset($models[$this->getPluginId()][$operation_type][$model_id])) {
      return $models[$this->getPluginId()][$operation_type][$model_id];
    }
    // Otherwise get the models.
    $models = $this->getConfiguredModels($operation_type);
    if (isset($models[$model_id])) {
      return [
        'model_id' => $model_id,
        'label' => $models[$model_id],
      ];
    }
    return [];
  }

}
