<?php

namespace Drupal\ai_assistant_api\Base;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\ai_assistant_api\AiAssistantActionInterface;
use Drupal\ai_assistant_api\AiAssistantInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for AI assistant actions.
 */
abstract class AiAssistantActionBase implements AiAssistantActionInterface, ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The assistant.
   *
   * @var \Drupal\ai_assistant_api\AiAssistantInterface
   */
  protected AiAssistantInterface $assistant;

  /**
   * The AI provider.
   *
   * @var \Drupal\ai\AiProviderInterface|\Drupal\ai\Plugin\ProviderProxy
   */
  protected $aiProvider;

  /**
   * The Temp store factory.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected PrivateTempStoreFactory $tempStoreFactory;

  /**
   * Configuration.
   *
   * @var array
   */
  protected array $configuration = [];

  /**
   * The threads id.
   *
   * @var string
   */
  protected string $threadId;

  /**
   * The messages thread.
   *
   * @var array
   */
  protected array $messages = [];

  /**
   * Constructor.
   */
  public function __construct(array $configuration, PrivateTempStoreFactory $tempStoreFactory) {
    $this->configuration = $configuration;
    $this->tempStoreFactory = $tempStoreFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $container->get('tempstore.private')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration($configuration) {
    $this->configuration = $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setMessages(array $messages): void {
    $this->messages = $messages;
  }

  /**
   * {@inheritdoc}
   */
  public function setAiProvider($provider): void {
    $this->aiProvider = $provider;
  }

  /**
   * {@inheritdoc}
   */
  public function setAssistant(AiAssistantInterface $assistant): void {
    $this->assistant = $assistant;
  }

  /**
   * {@inheritdoc}
   */
  public function setThreadId(string $thread_id): void {
    $this->threadId = $thread_id;
  }

  /**
   * {@inheritdoc}
   */
  public function listUsageInstructions(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function triggerRollback(): void {}

  /**
   * Get the private tempstore for AI Assistant.
   *
   * @return \Drupal\Core\TempStore\PrivateTempStore
   *   The tempstore.
   */
  public function getTempStore() {
    return $this->tempStoreFactory->get('ai_assistant_api');
  }

  /**
   * Get action context from history.
   *
   * @param string $key
   *   The key to get the context from.
   *
   * @return array
   *   The data.
   */
  public function getActionContext(string $key): array {
    $session = $this->getAllActionContexts();
    return $session[$key] ?? [];
  }

  /**
   * Get all the context from history.
   *
   * @return array
   *   The data.
   */
  public function getAllActionContexts(): array {
    if ($this->assistant->get('allow_history') == 'session') {
      $session = $this->getTempStore()->get($this->threadId);
      return $session['contexts'] ?? [];
    }
    return [];
  }

  /**
   * Store action context for history.
   *
   * @param string $key
   *   The key to set the context to.
   * @param mixed $data
   *   The data.
   */
  public function storeActionContext(string $key, mixed $data) {
    $session = $this->getTempStore()->get($this->threadId);
    $session['contexts'][$key][] = $data;
    $this->getTempStore()->set($this->threadId, $session);
  }

  /**
   * Sets output context.
   *
   * @param string $key
   *   The key to set the context to.
   * @param string $context
   *   The context.
   */
  public function setOutputContext(string $key, string $context) {
    $session = $this->getTempStore()->get($this->threadId);
    if (!isset($session['output_contexts'][$key]) || !is_array($session['output_contexts'][$key])) {
      $session['output_contexts'][$key] = [];
    }
    $session['output_contexts'][$key][] = $context;

    $this->getTempStore()->set($this->threadId, $session);
  }

  /**
   * Get output token.
   *
   * @param string $key
   *   The key to get the context from.
   *
   * @return string
   *   The data.
   */
  public function getOutputToken(string $key): string {
    $session = $this->getTempStore()->get($this->threadId);
    return $session['output_tokens'][$key] ?? '';
  }

  /**
   * Get all the output tokens.
   *
   * @return array
   *   The data.
   */
  public function getAllOutputTokens(): array {
    $session = $this->getTempStore()->get($this->threadId);
    return $session['output_tokens'] ?? [];
  }

  /**
   * Set output tokens.
   *
   * @param string $key
   *   The key to set the context to.
   * @param string $context
   *   The context.
   */
  public function setOutputTokens(string $key, string $context) {
    $session = $this->getTempStore()->get($this->threadId);
    if (!isset($session['output_tokens'][$key]) || !is_array($session['output_tokens'][$key])) {
      $session['output_tokens'][$key] = [];
    }
    $session['output_tokens'][$key][] = $context;

    $this->getTempStore()->set($this->threadId, $session);
  }

  /**
   * Reset the out structure.
   */
  public function resetStructuredResults() {
    $session = $this->getTempStore()->get($this->threadId);
    $session['structured_results'] = [];
    $this->getTempStore()->set($this->threadId, $session);
  }

  /**
   * Set output structure results.
   *
   * @param string $key
   *   The key to set the context to.
   * @param array $context
   *   The context.
   */
  public function setStructuredResults(string $key, array $context) {
    $session = $this->getTempStore()->get($this->threadId);
    if (!isset($session['structured_results'][$key]) || !is_array($session['structured_results'][$key])) {
      $session['structured_results'][$key] = [];
    }
    $session['structured_results'][$key][] = $context;

    $this->getTempStore()->set($this->threadId, $session);
  }

  /**
   * {@inheritdoc}
   */
  public function getFunctionCallSchema(): array {
    return [
      'action' => [
        'type' => 'string',
        'description' => $this->t('The action to call.'),
      ],
      'query' => [
        'type' => 'string',
        'description' => $this->t('The query to the action.'),
      ],
    ];
  }

}
