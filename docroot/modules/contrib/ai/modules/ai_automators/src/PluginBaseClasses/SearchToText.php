<?php

namespace Drupal\ai_automators\PluginBaseClasses;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ai\OperationType\Embeddings\EmbeddingsInput;

/**
 * Base class for Search to Text automator type plugins.
 */
abstract class SearchToText extends SearchToReference {

  use DependencySerializationTrait;

  /**
   * {@inheritdoc}
   */
  public function extraFormFields(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, FormStateInterface $formState, array $defaultValues = []) {
    $form = parent::extraFormFields($entity, $fieldDefinition, $formState, $defaultValues);

    // Add a warning about permissions.
    $form['automator_permissions_warning'] = [
      '#type' => 'markup',
      '#markup' => $this->t('⚠️ Note that this will save the data found in this field and that any user that has access to view this field will see the content, independent on content permissions or index permissions.'),
      '#weight' => 10,
    ];

    // Add AJAX to the search index field.
    $form['automator_search_index']['#ajax'] = [
      'callback' => [$this, 'updateOutputFieldOptions'],
      'wrapper' => 'output-field-wrapper',
      'event' => 'change',
    ];

    // Get the selected index to load available fields.
    $index_id = $formState->getValue('automator_search_index') ?? ($defaultValues['automator_search_index'] ?? NULL);

    $form['automator_output_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Output Field'),
      '#description' => $this->t('Select the field from the search index to use as output.'),
      '#options' => $this->getFieldOptions($index_id),
      '#default_value' => $defaultValues['automator_output_field'] ?? NULL,
      '#required' => TRUE,
      '#weight' => 15,
      '#prefix' => '<div id="output-field-wrapper">',
      '#suffix' => '</div>',
    ];

    return $form;
  }

  /**
   * Get field options for the given search index.
   *
   * @param string|null $index_id
   *   The search index ID.
   *
   * @return array
   *   Array of field options.
   */
  protected function getFieldOptions(?string $index_id): array {
    $field_options = [
      'content' => $this->t('Content'),
    ];

    if ($index_id) {
      /** @var \Drupal\search_api\Entity\Index $index */
      $index = $this->entityTypeManager->getStorage('search_api_index')->load($index_id);
      if ($index && $index->status()) {
        $config = $this->configFactory->get('ai_search.index.' . $index_id);
        $indexing_options = $config->getRawData()['indexing_options'] ?? [];
        foreach ($index->getFields() as $field_id => $field) {
          // Skip the content field as it's already added.
          if ($field_id === 'content') {
            continue;
          }

          // Check if this field has indexing_option set to "attributes".
          if (isset($indexing_options[$field_id]['indexing_option'])
              && $indexing_options[$field_id]['indexing_option'] === 'attributes') {
            $field_options[$field_id] = $field->getLabel();
          }
        }
      }
    }

    return $field_options;
  }

  /**
   * Ajax callback to update output field options.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function updateOutputFieldOptions(array &$form, FormStateInterface $form_state) {
    // Get the new index and its fields.
    $index_id = $form_state->getValue('automator_search_index');
    $field_options = $this->getFieldOptions($index_id);

    // Rebuild the output field element.
    $element = [
      '#type' => 'select',
      '#title' => $this->t('Output Field'),
      '#description' => $this->t('Select the field from the search index to use as output.'),
      '#options' => $field_options,
      '#required' => TRUE,
      '#default_value' => NULL,
      '#prefix' => '<div id="output-field-wrapper">',
      '#suffix' => '</div>',
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigValues($form, FormStateInterface $formState) {
    parent::validateConfigValues($form, $formState);

    if (empty($formState->getValue('automator_output_field'))) {
      $formState->setErrorByName('automator_output_field', $this->t('Please select an output field.'));
    }
  }

  /**
   * Process the vector search results to extract the specified field text.
   */
  protected function process($value, ContentEntityInterface $entity, $field_name, array $automatorConfig) {
    try {
      /** @var \Drupal\search_api\Entity\Index $index */
      $index = $this->entityTypeManager->getStorage('search_api_index')->load($automatorConfig['search_index']);

      if (!$index) {
        throw new \Exception("Search index not found: {$automatorConfig['search_index']}");
      }

      if (!$index->status()) {
        throw new \Exception("Search index is not enabled: {$automatorConfig['search_index']}");
      }

      // Configurations.
      $max_results = $automatorConfig['max_results'] ?? 10;
      $offset = $automatorConfig['offset'] ?? 0;

      // Get the backend configuration.
      $server = $index->getServerInstance();
      $backend_config = $server->getBackendConfig();

      // Prepare search parameters.
      $params = [
        'database' => $backend_config['database_settings']['database_name'],
        'collection_name' => $backend_config['database_settings']['collection'],
        'output_fields' => ['id', 'drupal_entity_id', $automatorConfig['output_field']],
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

      // Extract field values from results.
      $output = [];
      foreach ($response as $match) {
        if (empty($automatorConfig['minimum_score']) || ($match['distance'] > $automatorConfig['minimum_score'])) {
          if (isset($match[$automatorConfig['output_field']])) {
            $output[] = $match[$automatorConfig['output_field']];
          }
        }
      }

      return $output;
    }
    catch (\Exception $e) {
      $this->logger->error('Vector search to text failed: @message', ['@message' => $e->getMessage()]);
      return [];
    }
  }

}
