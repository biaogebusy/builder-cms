<?php

namespace Drupal\ai\Base;

use Drupal\ai\Service\FunctionCalling\OverridableFunctionCallInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\Context\ContextDefinitionInterface;
use Drupal\Core\Plugin\ContextAwarePluginTrait;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ai\OperationType\Chat\Tools\ToolsFunctionInput;
use Drupal\ai\OperationType\Chat\Tools\ToolsFunctionOutput;
use Drupal\ai\PluginManager\AiDataTypeConverterPluginManager;
use Drupal\ai\Service\FunctionCalling\FunctionCallInterface;
use Drupal\ai\Traits\PluginManager\AiDataTypeConverterPluginManagerTrait;
use Drupal\ai\Utility\ContextDefinitionNormalizer;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Function call base class.
 */
abstract class FunctionCallBase extends PluginBase implements OverridableFunctionCallInterface, ContainerFactoryPluginInterface {

  use AiDataTypeConverterPluginManagerTrait;
  use StringTranslationTrait;
  use ContextAwarePluginTrait {
    setContextValue as protected traitSetContextValue;
    getContextDefinition as protected traitGetContextDefinition;
    getContextDefinitions as protected traitGetContextDefinitions;
  }

  /**
   * The context definition normalizer service.
   */
  protected ContextDefinitionNormalizer $contextDefinitionNormalizer;

  /**
   * The ai context converter plugin manager.
   */
  protected AiDataTypeConverterPluginManager $dataTypeConverterManager;

  /**
   * The tools id.
   *
   * @var string
   */
  protected string $toolsId = "";

  /**
   * The output.
   *
   * @var string
   */
  protected string $stringOutput = "";

  /**
   * The structured output.
   *
   * @var array
   */
  protected array $structuredOutput = [];

  /**
   * Per-instance context definition overrides.
   *
   * Keyed by context definition name. Values take precedence over the shared
   * plugin definition cache in getContextDefinition() and
   * getContextDefinitions().
   *
   * @var array<string, \Drupal\Core\Plugin\Context\ContextDefinitionInterface>
   */
  protected array $contextDefinitionOverrides = [];

  /**
   * Constructs a FunctionCall plugin.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\ai\Utility\ContextDefinitionNormalizer $context_definition_normalizer
   *   The context definition normalizer service.
   * @param \Drupal\ai\PluginManager\AiDataTypeConverterPluginManager $data_type_converter_manager
   *   The ai data type converter plugin manager.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected ContextDefinitionNormalizer $context_definition_normalizer,
    protected ?AiDataTypeConverterPluginManager $data_type_converter_manager = NULL,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->contextDefinitionNormalizer = $context_definition_normalizer;

    if (!$data_type_converter_manager instanceof AiDataTypeConverterPluginManager) {
      @trigger_error('FunctionCallBase::__construct() without the AiDataTypeConverterPluginManager argument is deprecated in ai:1.2.0 and will be required in ai:2.0.0. See https://www.drupal.org/project/ai/issues/3512100', E_USER_DEPRECATED);
    }
    else {
      $this->dataTypeConverterManager = $data_type_converter_manager;
    }

  }

  /**
   * Load from dependency injection container.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): FunctionCallInterface|static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ai.context_definition_normalizer'),
      $container->get('plugin.manager.ai_data_type_converter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getToolsId(): string {
    return $this->toolsId;
  }

  /**
   * {@inheritdoc}
   */
  public function setToolsId(string $tools_id) {
    $this->toolsId = $tools_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getFunctionName(): string {
    return $this->pluginDefinition['function_name'];
  }

  /**
   * {@inheritdoc}
   */
  public function populateValues(ToolsFunctionOutput $output) {
    foreach ($output->getArguments() as $argument) {
      $property_name = $argument->getName();
      // Tmp fix?.
      $property_name = str_replace('__colon__', ':', $property_name);
      // Only set context value if the context exists.
      if (array_key_exists($property_name, $this->getContextDefinitions())) {
        $this->setContextValue($property_name, $argument->getValue());
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getReadableOutput(): string {
    return $this->stringOutput;
  }

  /**
   * {@inheritdoc}
   */
  public function setOutput(string $output): void {
    $this->stringOutput = $output;
  }

  /**
   * {@inheritdoc}
   */
  public function setContextDefinitionOverride(string $name, ContextDefinitionInterface $definition): void {
    $this->contextDefinitionOverrides[$name] = $definition;
  }

  /**
   * {@inheritdoc}
   */
  public function getContextDefinition($name) {
    return $this->contextDefinitionOverrides[$name] ?? $this->traitGetContextDefinition($name);
  }

  /**
   * {@inheritdoc}
   */
  public function getContextDefinitions() {
    return $this->contextDefinitionOverrides + $this->traitGetContextDefinitions();
  }

  /**
   * {@inheritdoc}
   */
  public function setContextValue($name, $value) {
    // If multiple, convert the value based on list, then convert each item.
    if ($this->getContextDefinition($name)->isMultiple()) {
      $value = $this->dataTypeConverterManager()->convert('list', $value);
      foreach ($value as $delta => $item) {
        $value[$delta] = $this->dataTypeConverterManager()->convert($this->getContextDefinition($name)->getDataType(), $item);
      }
    }
    else {
      $value = $this->dataTypeConverterManager()->convert($this->getContextDefinition($name)->getDataType(), $value);
    }
    return $this->traitSetContextValue($name, $value);
  }

  /**
   * {@inheritdoc}
   */
  public function populateChildValue(FunctionCallInterface $child, array $values): array {
    $items = [];
    foreach ($values as $props) {
      $item = clone $child;
      foreach ($props as $prop => $value) {
        if (property_exists($child, $prop)) {
          // Ensure the value is converted to the correct type, we ignore the
          // type conversion here, as the child should handle it.
          $item->$prop = $value;
        }
      }
      $items[] = $item;
    }
    return $items;
  }

  /**
   * {@inheritdoc}
   */
  public function normalize(): ToolsFunctionInput {
    // Check all the attributes of the function call.
    $function_attributes = $this->pluginDefinition;
    // Create the function first.
    $function = new ToolsFunctionInput($function_attributes['function_name'], []);
    $function->setDescription($function_attributes['description']);
    if ($this->getContextDefinitions()) {
      $properties = $this->contextDefinitionNormalizer->normalize($this->getContextDefinitions());
      foreach ($properties as $property) {
        $function->setProperty($property);
      }
    }
    return $function;
  }

  /**
   * Add the get structured output method to the abstract as fallback.
   *
   * @return array
   *   The structured output.
   */
  public function getStructuredOutput(): array {
    return $this->structuredOutput ?? [];
  }

  /**
   * Set the structured output.
   *
   * @param array $output
   *   The structured output to set.
   */
  public function setStructuredOutput(array $output): void {
    $this->structuredOutput = $output;
  }

  /**
   * Get the data type converter manager.
   *
   * @return \Drupal\ai\PluginManager\AiDataTypeConverterPluginManager
   *   The data type converter plugin manager.
   */
  protected function dataTypeConverterManager(): AiDataTypeConverterPluginManager {
    return $this->dataTypeConverterManager ?? $this->getAiDataTypeConverterPluginManager();
  }

}
