<?php

namespace Drupal\ai_search\Trait;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ai\AiProviderInterface;

/**
 * Trait for Search API AI Embeddings Strategy.
 *
 * This will add some of the function that are not needed for the interface
 * with empty implementations. This adds logic for loading and storing
 * embedding strategies.
 */
trait AiSearchBackendEmbeddingsStrategyTrait {

  use StringTranslationTrait;

  /**
   * The configuration.
   *
   * @var array
   */
  protected array $strategyConfiguration = [];

  /**
   * Sets the configuration.
   *
   * @param array $configuration
   *   The configuration.
   */
  public function setStrategyConfiguration(array $configuration): void {
    $this->strategyConfiguration = $configuration;
  }

  /**
   * Set the embeddings strategy configuration.
   *
   * @return array
   *   The configuration.
   */
  public function defaultStrategyConfiguration(): array {
    // Keys must start with 'embedding_strategy',
    // see AiSearchBackendPluginBase::buildConfigurationForm().
    return [
      'embedding_strategy' => 'contextual_chunks',
      'embedding_strategy_configuration' => [],
    ];
  }

  /**
   * Builds the strategy part of the configuration form.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The form array.
   */
  public function strategyConfigurationForm(array $form, FormStateInterface $form_state): array {
    // It might be a Sub form state, so we need to get the complete form state.
    if ($form_state instanceof SubformStateInterface) {
      $form_state = $form_state->getCompleteFormState();
    }
    if (empty($this->strategyConfiguration)) {
      $this->strategyConfiguration = $this->defaultStrategyConfiguration();
    }

    $form['embedding_strategy_container'] = [
      '#type' => 'details',
      '#open' => FALSE,
      '#title' => $this->t('Advanced Embeddings Strategy Configuration'),
      '#weight' => 6,
    ];

    $form['embedding_strategy_container']['embedding_strategy'] = [
      '#type' => 'select',
      '#title' => $this->t('Strategy for breaking content into smaller chunks for indexing'),
      '#options' => $this->getEmbeddingStrategiesOptions(),
      '#required' => TRUE,
      '#default_value' => $this->getConfiguration()['embedding_strategy'] ?? $this->defaultStrategyConfiguration()['embedding_strategy'],
      '#description' => $this->t('The service to use for embeddings. If you change this, everything will be needed to be re-indexed. The Embeddings Strategy decides how to break apart the contents into smaller chunks to be vectorized. The strategy impacts the information stored in the vectors and therefore the accuracy level when retrieving results.'),
      '#ajax' => [
        'callback' => [$this, 'updateEmbeddingStrategyConfigurationForm'],
        'wrapper' => 'embedding-strategy-configuration-wrapper',
        'method' => 'replaceWith',
        'effect' => 'fade',
      ],
    ];
    $rows = [];
    foreach ($this->getEmbeddingStrategyDetails() as $strategy) {
      $rows[] = [
        'label' => $strategy['label'],
        'description' => $strategy['description'],
      ];
    }
    $form['embedding_strategy_container']['embedding_strategy_details'] = [
      '#type' => 'table',
      '#header' => [
        ['data' => t('Strategy name')],
        ['data' => t('Explanation')],
      ],
      '#rows' => $rows,
    ];

    $form['embedding_strategy_container']['embedding_strategy_configuration'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Embedding Strategy Configuration'),
      '#attributes' => ['id' => 'embedding-strategy-configuration-wrapper'],
      '#weight' => 15,
    ];

    // If the embeddings strategy is set, add the configuration form.
    if (!empty($this->strategyConfiguration['embedding_strategy']) || $form_state->get('embedding_strategy')) {
      $plugin_manager = \Drupal::service('ai_search.embedding_strategy');
      $embedding_strategy = $this->strategyConfiguration['embedding_strategy'] ?? $form_state->get('embedding_strategy');
      if ($embedding_strategy) {
        $subform = $plugin_manager->createInstance($embedding_strategy)->getConfigurationSubform($this->strategyConfiguration['embedding_strategy_configuration'] ?? []);
        foreach ($subform as $key => $element) {
          $form['embedding_strategy_container']['embedding_strategy_configuration'][$key] = $element;
        }
      }
    }

    return $form;
  }

  /**
   * Load the embedding strategy with a configuration.
   *
   * @return Drupal\ai_search\EmbeddingStrategyInterface
   *   The embedding strategy.
   */
  public function loadEmbeddingsStrategy(): AiProviderInterface {
    $plugin_manager = \Drupal::service('ai_search.embedding_strategy');
    return $plugin_manager->createInstance($this->strategyConfiguration['embedding_strategy']);
  }

  /**
   * Returns the embeddings strategy.
   *
   * @return string
   *   The embedding strategy.
   */
  public function getEmbeddingsStrategy(): string {
    return $this->strategyConfiguration['embedding_strategy'];
  }

  /**
   * Returns all available embedding strategies as options.
   *
   * @return array
   *   The embedding strategies.
   */
  public function getEmbeddingStrategiesOptions(): array {
    return \Drupal::service('ai_search.embedding_strategy')->getStrategies();
  }

  /**
   * Returns all available embedding strategy details for help text.
   *
   * @return array
   *   The embedding strategies.
   */
  public function getEmbeddingStrategyDetails(): array {
    return \Drupal::service('ai_search.embedding_strategy')->getStrategyDetails();
  }

  /**
   * Callback to update the embedding strategy configuration form.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The updated form.
   */
  public function updateEmbeddingStrategyConfigurationForm(array $form, FormStateInterface $form_state): array {
    return $form['backend_config']['embedding_strategy_container']['embedding_strategy_configuration'];
  }

}
