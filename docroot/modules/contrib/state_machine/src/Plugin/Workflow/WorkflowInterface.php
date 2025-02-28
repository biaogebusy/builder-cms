<?php

namespace Drupal\state_machine\Plugin\Workflow;

use Drupal\Core\Entity\EntityInterface;

/**
 * Defines the interface for workflows.
 */
interface WorkflowInterface {

  /**
   * Gets the workflow ID.
   *
   * @return string
   *   The workflow ID.
   */
  public function getId();

  /**
   * Gets the translated label.
   *
   * @return string
   *   The translated label.
   */
  public function getLabel();

  /**
   * Gets the workflow group.
   *
   * @return string
   *   The workflow group.
   */
  public function getGroup();

  /**
   * Gets the workflow states.
   *
   * @return \Drupal\state_machine\Plugin\Workflow\WorkflowState[]
   *   The states, keyed by state ID.
   */
  public function getStates();

  /**
   * Gets a workflow state with the given ID.
   *
   * @param string $id
   *   The state ID.
   *
   * @return \Drupal\state_machine\Plugin\Workflow\WorkflowState|null
   *   The requested state, or NULL if not found.
   */
  public function getState($id);

  /**
   * Gets the workflow transitions.
   *
   * @return \Drupal\state_machine\Plugin\Workflow\WorkflowTransition[]
   *   The transitions, keyed by transition ID.
   */
  public function getTransitions();

  /**
   * Gets a workflow transition with the given ID.
   *
   * @param string $id
   *   The transition ID.
   *
   * @return \Drupal\state_machine\Plugin\Workflow\WorkflowTransition|null
   *   The requested transition, or NULL if not found.
   */
  public function getTransition($id);

  /**
   * Gets the possible workflow transitions for the given state ID.
   *
   * Note that a possible transition might not be allowed (because of a guard
   * returning false).
   *
   * @param string $state_id
   *   The state ID.
   *
   * @return \Drupal\state_machine\Plugin\Workflow\WorkflowTransition[]
   *   The possible transitions, keyed by transition ID.
   */
  public function getPossibleTransitions($state_id);

  /**
   * Gets the allowed workflow transitions for the given state ID.
   *
   * @param string $state_id
   *   The state ID.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The parent entity.
   *
   * @return \Drupal\state_machine\Plugin\Workflow\WorkflowTransition[]
   *   The allowed transitions, keyed by transition ID.
   */
  public function getAllowedTransitions($state_id, EntityInterface $entity);

  /**
   * Finds the workflow transition for moving between two given states.
   *
   * @param string $from_state_id
   *   The ID of the "from" state.
   * @param string $to_state_id
   *   The ID of the "to" state.
   *
   * @return \Drupal\state_machine\Plugin\Workflow\WorkflowTransition|null
   *   The transition, or NULL if not found.
   */
  public function findTransition($from_state_id, $to_state_id);

  /**
   * Gets whether the given transition is allowed by the transition guards.
   *
   * Note that this method assumes the given transition is "possible".
   *
   * @param \Drupal\state_machine\Plugin\Workflow\WorkflowTransition $transition
   *   The transition.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The parent entity.
   *
   * @return bool
   *   TRUE if the transition is allowed, FALSE otherwise.
   */
  public function isTransitionAllowed(WorkflowTransition $transition, EntityInterface $entity);

}
