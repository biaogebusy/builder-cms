<?php

namespace Drupal\ai_search\Backend;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ai_search\Trait\AiSearchBackendEmbeddingsEngineTrait;
use Drupal\ai_search\Trait\AiSearchBackendEmbeddingsStrategyTrait;
use Drupal\search_api\Backend\BackendPluginBase;

/**
 * Base class for Search API AI backend plugins.
 *
 * This will add some of the function that are not needed for the interface
 * with empty implementations. This adds logic for loading and storing
 * embedding engines and strategies. This uses the traits for all engine and
 * strategy logic automatically. Database engines and strategies that have to
 * extend other classes should use the trait directly.
 */
abstract class AiSearchBackendPluginBase extends BackendPluginBase {

  use AiSearchBackendEmbeddingsEngineTrait;
  use AiSearchBackendEmbeddingsStrategyTrait;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return array_merge_recursive(
      $this->defaultEngineConfiguration(),
      $this->defaultStrategyConfiguration(),
    );
  }

  /**
   * Build the form.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The configuration form.
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {

    // Pass the configuration on to the embeddings engine and strategy.
    $this->setEngineConfiguration(array_filter($this->configuration, function ($config_key) {
      return str_starts_with($config_key, 'embeddings_engine');
    }, ARRAY_FILTER_USE_KEY));
    $this->setStrategyConfiguration(array_filter($this->configuration, function ($config_key) {
      return str_starts_with($config_key, 'embedding_strategy');
    }, ARRAY_FILTER_USE_KEY));

    // Build the form for both types.
    return array_merge(
      $this->engineConfigurationForm($form, $form_state),
      $this->strategyConfigurationForm($form, $form_state)
    );
  }

}
