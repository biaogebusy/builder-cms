<?php

namespace Drupal\state_machine;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Plugin\Discovery\ContainerDerivativeDiscoveryDecorator;
use Drupal\Core\Plugin\Discovery\YamlDiscovery;

/**
 * Manages discovery and instantiation of workflow plugins.
 *
 * @see \Drupal\state_machine\Plugin\Workflow\WorkflowInterface
 * @see plugin_api
 */
class WorkflowManager extends DefaultPluginManager implements WorkflowManagerInterface {

  /**
   * The workflow group manager.
   *
   * @var \Drupal\state_machine\WorkflowGroupManagerInterface
   */
  protected $groupManager;

  /**
   * A cache of loaded workflows, keyed by workflow ID.
   *
   * @var \Drupal\state_machine\Plugin\Workflow\WorkflowInterface[]
   */
  protected $plugins;

  /**
   * Default values for each workflow plugin.
   *
   * @var array
   */
  protected $defaults = [
    'id' => '',
    'label' => '',
    'group' => '',
    'states' => [],
    'transitions' => [],
  ];

  /**
   * Constructs a new WorkflowManager object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The cache backend.
   * @param \Drupal\state_machine\WorkflowGroupManagerInterface $group_manager
   *   The workflow group manager.
   */
  public function __construct(ModuleHandlerInterface $module_handler, CacheBackendInterface $cache_backend, WorkflowGroupManagerInterface $group_manager) {
    $this->moduleHandler = $module_handler;
    $this->setCacheBackend($cache_backend, 'workflow', ['workflow']);
    $this->groupManager = $group_manager;
    $this->alterInfo('workflows');
  }

  /**
   * {@inheritdoc}
   */
  protected function getDiscovery() {
    if (!isset($this->discovery)) {
      $this->discovery = new YamlDiscovery('workflows', $this->moduleHandler->getModuleDirectories());
      $this->discovery->addTranslatableProperty('label', 'label_context');
      $this->discovery = new ContainerDerivativeDiscoveryDecorator($this->discovery);
    }
    return $this->discovery;
  }

  /**
   * {@inheritdoc}
   */
  public function createInstance($plugin_id, array $configuration = []) {
    if (empty($this->plugins[$plugin_id])) {
      $plugin_definition = $this->getDefinition($plugin_id);
      if (empty($plugin_definition['group'])) {
        throw new PluginException(sprintf('The workflow %s must define the group property.', $plugin_id));
      }
      $group_definition = $this->groupManager->getDefinition($plugin_definition['group']);
      $plugin_class = $group_definition['workflow_class'];

      if (is_subclass_of($plugin_class, ContainerFactoryPluginInterface::class)) {
        $this->plugins[$plugin_id] = $plugin_class::create(\Drupal::getContainer(), $configuration, $plugin_id, $plugin_definition);
      }
      else {
        $this->plugins[$plugin_id] = new $plugin_class($configuration, $plugin_id, $plugin_definition);
      }
    }
    return $this->plugins[$plugin_id];
  }

  /**
   * {@inheritdoc}
   */
  public function processDefinition(&$definition, $plugin_id) {
    parent::processDefinition($definition, $plugin_id);

    $definition['id'] = $plugin_id;
    foreach (['label', 'group', 'states', 'transitions'] as $required_property) {
      if (empty($definition[$required_property])) {
        throw new PluginException(sprintf('The workflow %s must define the %s property.', $plugin_id, $required_property));
      }
    }
    foreach ($definition['states'] as $state_id => $state_definition) {
      if (empty($state_definition['label'])) {
        throw new PluginException(sprintf('The workflow state %s must define the label property.', $state_id));
      }
    }
    foreach ($definition['transitions'] as $transition_id => $transition_definition) {
      foreach (['label', 'from', 'to'] as $required_property) {
        if (empty($transition_definition[$required_property])) {
          throw new PluginException(sprintf('The workflow transition %s must define the %s property.', $transition_id, $required_property));
        }
      }
      // Validate the referenced "from" and "to" states.
      foreach ($transition_definition['from'] as $from_state) {
        if (!isset($definition['states'][$from_state])) {
          throw new PluginException(sprintf('The workflow transition %s specified an invalid "from" property: %s.', $transition_id, $from_state));
        }
      }
      $to_state = $transition_definition['to'];
      if (!isset($definition['states'][$to_state])) {
        throw new PluginException(sprintf('The workflow transition %s specified an invalid "to" property.', $transition_id));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupedLabels($entity_type_id = NULL) {
    $definitions = $this->getSortedDefinitions();
    $group_labels = $this->getGroupLabels($entity_type_id);
    $grouped_definitions = [];
    foreach ($definitions as $id => $definition) {
      $group_id = $definition['group'];
      if (!isset($group_labels[$group_id])) {
        // Don't return workflows for groups ignored due to their entity type.
        continue;
      }
      $group_label = $group_labels[$group_id];
      $grouped_definitions[$group_label][$id] = $definition['label'];
    }

    return $grouped_definitions;
  }

  /**
   * Gets the sorted workflow plugin definitions.
   *
   * @return array
   *   The workflow plugin definitions, sorted by group and label.
   */
  protected function getSortedDefinitions() {
    // Sort the plugins first by group, then by label.
    $definitions = $this->getDefinitions();
    uasort($definitions, function ($a, $b) {
      if ($a['group'] != $b['group']) {
        return strnatcasecmp($a['group'], $b['group']);
      }
      return strnatcasecmp($a['label'], $b['label']);
    });

    return $definitions;
  }

  /**
   * Gets a list of group labels for the given entity type ID.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   *
   * @return array
   *   A list of groups labels keyed by ID.
   */
  protected function getGroupLabels($entity_type_id = NULL) {
    $group_definitions = $this->groupManager->getDefinitionsByEntityType($entity_type_id);
    $group_labels = array_map(function ($group_definition) {
      return (string) $group_definition['label'];
    }, $group_definitions);
    natcasesort($group_labels);

    return $group_labels;
  }

}
