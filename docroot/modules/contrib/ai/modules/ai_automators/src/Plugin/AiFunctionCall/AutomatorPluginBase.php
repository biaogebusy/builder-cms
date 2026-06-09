<?php

namespace Drupal\ai_automators\Plugin\AiFunctionCall;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Service\FunctionCalling\FunctionCallInterface;
use Drupal\ai\Service\FunctionCalling\StructuredExecutableFunctionCallInterface;
use Drupal\ai_automators\Plugin\AiFunctionCall\Derivative\AutomatorPluginDeriver;
use Drupal\ai_automators\Service\Automate;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Plugin implementation of the automator plugin function.
 */
#[FunctionCall(
  id: 'automator_plugin',
  function_name: 'automator_plugin',
  name: new TranslatableMarkup('Automator Plugin Wrapper'),
  description: '',
  deriver: AutomatorPluginDeriver::class
)]
class AutomatorPluginBase extends FunctionCallBase implements StructuredExecutableFunctionCallInterface {

  /**
   * The automator tool config.
   *
   * @var \Drupal\ai_automators\AutomatorsToolInterface
   */
  protected $pluginCollection;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The automate runner.
   *
   * @var \Drupal\ai_automators\Service\Automate
   */
  protected Automate $automate;

  /**
   * The output.
   *
   * @var array
   */
  protected array $output = [];

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
    $instance->automate = $container->get('ai_automator.automate');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute() {
    // Load the automator tool.
    try {
      /** @var \Drupal\ai_automators\Entity\AutomatorsTool $tool */
      $tool = $this->entityTypeManager->getStorage('automators_tool')->load($this->getDerivativeId());
    }
    catch (\Exception $e) {
      throw new \Exception('Automator tool not found.');
    }
    $workflow = explode('--', $tool->get('workflow'));
    if (count($workflow) !== 2) {
      throw new \Exception('Invalid workflow.');
    }
    $input = [];
    // Iterate the inputs.
    $contexts = $this->getContexts();
    foreach ($tool->get('field_connections') as $data) {
      if ($data['agent_process'] === 'input') {
        $input[$data['field_name']] = $contexts[$data['field_name']]->getContextValue();
      }
    }
    $result = $this->automate->run($workflow[1], $input);

    foreach ($tool->get('field_connections') as $data) {
      if ($data['agent_process'] === 'output') {
        $this->output[$data['field_name']] = $result[$data['field_name']];
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getReadableOutput(): string {
    return Yaml::dump($this->output, 10, 2);
  }

  /**
   * {@inheritdoc}
   */
  public function getStructuredOutput(): array {
    return $this->output;
  }

  /**
   * {@inheritdoc}
   */
  public function setStructuredOutput(array $output): void {
    $this->output = $output;
  }

}
