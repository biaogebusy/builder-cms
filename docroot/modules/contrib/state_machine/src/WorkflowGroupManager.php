<?php

namespace Drupal\state_machine;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Plugin\Discovery\ContainerDerivativeDiscoveryDecorator;
use Drupal\Core\Plugin\Discovery\YamlDiscovery;
use Drupal\state_machine\Plugin\Workflow\Workflow;
use Drupal\state_machine\Plugin\WorkflowGroup\WorkflowGroup;

/**
 * Manages discovery and instantiation of workflow_group plugins.
 *
 * @see \Drupal\state_machine\Plugin\WorkflowGroup\WorkflowGroupInterface
 * @see plugin_api
 */
class WorkflowGroupManager extends DefaultPluginManager implements WorkflowGroupManagerInterface {

  /**
   * Default values for each workflow_group plugin.
   *
   * @var array
   */
  protected $defaults = [
    'id' => '',
    'label' => '',
    'entity_type' => '',
    'class' => WorkflowGroup::class,
    // Groups can override the default workflow class for advanced use cases.
    'workflow_class' => Workflow::class,
  ];

  /**
   * Constructs a new WorkflowGroupManager object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The cache backend.
   */
  public function __construct(ModuleHandlerInterface $module_handler, CacheBackendInterface $cache_backend) {
    $this->moduleHandler = $module_handler;
    $this->setCacheBackend($cache_backend, 'workflow_group', ['workflow_group']);
    $this->alterInfo('workflow_groups');
  }

  /**
   * {@inheritdoc}
   */
  protected function getDiscovery() {
    if (!isset($this->discovery)) {
      $this->discovery = new YamlDiscovery('workflow_groups', $this->moduleHandler->getModuleDirectories());
      $this->discovery->addTranslatableProperty('label', 'label_context');
      $this->discovery = new ContainerDerivativeDiscoveryDecorator($this->discovery);
    }
    return $this->discovery;
  }

  /**
   * {@inheritdoc}
   */
  public function processDefinition(&$definition, $plugin_id) {
    parent::processDefinition($definition, $plugin_id);

    if ($plugin_id == 'state_machine') {
      throw new PluginException('The "state_machine" workflow_group ID is reserved and must not be used.');
    }

    $definition['id'] = $plugin_id;
    foreach (['label', 'entity_type'] as $required_property) {
      if (empty($definition[$required_property])) {
        throw new PluginException(sprintf('The workflow_group %s must define the %s property.', $plugin_id, $required_property));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinitionsByEntityType($entity_type_id = NULL) {
    $definitions = $this->getDefinitions();
    if ($entity_type_id) {
      $definitions = array_filter($definitions, function ($definition) use ($entity_type_id) {
        return $definition['entity_type'] == $entity_type_id;
      });
    }

    return $definitions;
  }

}
