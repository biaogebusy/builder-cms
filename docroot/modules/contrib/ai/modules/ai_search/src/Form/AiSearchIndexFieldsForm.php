<?php

namespace Drupal\ai_search\Form;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\ai\Enum\EmbeddingStrategyCapability;
use Drupal\ai\Enum\EmbeddingStrategyIndexingOptions;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Form\IndexFieldsForm;
use Drupal\search_api\Item\ItemInterface;

/**
 * Override the Search API Index Fields Form.
 */
class AiSearchIndexFieldsForm extends IndexFieldsForm {

  /**
   * The indexing options with labels and descriptions.
   *
   * @var array[] {
   *   @type string $label The label for the indexing option.
   *   @type string $description The description for the indexing option.
   * }
   */
  public array $options = [];

  /**
   * Build the select indexing options.
   *
   * @return array
   *   The select options for indexing options.
   */
  protected function buildSelectIndexingOptions(): array {
    $return = [];
    foreach (EmbeddingStrategyIndexingOptions::cases() as $option) {
      $return[$option->getKey()] = $option->getLabel();
    }
    return $return;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    if ($this->entity->getServerInstance()->getBackendId() !== 'search_api_ai_search') {
      return $form;
    }
    $ai_search_index_config = $this->config('ai_search.index.' . $this->entity->id())->getRawData();

    // Advance controls.
    $form['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced Field Indexing Options'),
      '#open' => FALSE,
    ];
    $form['advanced']['control_field_max_length'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Advanced usage: Set maximum lengths for each string "Filterable attribute".'),
      '#description' => $this->t('Vector Databases allow attaching of metadata to the vectorized content; however, they typically have limits to how much metadata can be attached. If you set very long fields as "Filterable attributes" you may wish to control the maximum length per field. Disabling this checkbox will reset the maximum lengths to no restriction.'),
      '#default_value' => $ai_search_index_config['control_field_max_length'] ?? FALSE,
    ];
    $form['advanced']['exclude_chunk_from_metadata'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Advanced usage: Exclude the "Chunk" of the "Main Content" from the metadata.'),
      '#description' => $this->t('By default the metadata contains a "content" attribute attached to it. This may be used by some tools when a chunk is returned such as an AI Assistant. If you however ensure that the returned results are used to load the full entity (also an option in AI Assistants and the default for Views) then the "content" attribute in the metadata is not needed and can save space.'),
      '#default_value' => $ai_search_index_config['exclude_chunk_from_metadata'] ?? FALSE,
    ];
    if (
      $form['advanced']['control_field_max_length']['#default_value']
      || $form['advanced']['exclude_chunk_from_metadata']['#default_value']
    ) {
      $form['advanced']['#open'] = TRUE;
    }

    // Add the options to the introductory description.
    $form['description']['indexing_options_heading'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => $this->t('Vector Database indexing options'),
    ];
    $rows = [];
    foreach (EmbeddingStrategyIndexingOptions::cases() as $option) {
      $rows[] = [
        'label' => $option->getLabel(),
        'description' => $option->getDescription(),
      ];
    }
    $form['description']['indexing_options_table'] = [
      '#type' => 'table',
      '#header' => [
        ['data' => $this->t('Indexing option')],
        ['data' => $this->t('Description')],
      ],
      '#rows' => $rows,
    ];

    foreach ($form as $key => &$field_group) {
      if ($key !== '_general' && !str_starts_with($key, 'entity:')) {
        continue;
      }

      // Add the header row for target type.
      if (!empty($field_group['#header'])) {
        $operations_header = array_pop($field_group['#header']);

        // Remove boost from header as it is not applicable to vector
        // databases.
        $boost_index = array_search($this->t('Boost'), $field_group['#header']);
        if ($boost_index) {
          $field_group['#header'][$boost_index] = '';
        }

        $field_group['#header'][] = $this->t('Indexing option');
        if (isset($ai_search_index_config['control_field_max_length']) && $ai_search_index_config['control_field_max_length']) {
          $field_group['#header'][] = $this->t('Maximum length');
        }
        $field_group['#header'][] = $operations_header;

        // Update the rows.
        if (!empty($field_group['fields'])) {
          foreach ($field_group['fields'] as $field_id => &$row) {
            $field_id = (string) $field_id;
            $edit_row = array_pop($row);
            $remove_row = array_pop($row);

            // Remove boost from row as it is not applicable to vector
            // databases.
            if (isset($row['boost'])) {
              $row['boost']['#type'] = 'hidden';
              if (isset($row['boost']['#states'])) {
                unset($row['boost']['#states']);
              }
            }

            $row['indexing_option'] = [
              '#type' => 'select',
              '#options' => $this->buildSelectIndexingOptions(),
              '#empty_option' => $this->t('- Select -'),
              '#default_value' => '',
            ];
            if (!empty($ai_search_index_config['indexing_options'][$field_id]['indexing_option'])) {
              $row['indexing_option']['#default_value'] = $ai_search_index_config['indexing_options'][$field_id]['indexing_option'];
            }

            if (isset($ai_search_index_config['control_field_max_length']) && $ai_search_index_config['control_field_max_length']) {
              if (
                isset($row['type']['#default_value'])
                && $row['type']['#default_value'] === 'string'
                && $row['indexing_option']['#default_value'] === EmbeddingStrategyIndexingOptions::Attributes->getKey()
              ) {
                $row['max'] = [
                  '#type' => 'number',
                  '#step' => 1,
                  '#maxlength' => 5,
                  '#default_value' => '',
                ];
                if (
                  !empty($ai_search_index_config['indexing_options'][$field_id]['max'])
                  && $ai_search_index_config['indexing_options'][$field_id]['max'] > 0
                ) {
                  $row['max']['#default_value'] = (int) $ai_search_index_config['indexing_options'][$field_id]['max'];
                }
              }
              else {
                $row['max'] = ['#markup' => 'N/A'];
              }
            }
            $row[] = $remove_row;
            $row[] = $edit_row;
          }
        }
      }

    }

    // Chunk checker form.
    if ($data_sources = $this->entity->getDatasources()) {
      $form['checker'] = [
        '#type' => 'details',
        '#title' => $this->t('Preview content to be vectorized'),
        '#description' => $this->t('After saving your configuration, without needing to index, this form can be used to check what will get vectorized from a specific item (i.e., the "Main Content" and "Contextual Content" output will be shown), as well as what metadata will be available from "Filterable Attributes".'),
        '#open' => (bool) $form_state->get(['checker', 'entity']),
        '#attributes' => ['id' => 'checker-wrapper'],
      ];

      // Entity type.
      $current_type = FALSE;
      $current_data_source = FALSE;
      $current_bundles = [];
      $form['checker']['data_source'] = [
        '#title' => $this->t('Data source'),
        '#type' => 'select',
        '#options' => [],
        '#ajax' => [
          'callback' => [$this, 'updateChecker'],
          'event' => 'change',
          'method' => 'replaceWith',
          'wrapper' => 'checker-wrapper',
        ],
      ];
      foreach ($data_sources as $key => $data_source) {
        if (
          !isset($form['checker']['data_source']['#default_value'])
          || $form_state->getValue(['checker', 'data_source']) === $key
        ) {
          $form['checker']['data_source']['#default_value'] = $key;
          $current_type_parts = explode(':', $key);
          $current_type = end($current_type_parts);
          $current_data_source = $data_source;
          $configuration = $data_source->getConfiguration();

          // Ignore static Drupal Service call: we do this to make it easier to
          // keep this compatible with Search API as changes are expected here.
          // @phpstan-ignore-next-line
          $all_bundles = array_keys(\Drupal::service('entity_type.bundle.info')->getBundleInfo($current_type));
          if (!empty($configuration['bundles']['default'])) {

            // All selections are exclusions.
            $current_bundles = $all_bundles;
            if (!empty($configuration['bundles']['selected'])) {
              $exclude_bundles = array_values($configuration['bundles']['selected']);
              $current_bundles = array_diff($all_bundles, $exclude_bundles);
            }
          }
          else {

            // All selections are inclusions.
            if (!empty($configuration['bundles']['selected'])) {
              $current_bundles = array_values($configuration['bundles']['selected']);
            }
          }
        }
        $form['checker']['data_source']['#options'][$key] = $data_source->label();
      }

      if ($current_type) {
        $form['checker']['entity'] = [
          '#type' => 'entity_autocomplete',
          '#title' => $this->t('Search for an item by title'),
          '#target_type' => $current_type,
          '#selection_handler' => 'default',
          '#selection_settings' => $current_bundles ? [
            'target_bundles' => $current_bundles,
          ] : [],
          '#ajax' => [
            'callback' => [$this, 'updateChecker'],
            'event' => 'autocompleteclose',
            'method' => 'replaceWith',
            'wrapper' => 'checker-wrapper',
          ],
        ];

        $entity_id = $form_state->getValue(['checker', 'entity']);
        if ($entity_id) {
          $form['checker']['#open'] = TRUE;
          $check_entity = $this->entityTypeManager->getStorage($current_type)->load($entity_id);
          if ($check_entity instanceof EntityInterface) {
            $embeddings = $this->getCheckerEmbeddings(
              $current_data_source,
              $check_entity,
            );
            $form['checker']['embeddings_count'] = [
              '#type' => 'html_tag',
              '#tag' => 'h3',
              '#value' => $this->t('Total chunks for this content: @count', [
                '@count' => count($embeddings),
              ]),
            ];
            foreach (array_values($embeddings) as $number => $embedding) {
              $form = $this->buildCheckerChunkTable($form, $number, $embedding);
            }
          }
        }
      }
    }

    return $form;
  }

  /**
   * Get the embeddings for the given entity.
   *
   * @param \Drupal\search_api\Datasource\DatasourceInterface $current_data_source
   *   The index data source.
   * @param \Drupal\Core\Entity\EntityInterface $check_entity
   *   The entity to check.
   *
   * @return array
   *   The embeddings.
   */
  protected function getCheckerEmbeddings(
    DatasourceInterface $current_data_source,
    EntityInterface $check_entity,
  ): array {
    $backend_config = $this->entity->getServerInstance()->getBackendConfig();

    // Ignore static Drupal Service call: we do this to make it easier to keep
    // this compatible with Search API as changes are expected here.
    /** @var \Drupal\ai_search\EmbeddingStrategyPluginManager $embedding_strategy_provider */
    // @phpstan-ignore-next-line
    $embedding_strategy_provider = \Drupal::service('ai_search.embedding_strategy');
    /** @var \Drupal\ai_search\EmbeddingStrategyInterface $embedding_strategy */
    $embedding_strategy = $embedding_strategy_provider->createInstance($backend_config['embedding_strategy']);
    if ($current_data_source instanceof DatasourceInterface) {
      $item_id = $current_data_source->getItemId($check_entity->getTypedData());
      $item = $current_data_source->load($item_id);
      if ($item instanceof ComplexDataInterface) {
        // @phpstan-ignore-next-line
        $search_item = \Drupal::getContainer()
          ->get('search_api.fields_helper')
          ->createItemFromObject($this->entity, $item, $item_id, $current_data_source);
        if ($search_item instanceof ItemInterface) {
          return $embedding_strategy->getEmbedding(
            $backend_config['embeddings_engine'],
            $backend_config['chat_model'],
            $backend_config['embedding_strategy_configuration'],
            $search_item->getFields(),
            $search_item,
            $this->entity,
          );
        }
      }
    }
    return [];
  }

  /**
   * Build a table per embedding chunk.
   *
   * @param array $form
   *   The original form.
   * @param int $number
   *   The chunk number.
   * @param array $embedding
   *   The embedding chunk.
   *
   * @return array
   *   The updated form.
   */
  protected function buildCheckerChunkTable(array $form, int $number, array $embedding): array {
    $form['checker']['embeddings_' . $number] = [
      '#type' => 'table',
      '#header' => [
        ['data' => $this->t('Property')],
        ['data' => $this->t('Content')],
      ],
      '#rows' => [],
      '#empty' => $this->t('No chunks were generated for the given entity.'),
    ];
    $form['checker']['embeddings_' . $number]['#rows'][] = [
      'property' => $this->t('ID for chunk @chunk', [
        '@chunk' => $number,
      ]),
      'content' => $embedding['id'],
    ];
    $form['checker']['embeddings_' . $number]['#rows'][] = [
      'property' => $this->t('Dimensions'),
      'content' => count($embedding['values']),
    ];

    // The conversion from markdown to html is an optional dependency.
    $converter = FALSE;
    if (class_exists('League\CommonMark\CommonMarkConverter')) {
      // Ignore the non-use statement loading since this dependency may not
      // exist.
      // @codingStandardsIgnoreLine
      $converter = new \League\CommonMark\CommonMarkConverter([
        'html_input' => 'strip',
        'allow_unsafe_links' => FALSE,
      ]);
    }
    foreach ($embedding['metadata'] as $key => $item) {
      if (is_array($item)) {
        $form['checker']['embeddings_' . $number]['#rows'][] = [
          'property' => $key,
          'content' => implode(', ', $item) . ' (' . $this->t('Imploded array') . ')',
        ];
      }
      else {

        // Convert the main content from markdown to HTML if the optional
        // dependency on Commonmark exists.
        if ($key === 'content') {
          if ($converter) {
            $item = $converter->convert($item);
          }
          else {
            $notice = [
              '#theme' => 'status_messages',
              '#message_list' => [
                'status' => [
                  $this->t('In order to make the chunk more readable, please install the Commonmark optional dependency from PHP League by running <code>composer require league/commonmark</code>.'),
                ],
              ],
            ];
            $item = $this->renderer->render($notice) . $item;
          }
        }

        $form['checker']['embeddings_' . $number]['#rows'][] = [
          'property' => $key,
          'content' => [
            'data' => [
              '#markup' => $item,
            ],
          ],
        ];
      }
    }
    return $form;
  }

  /**
   * AJAX callback to update the checker.
   */
  public function updateChecker(array &$form, FormStateInterface $form_state): array {
    return $form['checker'] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    if ($this->entity->getServerInstance()->getBackendId() !== 'search_api_ai_search') {
      return $form;
    }

    // Check if the embedding strategy does not support multiple 'Main Content'
    // fields.
    $server_backend_config = $this->entity->getServerInstance()->getBackendConfig();

    // Ignore static Drupal Service call: we do this to make it easier to keep
    // this compatible with Search API as changes are expected here.
    // @phpstan-ignore-next-line
    $embedding_strategy_provider = \Drupal::service('ai_search.embedding_strategy');
    /** @var \Drupal\ai_search\EmbeddingStrategyInterface $embedding_strategy */
    $embedding_strategy = $embedding_strategy_provider->createInstance($server_backend_config['embedding_strategy']);
    if (!$embedding_strategy->supports(EmbeddingStrategyCapability::MultipleMainContent)) {
      $values = $form_state->getValues();

      // Determine the selected indexing options by looping through all fields.
      $count_main_contents = 0;
      if (!empty($values['fields'])) {
        $one_main_message = $this->t('Only one "Main Content" field is supported by the Embedding Strategy selected in the Search API Server configuration.');

        foreach ($values['fields'] as $id => $field) {

          // Skip internal fields.
          if (in_array($id, ['node_grants'])) {
            continue;
          }

          // Ensure that an indexing option is selected for each field.
          if (empty($field['indexing_option'])) {
            $option_required_message = $this->t('For "@field", you must select an indexing option. Select "Ignore" if you do not wish to do anything with this field for now.', [
              '@field' => $field['title'] ?? $id,
            ]);
            $form_state->setErrorByName('fields[' . $id . '][indexing_option', $option_required_message);
            continue;
          }

          // If there is more than one, set a validation error.
          if ($field['indexing_option'] === EmbeddingStrategyIndexingOptions::MainContent->getKey()) {
            $count_main_contents++;
          }
          if ($count_main_contents > 1) {
            $form_state->setErrorByName('fields[' . $id . '][indexing_option', $one_main_message);
          }
        }

        // There must be at least one main content.
        if ($count_main_contents < 1) {
          $keys = array_keys($values['fields']);
          $id = reset($keys);
          $message = $this->t('At least one field should be set as "Main content" to generate the vector embeddings from.');
          $form_state->setErrorByName('fields[' . $id . '][indexing_option', $message);
        }
      }
    }

    if ($form_state->getValue(['checker', 'entity'])) {
      $this->messenger()->addWarning('Remove the "Preview content to be vectorized" in order to save the form.');
      $form_state->setRebuild();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $return = parent::save($form, $form_state);
    if ($this->entity->getServerInstance()->getBackendId() !== 'search_api_ai_search') {
      return $form;
    }

    // Get subsets of values for ease of access.
    $values = $form_state->getValues();
    $advanced = $form_state->getValue('advanced');

    // Determine the selected indexing options by looping through all fields.
    $indexing_options = [];
    if (!empty($values['fields'])) {
      foreach ($values['fields'] as $id => $field) {
        if (!isset($field['indexing_option'])) {
          continue;
        }
        $id = (string) $id;

        // Store the selected indexing option for the field.
        $indexing_options[$id] = [
          'indexing_option' => $field['indexing_option'],
        ];

        // Store maximum length if set, otherwise set to -1 for unlimited.
        if (isset($field['max']) && is_numeric($field['max']) && $field['max'] > 0) {
          $indexing_options[$id]['max'] = $field['max'];
        }
        else {
          $indexing_options[$id]['max'] = -1;
        }
      }
    }

    // Save the configuration to a separate configuration object since Search
    // API does not support custom configuration.
    $ai_search_index_config = $this->configFactory()->getEditable('ai_search.index.' . $this->entity->id());
    $ai_search_index_config->set('indexing_options', $indexing_options);
    $ai_search_index_config->set('control_field_max_length', (bool) $advanced['control_field_max_length']);
    $ai_search_index_config->set('exclude_chunk_from_metadata', (bool) $advanced['exclude_chunk_from_metadata']);
    $ai_search_index_config->save();

    return $return;
  }

}
