<?php

namespace Drupal\ai_automators\PluginBaseClasses;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ai\OperationType\Embeddings\EmbeddingsInput;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for Vector Search Entity Reference automator type plugins.
 */
abstract class SearchToReference extends RuleBase {

  use DependencySerializationTrait;

  /**
   * The VDB provider manager.
   *
   * @var \Drupal\ai\AiVdbProviderManagerInterface
   */
  protected $vdbProviderManager;

  /**
   * The AI provider manager.
   *
   * @var \Drupal\ai\AiProviderManagerInterface
   */
  protected $aiProviderManager;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->vdbProviderManager = $container->get('ai.vdb_provider');
    $instance->aiProviderManager = $container->get('ai.provider');
    $instance->moduleHandler = $container->get('module_handler');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->configFactory = $container->get('config.factory');
    $instance->currentUser = $container->get('current_user');
    return $instance;
  }

  /**
   * {@inheritDoc}
   */
  public function ruleIsAllowed(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition) {
    // Only enable this if AI Search is enabled.
    if (!$this->moduleHandler->moduleExists('ai_search')) {
      return FALSE;
    }
    // Check so the user has access to manage search api.
    if (!$this->currentUser->hasPermission('administer search_api')) {
      return FALSE;
    }
    // Checks so one enabled index exists.
    $query = $this->entityTypeManager->getStorage('search_api_index')->getQuery();
    $query->condition('status', TRUE);
    $query->range(0, 1);
    if (!$query->execute()) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function extraFormFields(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, FormStateInterface $formState, array $defaultValues = []) {
    $form = [];

    // Get available search indexes.
    /** @var \Drupal\search_api\Entity\Index[] */
    $indexes = $this->entityTypeManager->getStorage('search_api_index')->loadMultiple();
    $index_options = [];
    foreach ($indexes as $index) {
      // Only show enabled indexes.
      if (!$index->status()) {
        continue;
      }
      $index_options[$index->id()] = $index->label();
    }

    $form['automator_search_index'] = [
      '#type' => 'select',
      '#title' => $this->t('Vector Search Index'),
      '#description' => $this->t('Select the vector search index to use.'),
      '#options' => $index_options,
      '#default_value' => $defaultValues['automator_search_index'] ?? NULL,
      '#required' => TRUE,
      '#weight' => 12,
    ];

    $form['automator_max_results'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum Results'),
      '#description' => $this->t('Maximum number of similar items to return.'),
      '#min' => 1,
      '#max' => 100,
      '#default_value' => $defaultValues['automator_max_results'] ?? 10,
      '#required' => TRUE,
      '#weight' => 13,
    ];

    $form['automator_offset'] = [
      '#type' => 'number',
      '#title' => $this->t('Offset'),
      '#description' => $this->t('Number of items to skip before returning results.'),
      '#min' => 0,
      '#default_value' => $defaultValues['automator_offset'] ?? 0,
      '#required' => TRUE,
      '#weight' => 14,
    ];

    $form['automator_minimum_score'] = [
      '#type' => 'number',
      '#title' => $this->t('Minimum Score'),
      '#description' => $this->t('The minimum score of the returned responses.'),
      '#min' => 0,
      '#max' => 1,
      '#step' => 0.01,
      '#default_value' => $defaultValues['automator_minimum_score'] ?? 0,
      '#required' => TRUE,
      '#weight' => 14,
    ];

    $form['automator_distinct'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Distinct entities'),
      '#description' => $this->t('Return only distinct entities.'),
      '#default_value' => $defaultValues['automator_distinct'] ?? TRUE,
      '#weight' => 15,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigValues($form, FormStateInterface $formState) {
    if (empty($formState->getValue('automator_search_index'))) {
      $formState->setErrorByName('automator_search_index', $this->t('Please select a vector search index.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function generate(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    $prompts = parent::generate($entity, $fieldDefinition, $automatorConfig);
    $prompt = reset($prompts);
    return $this->process($prompt, $entity, $fieldDefinition->getName(), $automatorConfig);
  }

  /**
   * Process the input value and return vector search results.
   *
   * @param string $value
   *   The input value to process.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being processed.
   * @param string $field_name
   *   The name of the field being processed.
   * @param array $automatorConfig
   *   The automator configuration.
   *
   * @return array
   *   An array of target entity IDs.
   */
  protected function process($value, ContentEntityInterface $entity, $field_name, array $automatorConfig) {
    $search_index = $automatorConfig['search_index'] ?? NULL;
    $max_results = $automatorConfig['max_results'] ?? 10;
    $offset = $automatorConfig['offset'] ?? 0;
    $distinct = $automatorConfig['distinct'] ?? TRUE;

    if (empty($search_index) || empty($value)) {
      return [];
    }

    try {
      /** @var \Drupal\search_api\Entity\Index $index */
      $index = $this->entityTypeManager->getStorage('search_api_index')->load($search_index);

      if (!$index) {
        throw new \Exception("Search index not found: $search_index");
      }

      if (!$index->status()) {
        throw new \Exception("Search index is not enabled: $search_index");
      }

      // Get the backend configuration.
      $server = $index->getServerInstance();
      $backend_config = $server->getBackendConfig();

      // Prepare search parameters.
      $params = [
        'database' => $backend_config['database_settings']['database_name'],
        'collection_name' => $backend_config['database_settings']['collection'],
        'output_fields' => ['id', 'drupal_entity_id', 'drupal_long_id', 'content'],
        'limit' => $max_results,
        'offset' => $offset,
      ];

      // Get embeddings.
      [$provider_id, $model_id] = explode('__', $backend_config['embeddings_engine']);
      $embedding_llm = $this->aiProviderManager->createInstance($provider_id);
      $input = new EmbeddingsInput($value);
      $params['vector_input'] = $embedding_llm->embeddings($input, $model_id)->getNormalized();

      // Get VDB client and perform search.
      $vdb_client = $this->vdbProviderManager->createInstance($backend_config['database']);
      $response = $vdb_client->vectorSearch(...$params);

      // Extract entity IDs from results.
      $target_ids = [];
      $seen_ids = [];
      foreach ($response as $match) {
        if (empty($automatorConfig['minimum_score']) || ($match['distance'] > $automatorConfig['minimum_score'])) {
          if (isset($match['drupal_entity_id'])) {
            [, $entity_id] = explode('/', $match['drupal_entity_id']);
            if (!isset($seen_ids[$entity_id]) || !$distinct) {
              $target_ids[] = ['target_id' => $entity_id];
              $seen_ids[$entity_id] = TRUE;
            }
          }
        }
      }

      return $target_ids;
    }
    catch (\Exception $e) {
      $this->logger->error('Vector search failed: @message', ['@message' => $e->getMessage()]);
      return [];
    }
  }

}
