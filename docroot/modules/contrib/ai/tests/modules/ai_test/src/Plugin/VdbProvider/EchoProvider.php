<?php

namespace Drupal\ai_test\Plugin\VdbProvider;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\AiVdbProvider;
use Drupal\ai\Base\AiVdbProviderClientBase;
use Drupal\ai\Enum\VdbSimilarityMetrics;
use Drupal\key\KeyRepositoryInterface;
use Drupal\search_api\Query\QueryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Plugin implementation of the 'Echo DB' provider.
 */
#[AiVdbProvider(
  id: 'echo_db',
  label: new TranslatableMarkup('Echo DB'),
)]
class EchoProvider extends AiVdbProviderClientBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * Constructs an override for the AiVdbClientBase class to add Milvus V2.
   *
   * @param string $pluginId
   *   Plugin ID.
   * @param mixed $pluginDefinition
   *   Plugin definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\key\KeyRepositoryInterface $keyRepository
   *   The key repository.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   */
  public function __construct(
    protected string $pluginId,
    protected mixed $pluginDefinition,
    protected ConfigFactoryInterface $configFactory,
    protected KeyRepositoryInterface $keyRepository,
    protected EventDispatcherInterface $eventDispatcher,
    protected EntityFieldManagerInterface $entityFieldManager,
    protected MessengerInterface $messenger,
    protected Request $request,
  ) {
    parent::__construct(
      $this->pluginId,
      $this->pluginDefinition,
      $this->configFactory,
      $this->keyRepository,
      $this->eventDispatcher,
      $this->entityFieldManager,
      $this->messenger,
    );
  }

  /**
   * Load from dependency injection container.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): AiVdbProviderClientBase|static {
    return new static(
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('key.repository'),
      $container->get('event_dispatcher'),
      $container->get('entity_field.manager'),
      $container->get('messenger'),
      $container->get('request_stack')->getCurrentRequest(),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(): ImmutableConfig {
    return $this->configFactory->get('system.site');
  }

  /**
   * {@inheritdoc}
   */
  public function getClient(): mixed {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function isSetup(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getCollections(string $database = 'default'): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function ping(): bool {
    // Echo provider always returns true.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function createCollection(string $collection_name, int $dimension, VdbSimilarityMetrics $metric_type = VdbSimilarityMetrics::EuclideanDistance, string $database = 'default'): void {
    // Echo provider does not create collections.
    throw new \Exception('Echo provider does not support creating collections.');
  }

  /**
   * {@inheritdoc}
   */
  public function dropCollection(string $collection_name, string $database = 'default'): void {
    // Echo provider does not drop collections.
    throw new \Exception('Echo provider does not support dropping collections.');
  }

  /**
   * {@inheritdoc}
   */
  public function getCollection(string $collection_name, string $database = 'default'): array {
    // Echo provider does not return collections.
    throw new \Exception('Echo provider does not support getting collections.');
  }

  /**
   * {@inheritdoc}
   */
  public function insertIntoCollection(string $collection_name, array $data, string $database = 'default'): void {

  }

  /**
   * {@inheritdoc}
   */
  public function deleteFromCollection(string $collection_name, array $ids, string $database = 'default'): void {

  }

  /**
   * {@inheritdoc}
   */
  public function querySearch(string $collection_name, array $output_fields, string $filters = '', int $limit = 10, int $offset = 0, string $database = 'default'): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function vectorSearch(string $collection_name, array $vector_input, array $output_fields, QueryInterface $query, string $filters = '', int $limit = 10, int $offset = 0, string $database = 'default'): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getVdbIds(string $collection_name, array $drupalIds, string $database = 'default'): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function prepareFilters(QueryInterface $query): mixed {
    return [];
  }

}
