<?php

namespace Drupal\state_machine\Plugin\Workflow;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\state_machine\Guard\GuardFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the class for workflows.
 */
class Workflow extends PluginBase implements WorkflowInterface, ContainerFactoryPluginInterface {

  /**
   * The guard factory.
   *
   * @var \Drupal\state_machine\Guard\GuardFactoryInterface
   */
  protected $guardFactory;

  /**
   * The initialized states.
   *
   * @var \Drupal\state_machine\Plugin\Workflow\WorkflowState[]
   */
  protected $states = [];

  /**
   * The initialized transitions.
   *
   * @var \Drupal\state_machine\Plugin\Workflow\WorkflowTransition[]
   */
  protected $transitions = [];

  /**
   * Constructs a new Workflow object.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The workflow plugin_id.
   * @param mixed $plugin_definition
   *   The workflow plugin implementation definition.
   * @param \Drupal\state_machine\Guard\GuardFactoryInterface $guard_factory
   *   The guard factory.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, GuardFactoryInterface $guard_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->guardFactory = $guard_factory;
    // Populate value objects for states and transitions.
    foreach ($plugin_definition['states'] as $id => $state_definition) {
      $this->states[$id] = new WorkflowState($id, $state_definition['label']);
    }
    foreach ($plugin_definition['transitions'] as $id => $transition_definition) {
      $label = $transition_definition['label'];
      $from_states = [];
      foreach ($transition_definition['from'] as $from_state) {
        $from_states[$from_state] = $this->states[$from_state];
      }
      $to_state = $this->states[$transition_definition['to']];
      $this->transitions[$id] = new WorkflowTransition($id, $label, $from_states, $to_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('state_machine.guard_factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getId() {
    return $this->pluginDefinition['id'];
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getGroup() {
    return $this->pluginDefinition['group'];
  }

  /**
   * {@inheritdoc}
   */
  public function getStates() {
    return $this->states;
  }

  /**
   * {@inheritdoc}
   */
  public function getState($id) {
    return $this->states[$id] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getTransitions() {
    return $this->transitions;
  }

  /**
   * {@inheritdoc}
   */
  public function getTransition($id) {
    return $this->transitions[$id] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getPossibleTransitions($state_id) {
    if (empty($state_id)) {
      return $this->transitions;
    }
    $possible_transitions = [];
    foreach ($this->transitions as $id => $transition) {
      if (array_key_exists($state_id, $transition->getFromStates())) {
        $possible_transitions[$id] = $transition;
      }
    }

    return $possible_transitions;
  }

  /**
   * {@inheritdoc}
   */
  public function getAllowedTransitions($state_id, EntityInterface $entity) {
    $allowed_transitions = [];
    foreach ($this->getPossibleTransitions($state_id) as $transition_id => $transition) {
      if ($this->isTransitionAllowed($transition, $entity)) {
        $allowed_transitions[$transition_id] = $transition;
      }
    }

    return $allowed_transitions;
  }

  /**
   * {@inheritdoc}
   */
  public function findTransition($from_state_id, $to_state_id) {
    foreach ($this->getPossibleTransitions($from_state_id) as $transition) {
      if ($transition->getToState()->getId() == $to_state_id) {
        return $transition;
      }
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function isTransitionAllowed(WorkflowTransition $transition, EntityInterface $entity) {
    foreach ($this->guardFactory->get($this->getGroup()) as $guard) {
      if ($guard->allowed($transition, $this, $entity) === FALSE) {
        return FALSE;
      }
    }

    return TRUE;
  }

}
