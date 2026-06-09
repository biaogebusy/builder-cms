<?php

namespace Drupal\ai_search\Plugin\AiFunctionCall;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Service\FunctionCalling\FunctionCallInterface;
use Drupal\ai\Service\FunctionCalling\StructuredExecutableFunctionCallInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of AI Rag Search.
 */
#[FunctionCall(
  id: 'ai_search:rag_search',
  function_name: 'ai_search_rag_search',
  name: 'RAG/Vector Search',
  description: 'This method will search one index for a search query and give back results.',
  group: 'information_tools',
  context_definitions: [
    'index' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Index"),
      description: new TranslatableMarkup("The index to search in."),
      required: TRUE,
    ),
    'search_string' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Search String"),
      description: new TranslatableMarkup("The search string to search for."),
      required: TRUE,
    ),
    'amount' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup("Amount"),
      description: new TranslatableMarkup("The amount of results to find."),
      required: FALSE,
      default_value: 10,
    ),
    'min_score' => new ContextDefinition(
      data_type: 'float',
      label: new TranslatableMarkup("Minimal Score"),
      description: new TranslatableMarkup("The minimal score threshold to pass."),
      required: FALSE,
      default_value: 0.5,
    ),
  ],
)]
class RagTool extends FunctionCallBase implements StructuredExecutableFunctionCallInterface {


  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected EntityFieldManagerInterface $entityFieldManager;

  /**
   * The results.
   *
   * @var array
   */
  protected array $results = [];

  /**
   * Load from dependency injection container.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): FunctionCallInterface|static {
    $instance = new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ai.context_definition_normalizer'),
    );
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->entityFieldManager = $container->get('entity_field.manager');
    return $instance;
  }

  /**
   * The index for the results.
   *
   * @var string
   */
  protected string $index = '';

  /**
   * The search string.
   *
   * @var string
   */
  protected string $searchString = '';

  /**
   * {@inheritdoc}
   */
  public function execute() {
    // Collect the context values.
    $this->index = $this->getContextValue('index');
    $this->searchString = $this->getContextValue('search_string');
    $amount = $this->getContextValue('amount');
    $min_score = $this->getContextValue('min_score');

    $end_results = [];

    /** @var \Drupal\search_api\Entity\Index */
    $index = $this->entityTypeManager->getStorage('search_api_index')->load($this->index);
    if (!$index) {
      $this->setOutput("The index was not found.");
      return;
    }
    // Then we try to search.
    try {
      $query = $index->query([
        'limit' => $amount,
      ]);
      // We want to get chunks rather than full entities.
      $query->setOption('search_api_ai_get_chunks_result', TRUE);
      $queries = $this->searchString;
      $query->keys($queries);
      $results = $query->execute();
      $i = 1;
      foreach ($results->getResultItems() as $result) {
        // Filter the results.
        if ($min_score > $result->getScore()) {
          continue;
        }
        $end_results[] = "Search result: #$i:\n```\n" . $result->getExtraData('content') . "\n```\n\n";
        $i++;
      }
    }
    catch (\Exception $e) {
      $this->setOutput("Failed to search the index");
      return;
    }
    if (count($end_results)) {
      $output = "Results from searching in the rag index " . $this->index . " for the following prompt: " . $this->searchString . ".\n";
      $output .= implode("\n", $end_results);
      $this->setOutput($output);
    }
    else {
      $this->setOutput("No results were found when searching in the rag index " . $this->index . " for the following prompt: " . $this->searchString . ".\n");
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getStructuredOutput(): array {
    return [
      'results' => $this->results,
      'index' => $this->index,
      'search_string' => $this->searchString,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function setStructuredOutput(array $output): void {
    $this->results = $output['results'];
    $this->index = $output['index'] ?? '';
    $this->searchString = $output['search_string'] ?? '';
  }

}
