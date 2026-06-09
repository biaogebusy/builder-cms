<?php

namespace Drupal\ai_search\Trait;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ai\AiProviderInterface;
use Drupal\ai\Plugin\ProviderProxy;
use Drupal\search_api\LoggerTrait;

/**
 * Trait for Search API AI Embeddings Engine.
 *
 * This will add some of the function that are not needed for the interface
 * with empty implementations. This adds logic for loading and storing
 * embedding engines.
 */
trait AiSearchBackendEmbeddingsEngineTrait {

  use StringTranslationTrait;
  use LoggerTrait;

  /**
   * The configuration.
   *
   * @var array
   */
  protected array $engineConfiguration = [];

  /**
   * Sets the configuration.
   *
   * @param array $configuration
   *   The configuration.
   */
  public function setEngineConfiguration(array $configuration): void {
    $this->engineConfiguration = $configuration;
  }

  /**
   * Set the embeddings engine configuration.
   *
   * @return array
   *   The configuration.
   */
  public function defaultEngineConfiguration(): array {
    // Keys must start with 'embeddings_engine',
    // see AiSearchBackendPluginBase::buildConfigurationForm().
    return [
      'embeddings_engine' => NULL,
      'embeddings_engine_configuration' => [
        'dimensions' => 0,
      ],
    ];
  }

  /**
   * Builds the engine part of the configuration form.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The form array.
   */
  public function engineConfigurationForm(array $form, FormStateInterface $form_state): array {
    // It might be a Sub form state, so we need to get the complete form state.
    if ($form_state instanceof SubformStateInterface) {
      $form_state = $form_state->getCompleteFormState();
    }
    if (empty($this->engineConfiguration)) {
      $this->engineConfiguration = $this->defaultEngineConfiguration();
    }

    /** @var \Drupal\Core\Entity\Form $form_object */
    $form_object = $form_state->getFormObject();
    $entity = $form_object->getEntity();

    $form['embeddings_engine'] = [
      '#type' => 'select',
      '#title' => $this->t('Embeddings Engine'),
      '#options' => $this->getEmbeddingEnginesOptions(),
      '#required' => TRUE,
      '#default_value' => $this->getConfiguration()['embeddings_engine'] ?? $this->defaultEngineConfiguration()['embeddings_engine'],
      '#description' => $this->t("The service to use for generating the embeddings (the vectorized representations of each chunk of your content). If you change this, everything will be needed to be reindexed. Larger models tend to provide more complete representations of the content and therefore more accurate results, but are however slower (and for paid models, typically with a slightly higher cost). The general idea here is that the engine creates vectorized representations of your chunks of content, then vectorize the user's query in the same manner (i.e., using the same engine) to mathematically compare the vectors and find the nearest matches."),
      '#weight' => 1,
      '#ajax' => [
        'callback' => [$this, 'updateEmbeddingEngineConfigurationForm'],
        'wrapper' => 'embedding-engine-configuration-wrapper',
        'method' => 'replaceWith',
        'effect' => 'fade',
      ],
      // This is disabled if its editing.
      '#disabled' => !$entity->isNew(),
    ];

    $form['embeddings_engine_configuration'] = [
      '#type' => 'details',
      '#open' => FALSE,
      '#attributes' => ['id' => 'embedding-engine-configuration-wrapper'],
      '#title' => $this->t('Advanced Embeddings Engine Configuration'),
      '#weight' => 5,
    ];

    $form['embeddings_engine_configuration']['set_dimensions'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Set Dimensions Manually'),
      '#description' => $this->t('This is for advanced usage, when you want to use custom or a variable embeddings engines. With variable embeddings engine you can choose a smaller dimension to choose performance and price over quality. Once the index has been created, the dimensions can no longer be changed or overridden.'),
      '#default_value' => FALSE,
      // This is disabled if its editing.
      '#disabled' => !$entity->isNew(),
    ];

    $form['embeddings_engine_configuration']['dimensions'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of dimensions'),
      '#description' => $this->t('The number of dimensions for the embeddings. This is essentially the amount of information to store about each chunk of content. More information (more dimensions) leads to more accurate results, but slower performance. Depending on the provider, more dimensions may also have a higher cost.'),
      '#default_value' => $this->engineConfiguration['embeddings_engine_configuration']['dimensions'] ?? '',
      '#required' => TRUE,
      '#field_suffix' => $this->t('dimensions'),
      '#states' => [
        'disabled' => [
          ':input[name="backend_config[embeddings_engine_configuration][set_dimensions]"]' => ['checked' => FALSE],
        ],
      ],
    ];

    // If the embeddings engine is set, add the configuration form.
    if (!empty($this->engineConfiguration['embeddings_engine']) || $form_state->getValue('embeddings_engine')) {
      $plugin_manager = \Drupal::service('ai.provider');
      $parts = explode('__', $this->engineConfiguration['embeddings_engine'] ?? $form_state->get('embeddings_engine'));
      try {
        $dimensions = $plugin_manager->createInstance($parts[0])->embeddingsVectorSize($parts[1]);
        $form['embeddings_engine_configuration']['dimensions']['#value'] = $dimensions;
      }
      catch (\Exception $e) {
        \Drupal::messenger()->addError('Could not load the embeddings engine to get the dimensions. Please check the configuration.' . $e->getMessage());
      }
    }

    return $form;
  }

  /**
   * Load the embeddings engine with a configuration.
   *
   * @return \Drupal\ai\AiProviderInterface
   *   The embeddings engine.
   */
  public function loadEmbeddingsEngine(): AiProviderInterface|ProviderProxy {
    $plugin_manager = \Drupal::service('ai.provider');
    $parts = explode('__', $this->engineConfiguration['embeddings_engine']);
    return $plugin_manager->createInstance($parts[0]);
  }

  /**
   * Returns the embeddings engine.
   *
   * @return string
   *   The embeddings engine.
   */
  public function getEmbeddingsEngine(): string {
    return $this->engineConfiguration['embeddings_engine'];
  }

  /**
   * Returns all available embedding engines as options.
   *
   * @return array
   *   The embedding engines.
   */
  public function getEmbeddingEnginesOptions(): array {
    $options = [];
    $plugin_manager = \Drupal::service('ai.provider');
    foreach ($plugin_manager->getProvidersForOperationType('embeddings') as $id => $definition) {
      $provider = $plugin_manager->createInstance($id);
      try {
        foreach ($provider->getConfiguredModels('embeddings') as $model => $label) {
          $options[$id . '__' . $model] = $definition['label']->__toString() . ' | ' . $label;
        }
      }
      catch (\Exception $e) {
        $this->logException($e, '%type while getting models for provider %provider: @message in %function (line %line of %file).', [
          '%provider' => $id,
        ]);
      }
    }
    // Send a warning message if there are no available embedding engines.
    if (empty($options)) {
      \Drupal::messenger()->addWarning('No embedding engines available. Please install and enable an embedding engine module before continuing.');
    }
    return $options;
  }

  /**
   * Callback to update the embedding engine configuration form.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The updated form.
   */
  public function updateEmbeddingEngineConfigurationForm(array $form, FormStateInterface $form_state): array {
    return $form['backend_config']['embeddings_engine_configuration'];
  }

}
