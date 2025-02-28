<?php

namespace Drupal\state_machine\Plugin\views\filter;

use Drupal\Component\Utility\SortArray;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\views\Plugin\views\filter\InOperator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filter by workflow state.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("state_machine_state")
 */
class State extends InOperator {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Constructs a new State object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getValueOptions() {
    if (!isset($this->valueOptions)) {
      $entity_type_id = $this->getEntityType();
      $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
      $field_name = $this->getFieldName();
      $workflows = $this->getWorkflows($entity_type, $field_name);
      // Merge the states of all workflows into one list, preserving their
      // initial positions.
      $states = [];
      foreach ($workflows as $workflow) {
        $weight = 0;
        foreach ($workflow->getStates() as $state_id => $state) {
          $states[$state_id] = [
            'label' => $state->getLabel(),
            'weight' => $weight,
          ];
          $weight++;
        }
      }
      uasort($states, [SortArray::class, 'sortByWeightElement']);

      $this->valueOptions = array_map(function ($state) {
        return $state['label'];
      }, $states);
    }

    return $this->valueOptions;
  }

  /**
   * Gets the name of the entity field on which this filter operates.
   *
   * @return string
   *   The field name.
   */
  protected function getFieldName() {
    if (isset($this->configuration['field_name'])) {
      // Configurable field.
      $field_name = $this->configuration['field_name'];
    }
    else {
      // Base field.
      $field_name = $this->configuration['entity field'];
    }

    return $field_name;
  }

  /**
   * Gets the workflows used the current entity field.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The current entity type.
   * @param string $field_name
   *   The current field name.
   *
   * @return \Drupal\state_machine\Plugin\Workflow\WorkflowInterface[]
   *   The workflows.
   */
  protected function getWorkflows(EntityTypeInterface $entity_type, $field_name) {
    // Only the StateItem knows which workflow it's using. This requires us
    // to create an entity for each bundle in order to get the state field.
    $storage = $this->entityTypeManager->getStorage($entity_type->id());
    $bundles = $this->getBundles($entity_type, $field_name);
    $workflows = [];
    foreach ($bundles as $bundle) {
      $values = [];
      if ($bundle_key = $entity_type->getKey('bundle')) {
        $values[$bundle_key] = $bundle;
      }
      /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
      $entity = $storage->create($values);
      if ($entity->hasField($field_name)) {
        $workflow = $entity->get($field_name)->first()->getWorkflow();
        $workflows[$workflow->getId()] = $workflow;
      }
    }

    return $workflows;
  }

  /**
   * Gets the bundles for the current entity field.
   *
   * If the view has a non-exposed bundle filter, the bundles are taken from
   * there. Otherwise, the field's bundles are used.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The current entity type.
   * @param string $field_name
   *   The current field name.
   *
   * @return string[]
   *   The bundles.
   */
  protected function getBundles(EntityTypeInterface $entity_type, $field_name) {
    $bundles = [];
    $bundle_key = $entity_type->getKey('bundle');
    if ($bundle_key) {
      // Get any bundle filters for this entity type and bundle.
      // It is unlikely, but there could be multiple.
      /** @var \Drupal\views\Plugin\views\filter\FilterPluginBase[] $bundle_filters */
      $bundle_filters = array_filter($this->view->filter ?? [], static function ($filter) use ($entity_type, $bundle_key) {
        return $filter->getEntityType() === $entity_type->id() && $filter->realField === $bundle_key;
      });
      foreach ($bundle_filters as $bundle_filter) {
        if ($bundle_filter->isExposed()) {
          // If any bundle filters are exposed,
          // we cannot return a subset of bundles.
          $bundles = [];
          break;
        }
        switch ($bundle_filter->operator) {
          case 'in':
            $bundles = array_merge($bundles, $bundle_filter->value);
            break;

          case 'not in':
            $bundles = array_diff($bundles, $bundle_filter->value);
            break;
        }
      }
      // Remove the "all" option, if present.
      if (array_key_exists('all', $bundles)) {
        unset($bundles['all']);
      }
    }
    // Fallback to the list of bundles the field is attached to.
    if (empty($bundles)) {
      $map = $this->entityFieldManager->getFieldMap();
      $bundles = $map[$entity_type->id()][$field_name]['bundles'];
    }

    return $bundles;
  }

}
