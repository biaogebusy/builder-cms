<?php

declare(strict_types=1);

namespace Drupal\ai_search\Plugin\AiApiExplorer;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Service\AiProviderFormHelper;
use Drupal\ai_api_explorer\AiApiExplorerPluginBase;
use Drupal\ai_api_explorer\Attribute\AiApiExplorer;
use Drupal\ai_api_explorer\ExplorerHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Plugin implementation of the ai_api_explorer.
 */
#[AiApiExplorer(
  id: 'vector_db_generator',
  title: new TranslatableMarkup('Vector DB Explorer'),
  description: new TranslatableMarkup('Contains a form where you can try out the results you get back from the vector databases.'),
)]
final class VectorDBGenerator extends AiApiExplorerPluginBase {

  /**
   * Constructs the base plugin.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param \Drupal\ai\Service\AiProviderFormHelper $aiProviderHelper
   *   The AI Provider Helper.
   * @param \Drupal\ai_api_explorer\ExplorerHelper $explorerHelper
   *   The Explorer helper.
   * @param \Drupal\ai\AiProviderPluginManager $providerManager
   *   The Provider Manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The Entity Type Manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RequestStack $requestStack, AiProviderFormHelper $aiProviderHelper, ExplorerHelper $explorerHelper, AiProviderPluginManager $providerManager, protected EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $requestStack, $aiProviderHelper, $explorerHelper, $providerManager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('request_stack'),
      $container->get('ai.form_helper'),
      $container->get('ai_api_explorer.helper'),
      $container->get('ai.provider'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritDoc}
   */
  public function isActive(): bool {

    // Check so a search api index exists.
    $return = FALSE;
    try {
      if ($indexes = $this->entityTypeManager->getStorage('search_api_index')->loadMultiple()) {
        /** @var \Drupal\search_api\IndexInterface $index */
        foreach ($indexes as $index) {
          $backend = $index->hasValidServer() ? $index->getServerInstance()->getBackendId() : NULL;
          if ($backend === 'search_api_ai_search') {
            $return = TRUE;
            break;
          }
        }
      }
    }
    catch (\Exception $e) {
      // Ensure the method returns a value by catching any exceptions.
    }

    // Also look for providers that support embeddings.
    if (!$this->providerManager->hasProvidersForOperationType('embeddings')) {
      $return = FALSE;
    }

    return $return;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $options = [];

    /** @var \Drupal\search_api\IndexInterface $index */
    foreach ($this->entityTypeManager->getStorage('search_api_index')->loadMultiple() as $index) {
      $backend = $index->hasValidServer() ? $index->getServerInstance()->getBackendId() : NULL;

      if ($backend == 'search_api_ai_search') {
        $options[$index->id()] = $index->label() . ' (' . $index->id() . ')';
      }
    }

    $form = $this->getFormTemplate($form, 'ai-db-response');

    $form['left']['prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Enter your prompt here. When submitted, your provider will generate a response. Please note that each query counts against your API usage if your provider is a paid provider.'),
      '#description' => $this->t('Based on the complexity of your prompt, traffic, and other factors, a response can take time to complete. Please allow the operation to finish.'),
      '#required' => TRUE,
    ];

    $form['left']['index'] = [
      '#type' => 'select',
      '#title' => $this->t('Select the search api index to use'),
      '#options' => $options,
      '#required' => TRUE,
    ];

    $form['left']['result_nr'] = [
      '#type' => 'number',
      '#title' => $this->t('Results'),
      '#description' => $this->t('The number of results to return.'),
      '#default_value' => 20,
      '#required' => TRUE,
      '#min' => 1,
      '#max' => 100,
    ];

    $form['left']['group_results'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Get All Chunks'),
      '#description' => $this->t('Get all chunks instead of best chunk per entity.'),
      '#default_value' => 0,
    ];

    $form['left']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Run DB Query'),
      '#ajax' => [
        'callback' => $this->getAjaxResponseId(),
        'wrapper' => 'ai-db-response',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse(array &$form, FormStateInterface $form_state): array {
    try {
      /** @var \Drupal\search_api\Entity\Index $index */
      $index = $this->entityTypeManager->getStorage('search_api_index')->load($form_state->getValue('index'));
      $query = $index->query([
        'limit' => $form_state->getValue('result_nr'),
      ]);
      $query->setOption('search_api_bypass_access', TRUE);
      $query->setOption('search_api_ai_get_chunks_result', $form_state->getValue('group_results'));
      $query->keys([$form_state->getValue('prompt')]);
      $results = $query->execute();

      $form['right']['response']['#context']['ai_response']['table'] = [
        '#type' => 'table',
        '#header' => [
          'label' => $this->t('Score'),
        ],
        '#rows' => [],
        '#empty' => $this->t('No results found.'),
      ];

      $converter = NULL;
      if (class_exists('League\CommonMark\CommonMarkConverter')) {
        // Ignore the non-use statement loading since this dependency may not
        // exist.
        // @codingStandardsIgnoreLine
        $converter = new \League\CommonMark\CommonMarkConverter();
      }

      foreach ($results as $row) {
        $all_data = $row->getAllExtraData();
        $row_data = [];

        // First column: Score.
        $row_data['label'] = [
          'data' => [
            '#markup' => '<strong>' . $row->getScore() . '</strong>',
          ],
        ];

        // Dynamically build the rest of the row based on keys in $allData.
        foreach ($all_data as $key => $value) {
          // Add header if missing.
          if (!isset($form['right']['response']['#context']['ai_response']['table']['#header'][$key])) {
            $form['right']['response']['#context']['ai_response']['table']['#header'][$key] = ucfirst($key);
          }

          $html = $converter ? $converter->convert($value) : $value;

          $row_data[$key] = [
            'data' => [
              '#markup' => $html,
            ],
          ];
        }

        $form['right']['response']['#context']['ai_response']['table']['#rows'][] = $row_data;
      }
    }
    catch (\Exception $e) {
      $form['right']['response']['#context']['ai_response']['response'] = [
        '#type' => 'inline_template',
        '#template' => '{{ error|raw }}',
        '#context' => [
          'error' => $this->explorerHelper->renderException($e),
        ],
      ];
    }

    return $form['right'];
  }

}
