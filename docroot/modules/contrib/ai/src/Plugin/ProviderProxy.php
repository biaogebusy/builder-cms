<?php

namespace Drupal\ai\Plugin;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\ai\Base\AiProviderClientBase;
use Drupal\ai\Event\AiExceptionEvent;
use Drupal\ai\Event\PostGenerateResponseEvent;
use Drupal\ai\Event\PreGenerateResponseEvent;
use Drupal\ai\Exception\AiBadRequestException;
use Drupal\ai\Exception\AiMissingFeatureException;
use Drupal\ai\Exception\AiOperationTypeMissingException;
use Drupal\ai\Exception\AiQuotaException;
use Drupal\ai\Exception\AiRateLimitException;
use Drupal\ai\Exception\AiRequestErrorException;
use Drupal\ai\Exception\AiResponseErrorException;
use Drupal\ai\Exception\AiUnsafePromptException;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;
use Drupal\ai\OperationType\Chat\StreamedChatMessageIteratorInterface;
use Drupal\ai\OperationType\InputInterface;
use Drupal\ai\OperationType\OperationTypeInterface;
use Drupal\ai\Service\HostnameFilter;
use Psr\Http\Client\ClientExceptionInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Proxies calls to a plugin and logs before and after the method execution.
 */
class ProviderProxy {

  /**
   * The plugin to proxy.
   *
   * @var object
   */
  protected $plugin;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheBackend;

  /**
   * The UUID service.
   *
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected $uuid;

  /**
   * The Hostname filter service.
   *
   * @var \Drupal\ai\Service\HostnameFilter
   */
  protected $hostnameFilterService;

  /**
   * The request parent id.
   *
   * @var string
   */
  protected $requestParentId;

  /**
   * Attach metadata to streamed chat responses.
   */
  protected function attachStreamMetadata(
    StreamedChatMessageIteratorInterface $streamed,
    string $event_id,
    $input = NULL,
    ?string $provider_id = NULL,
    ?string $model_id = NULL,
    ?array $provider_configuration = NULL,
    array $tags = [],
  ) {
    $streamed->setInput($input);
    $streamed->setProviderId($provider_id);
    $streamed->setModelId($model_id);
    $streamed->setProviderConfiguration($provider_configuration);
    $streamed->setTags($tags);
    $streamed->setRequestThreadId($event_id);
  }

  /**
   * PluginLoggingProxy constructor.
   *
   * @param \Drupal\ai\Base\AiProviderClientBase $plugin
   *   The plugin to proxy.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Component\Uuid\UuidInterface $uuid
   *   The UUID service.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The cache backend.
   * @param \Drupal\ai\Service\HostnameFilter $hostname_filter_service
   *   The hostname filter service.
   */
  public function __construct(AiProviderClientBase $plugin, EventDispatcherInterface $event_dispatcher, LoggerChannelFactoryInterface $logger_factory, UuidInterface $uuid, CacheBackendInterface $cache_backend, HostnameFilter $hostname_filter_service) {
    $this->plugin = $plugin;
    $this->eventDispatcher = $event_dispatcher;
    $this->loggerFactory = $logger_factory;
    $this->uuid = $uuid;
    $this->cacheBackend = $cache_backend;
    $this->hostnameFilterService = $hostname_filter_service;
  }

  /**
   * Proxy for calling methods using magic methods.
   *
   * @param string $name
   *   The method name.
   * @param array $arguments
   *   The arguments to pass to the method.
   *
   * @return mixed
   *   The result of the method call.
   */
  public function __call($name, $arguments) {
    $reflection = new \ReflectionClass($this->plugin);

    if ($reflection->hasMethod($name)) {
      $method = $reflection->getMethod($name);

      if ($method->isPublic()) {
        return $this->wrapperCall($method, $arguments);
      }
    }

    if (!method_exists($this->plugin, $name)) {
      throw new AiOperationTypeMissingException("Method {$name} does not exist on provider " . $this->plugin->getPluginId());
    }
  }

  /**
   * All wrapper methods around each call.
   *
   * @param \ReflectionMethod $method
   *   The method to call.
   * @param array $arguments
   *   The arguments to pass to the method.
   *
   * @return mixed
   *   The result of the method call.
   */
  protected function wrapperCall(\ReflectionMethod $method, $arguments) {
    // Get the operation type trigger methods.
    $proxiedMethods = $this->getOperationTypeTriggerMethods(get_class($this->plugin));
    // Set the operation type from the method name.
    $operation_type = $this->camelToSnake($method->getName());
    // If the method is not a trigger method, just call it.
    if (!in_array($method->getName(), $proxiedMethods)) {
      // Special cases.
      switch ($method->getName()) {
        // Special add on for the configured models.
        case 'getConfiguredModels':
          return $this->resetConfiguredModels($method->invokeArgs($this->plugin, $arguments), $arguments);

        // Make sure to cache the api definition.
        case 'getApiDefinition':
          return $this->cacheApiDefinition($arguments);
      }
      return $method->invokeArgs($this->plugin, $arguments);
    }

    // If input is not set, input is model ID.
    if (!isset($arguments[1])) {
      $arguments[1] = $arguments[0];
      $arguments[0] = NULL;
    }

    // Tags might not always be set.
    if (!isset($arguments[2])) {
      $arguments[2] = [];
    }

    // The model is missing completely.
    if (!is_string($arguments[1])) {
      throw new AiBadRequestException('Model ID is missing in your request.');
    }

    // Normalize the configuration.
    $this->plugin->configuration = $this->plugin->normalizeConfiguration($operation_type, $arguments[1]);

    // Set some default tags.
    $this->plugin->resetTags();
    $this->plugin->setTag($operation_type);
    foreach ($arguments[2] as $tag) {
      $this->plugin->setTag($tag);
    }

    // Set input debug data on the plugin.
    if (isset($arguments[0]) && $arguments[0] instanceof InputInterface) {
      foreach ($arguments[0]->getDebugData() as $key => $value) {
        $this->plugin->setDebugData($key, $value);
      }
    }

    // Temporary fix until 2.0.0, to move the streamed chat into the input.
    // And also do the reverse for the providers that might not have updated.
    // @todo Remove in 2.0.0.
    if (is_bool($this->plugin->isStreamedOutput()) && $this->plugin->isStreamedOutput() && isset($arguments[0]) && $arguments[0] instanceof ChatInput) {
      $arguments[0]->setStreamedOutput($this->plugin->isStreamedOutput());
    }
    if ($this->plugin->isStreamedOutput() !== NULL && isset($arguments[0]) && $arguments[0] instanceof ChatInput && is_bool($arguments[0]->isStreamedOutput())) {
      $this->plugin->streamedOutput($arguments[0]->isStreamedOutput());
    }

    // Temporary fix until 2.0.0, to move the system role into the input.
    // And also do the reverse for the providers that might not have updated.
    // @todo Remove in 2.0.0.
    if (!empty($this->plugin->getChatSystemRole()) && isset($arguments[0]) && $arguments[0] instanceof ChatInput) {
      $arguments[0]->setSystemPrompt($this->plugin->getChatSystemRole());
    }
    if (empty($this->plugin->getChatSystemRole()) && isset($arguments[0]) && $arguments[0] instanceof ChatInput && !empty($arguments[0]->getSystemPrompt())) {
      $this->plugin->setChatSystemRole($arguments[0]->getSystemPrompt());
    }

    // Create a unique event id.
    $event_id = $this->uuid->generate();

    // Invoke the pre generate response event.
    $pre_generate_event = new PreGenerateResponseEvent(
      requestThreadId: $event_id,
      providerId: $this->plugin->getPluginId(),
      operationType: $operation_type,
      configuration: $this->plugin->configuration,
      input: $arguments[0],
      modelId: $arguments[1],
      tags: $this->plugin->getTags(),
      debugData: $this->plugin->getDebugData()
    );
    // Too not have breaking changes, it can't be in the constructor and check.
    if (method_exists($pre_generate_event, 'setRequestParentId') && $this->requestParentId) {
      $pre_generate_event->setRequestParentId($this->requestParentId);
    }

    $this->eventDispatcher->dispatch($pre_generate_event, PreGenerateResponseEvent::EVENT_NAME);

    // If a third party forces response output object, return it.
    if ($pre_generate_event->getForcedOutputObject()) {
      return $pre_generate_event->getForcedOutputObject();
    }

    // Get the possible new auth, configuration and input from the event.
    $this->plugin->configuration = $pre_generate_event->getConfiguration();
    $arguments[0] = $pre_generate_event->getInput();
    // Only set the authentication if it is set.
    if ($pre_generate_event->getAuthentication()) {
      $this->plugin->setAuthentication($pre_generate_event->getAuthentication());
    }

    // Handle any changes to the tags made in the event.
    $this->plugin->resetTags();

    foreach ($pre_generate_event->getTags() as $tag) {
      $this->plugin->setTag($tag);
    }

    // Apply HostnameFilterDto BEFORE invoking the provider so that strings
    // built inside the plugin (notably ToolsPropertyResult::setValue, which
    // runs filterText eagerly during ChatMessage::fromArray) honour the
    // per-call override. Snapshot the singleton's previous state so we can
    // restore it in finally — otherwise overrides leak between calls.
    $hostname_snapshot = NULL;
    if ($arguments[0] instanceof ChatInput && $arguments[0]->getHostnameFilter()) {
      $hostname_snapshot = $this->hostnameFilterService->snapshotSettings();
      $this->hostnameFilterService->applySettings($arguments[0]->getHostnameFilter());
    }

    try {
      try {
        $response = $method->invokeArgs($this->plugin, $arguments);
      }
      // Catch one of the known exceptions.
      catch (ClientExceptionInterface | AiResponseErrorException | AiMissingFeatureException | AiQuotaException | AiRateLimitException | AiUnsafePromptException | AiRequestErrorException | \Exception $e) {
        // Wrap raw HTTP client exceptions so downstream code and event
        // subscribers always receive a typed AiBadRequestException, preserving
        // the original exception as the previous for full stack traces.
        $exception = $e instanceof ClientExceptionInterface ? new AiBadRequestException('Error invoking client: ' . $e->getMessage(), 0, $e) : $e;
        // Dispatch the exception event for customization and logging, carrying
        // the request context so failover subscribers know which provider,
        // model and input failed.
        $event = new AiExceptionEvent(
          exception: $exception,
          requestThreadId: $event_id,
          providerId: $this->plugin->getPluginId(),
          operationType: $operation_type,
          configuration: $this->plugin->configuration ?? [],
          input: $arguments[0] ?? NULL,
          modelId: $arguments[1] ?? '',
          tags: $this->plugin->getTags(),
          debugData: $this->plugin->getDebugData(),
        );
        $this->eventDispatcher->dispatch($event);
        // If a subscriber forced a response output object, return it instead of
        // throwing. The subscriber providing the forced output is responsible '
        // for ensuring that output is safe and filtered, matching the same
        // design as the PreGenerateResponseEvent early return above.
        if ($forced = $event->getForcedOutputObject()) {
          return $forced;
        }
        // Throw the possibly customized exception.
        throw $event->getException();
      }

      // Invoke the post generate response event.
      $post_generate_event = new PostGenerateResponseEvent(
        requestThreadId: $event_id,
        providerId: $this->plugin->getPluginId(),
        operationType: $operation_type,
        configuration: $this->plugin->configuration,
        input: $arguments[0],
        modelId: $arguments[1],
        output: $response,
        tags: $this->plugin->getTags(),
        debugData: $this->plugin->getDebugData(),
        metadata: $pre_generate_event->getAllMetadata()
      );
      // Too not have breaking changes, it can't be in the constructor.
      if (method_exists($post_generate_event, 'setRequestParentId') && $this->requestParentId) {
        $post_generate_event->setRequestParentId($this->requestParentId);
      }
      // If the output is a none-streamed ChatOutput we apply host validation.
      // The DTO (if any) is already applied on the singleton at this point.
      if ($response instanceof ChatOutput && $response->getNormalized() instanceof ChatMessage) {
        // Filter the content.
        $new_text = $this->hostnameFilterService->filterText($response->getNormalized()->getText());
        $response->getNormalized()->setText($new_text);
      }
      $this->eventDispatcher->dispatch($post_generate_event, PostGenerateResponseEvent::EVENT_NAME);
      // Get a potential new response from the event.
      $response = $post_generate_event->getOutput();

      // Since we need to attach events on streaming responses as well.
      if ($response->getNormalized() instanceof StreamedChatMessageIteratorInterface) {
        $this->attachStreamMetadata(
          streamed: $response->getNormalized(),
          event_id: $event_id,
          input: $arguments[0] ?? NULL,
          provider_id: $this->plugin->getPluginId(),
          model_id: $arguments[1] ?? NULL,
          provider_configuration: $this->plugin->configuration ?? NULL,
          tags: $this->plugin->getTags() ?? []
        );
      }

      // Return the response.
      return $response;
    }
    finally {
      // Restore the singleton HostnameFilter state so a per-call DTO never
      // leaks into subsequent calls. Runs on success and on exception.
      if ($hostname_snapshot !== NULL) {
        $this->hostnameFilterService->restoreSettings($hostname_snapshot);
      }
    }
  }

  /**
   * Proxy for getting properties.
   *
   * @param string $name
   *   The property name.
   *
   * @return mixed
   *   The property value.
   */
  public function __get($name) {
    // We need to be able to access properties of the plugin using magic
    // methods, so we proxy the call to the plugin.
    // @todo In 2.0.0 change the architecture so we don't need to use magic
    // methods.
    return $this->plugin->$name;
  }

  /**
   * Proxy for setting properties.
   *
   * @param string $name
   *   The property name.
   * @param mixed $value
   *   The property value.
   */
  public function __set($name, $value) {
    // We need to be able to access properties of the plugin using magic
    // methods, so we proxy the call to the plugin.
    // @todo In 2.0.0 change the architecture so we don't need to use magic
    // methods.
    $this->plugin->$name = $value;
  }

  /**
   * Gets the parent id.
   *
   * @return string
   *   The parent id.
   */
  public function getRequestParentId(): string {
    return $this->requestParentId;
  }

  /**
   * Sets the parent id.
   *
   * @param string $request_parent_id
   *   The parent id.
   */
  public function setRequestParentId(string $request_parent_id) {
    $this->requestParentId = $request_parent_id;
  }

  /**
   * Cache the API definition.
   *
   * @param array $arguments
   *   The arguments.
   *
   * @return array
   *   The API definition.
   */
  public function cacheApiDefinition(array $arguments): array {
    $cache_id = 'ai:api_definition:' . $this->plugin->getPluginId();
    if ($cache = $this->cacheBackend->get($cache_id)) {
      return $cache->data;
    }
    $defintion = $this->plugin->getApiDefinition($arguments);
    $this->cacheBackend->set($cache_id, $defintion, CacheBackendInterface::CACHE_PERMANENT);
    return $defintion;
  }

  /**
   * Reset the configured models.
   *
   * @param array $models
   *   The models.
   * @param array $arguments
   *   The operation type and the capabilities.
   *
   * @return array
   *   The models.
   */
  public function resetConfiguredModels(array $models, array $arguments): array {
    // Load all the extra models.
    $config = $this->plugin->getModelsConfig();
    $plugin_id = $this->plugin->getPluginId();
    if (empty($arguments[0]) || empty($config) || empty($config[$plugin_id]) || empty($config[$plugin_id][$arguments[0]])) {
      return $models;
    }
    $provider_config = $config[$plugin_id][$arguments[0]];
    foreach ($provider_config as $model_id => $model) {
      // Override.
      if (isset($models[$model_id]) && !empty($model['label'])) {
        $models[$model_id] = $model['label'];
      }
      // Add.
      elseif (!empty($model['label'])) {
        $models[$model_id] = $model['label'];
      }
    }
    return $models;
  }

  /**
   * Gets the providers module name.
   *
   * @return string
   *   The module name.
   */
  public function getModuleDataName(): string {
    if (preg_match('/^Drupal\\\\([a-zA-Z_]+)\\\\/', $this->plugin::class, $matches)) {
      return $matches[1];
    }
    return '';
  }

  /**
   * We have to figure out the operation type trigger methods.
   *
   * @param string $className
   *   The class name.
   *
   * @return array
   *   List of operation type trigger methods.
   */
  public function getOperationTypeTriggerMethods(string $className): array {
    $reflectionClass = new \ReflectionClass($className);
    $methods = $reflectionClass->getMethods();

    $interfaces = $reflectionClass->getInterfaces();

    $methodInterfaces = [];

    foreach ($methods as $method) {
      $methodName = $method->getName();

      foreach ($interfaces as $interface) {
        if ($interface->hasMethod($methodName)) {
          // Get the parent interface.
          foreach ($interface->getInterfaces() as $parentInterface) {
            // Only run if its the actual trigger method name of the interface.
            if (
              isset($parentInterface->name) && OperationTypeInterface::class === $parentInterface->name &&
              str_replace('interface', '', strtolower($interface->getShortName())) == strtolower($methodName)) {
              $methodInterfaces[] = $methodName;
            }
          }
        }
      }
    }

    return $methodInterfaces;
  }

  /**
   * Convert camel case to snake case.
   *
   * @param string $camelCase
   *   The camel case string.
   *
   * @return string
   *   The snake case string.
   */
  protected function camelToSnake($camelCase) {
    $pattern = '/(?<=\\w)(?=[A-Z])|(?<=[a-z])(?=[0-9])/';
    $snakeCase = preg_replace($pattern, '_', $camelCase);
    return strtolower($snakeCase);
  }

}
