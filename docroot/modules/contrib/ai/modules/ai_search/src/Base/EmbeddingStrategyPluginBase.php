<?php

namespace Drupal\ai_search\Base;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Plugin\ProviderProxy;
use Drupal\ai\Utility\TextChunker;
use Drupal\ai_search\EmbeddingStrategyInterface;
use League\HTMLToMarkdown\Converter\TableConverter;
use League\HTMLToMarkdown\HtmlConverter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class to provide an embedding strategy to break content into chunks.
 */
abstract class EmbeddingStrategyPluginBase implements EmbeddingStrategyInterface, ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The Vector Database Provider ID.
   *
   * @var string
   */
  protected string $providerId;

  /**
   * The Model ID.
   *
   * @var string
   */
  protected string $modelId;

  /**
   * The chunk size.
   *
   * @var int
   */
  protected int $chunkSize;

  /**
   * If we should skip moderation (mostly NO!).
   *
   * @var bool
   */
  protected bool $skipModeration;

  /**
   * The chunk minimum overlap.
   *
   * @var int
   */
  protected int $chunkMinOverlap;

  /**
   * The EmbeddingInterface proxied via ProviderProxy.
   *
   * @var \Drupal\ai\Plugin\ProviderProxy
   */
  protected ProviderProxy $embeddingLlm;

  /**
   * Constructs a new Embedding Strategy abstract class.
   *
   * @param string $pluginId
   *   Plugin ID.
   * @param mixed $pluginDefinition
   *   Plugin definition.
   * @param \Drupal\ai\AiProviderPluginManager $aiProviderManager
   *   The AI provider plugin manager.
   * @param \League\HTMLToMarkdown\HtmlConverter $converter
   *   The html to markdown converter.
   * @param \Drupal\ai\Utility\TextChunker $textChunker
   *   The text chunker.
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Extension\ModuleExtensionList $extensionList
   *   The module extension list.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   The logger factory.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   */
  final public function __construct(
    protected string $pluginId,
    protected mixed $pluginDefinition,
    protected AiProviderPluginManager $aiProviderManager,
    protected HtmlConverter $converter,
    protected TextChunker $textChunker,
    protected EntityTypeManager $entityTypeManager,
    protected ModuleExtensionList $extensionList,
    protected ConfigFactoryInterface $configFactory,
    protected LoggerChannelFactoryInterface $loggerChannelFactory,
    protected MessengerInterface $messenger,
  ) {
    // Set the default converter settings.
    $this->converter->getConfig()->setOption('strip_tags', TRUE);
    $this->converter->getConfig()->setOption('strip_placeholder_links', TRUE);
    $this->converter->getEnvironment()->addConverter(new TableConverter());
  }

  /**
   * Initialise the settings for the embedding strategy.
   *
   * @param string $embedding_engine
   *   The embedding engine.
   * @param string $chat_model
   *   The chat model ID for token calculations.
   * @param array $configuration
   *   The embedding strategy configuration.
   */
  public function init(string $embedding_engine, string $chat_model, array $configuration): void {
    [$this->providerId, $this->modelId] = explode('__', $embedding_engine);
    $chat_model_id = $this->aiProviderManager->getModelNameFromSimpleOption($chat_model);
    $chat_model_id = $chat_model_id ?: 'gpt-3.5';
    $this->textChunker->setModel($chat_model_id);
    /** @var \Drupal\ai\OperationType\Embeddings\EmbeddingsInterface $embeddingLlm */
    $this->embeddingLlm = $this->aiProviderManager->createInstance($this->providerId);
    $this->skipModeration = !empty($configuration['skip_moderation']);
    if (!empty($configuration['chunk_size']) && is_numeric($configuration['chunk_size'])) {
      $this->chunkSize = (int) $configuration['chunk_size'];
    }
    else {
      $this->chunkSize = $this->embeddingLlm->maxEmbeddingsInput($this->modelId);
    }

    if (!empty($configuration['chunk_min_overlap'])) {
      $this->chunkMinOverlap = (int) $configuration['chunk_min_overlap'];
    }
  }

  /**
   * Load from dependency injection container.
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ): EmbeddingStrategyPluginBase|static {
    /** @var \Drupal\ai\AiProviderPluginManager $ai_provider */
    $ai_provider = $container->get('ai.provider');
    /** @var \Drupal\ai\Utility\TextChunker $text_chunker */
    $text_chunker = $container->get('ai.text_chunker');
    return new static(
      $plugin_id,
      $plugin_definition,
      $ai_provider,
      new HtmlConverter(),
      $text_chunker,
      $container->get('entity_type.manager'),
      $container->get('extension.list.module'),
      $container->get('config.factory'),
      $container->get('logger.factory'),
      $container->get('messenger'),
    );
  }

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
   * {@inheritDoc}
   */
  public function getConfigurationSubform(array $configuration): array {
    $form = [];
    if (empty($configuration)) {
      $configuration = $this->getDefaultConfigurationValues();
    }

    $form['skip_moderation'] = [
      '#title' => $this->t('Skip moderation for embeddings (only for advanced use cases).'),
      '#description' => $this->t('This means that the moderation layer will not check for any possible flagged content. Only do this when the moderation provider is different from the LLM provider for the chatbot or when you are using pure vector search without and LLM. Only check this if you are 100% sure what you are doing.'),
      '#type' => 'checkbox',
      '#default_value' => $configuration['skip_moderation'] ?? FALSE,
    ];

    $form['chunk_size'] = [
      '#title' => $this->t('Maximum chunk size allowed when breaking up larger content'),
      '#description' => $this->t('When the content is longer than this in tokens (which roughly equates to syllables when oversimplified), the content should be broken into smaller "Chunks". This setting defines how to segment or break up the larger text. When configuring Fields for this Index, the fields with the indexing option "Main Content" will be split into chunks no greater than this size. This includes any added "Contextual Content" as well as the "Title" to ensure an accurate vectorized representation of the content. More details are provided when configuring the Fields within your Index. Leave this blank to use the maximum size provided by the selected model.'),
      '#required' => FALSE,
      '#type' => 'number',
      '#default_value' => $configuration['chunk_size'] ?? '',
      '#field_suffix' => $this->t('tokens'),
    ];
    $form['chunk_size_details'] = [
      '#type' => 'details',
      '#title' => $this->t('How to select your chunk size'),
    ];
    $path = $this->extensionList->getPath('ai_search');
    $file = $path . '/assets/html/chunk-size-advice.html';
    $form['chunk_size_details']['content'] = [
      '#markup' => file_get_contents($file),
    ];
    $form['chunk_min_overlap'] = [
      '#title' => $this->t("Minimum chunk overlap for 'Main Content'"),
      '#description' => $this->t('When breaking apart the content into smaller chunks, copy a bit of the content from the previous chunk to avoid anything important being missed overall by inadvertently splitting for example mid-concept. This specifies the number of tokens to retrieve from the preceding chunk to provide that overlapping content.'),
      '#required' => TRUE,
      '#type' => 'number',
      '#default_value' => $configuration['chunk_min_overlap'] ?? '',
      '#field_suffix' => $this->t('tokens'),
    ];
    return $form;
  }

  /**
   * Returns array of default configuration values for given strategy.
   *
   * @return array
   *   List of configuration values set for given model.
   */
  public function getDefaultConfigurationValues(): array {
    return [
      'chunk_size' => 500,
      'chunk_min_overlap' => 100,
    ];
  }

}
