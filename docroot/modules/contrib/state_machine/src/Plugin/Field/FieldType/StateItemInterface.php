<?php

namespace Drupal\state_machine\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowTransition;

/**
 * Defines the interface for state field items.
 */
interface StateItemInterface extends FieldItemInterface {

  /**
   * Gets the workflow used by the field.
   *
   * @return \Drupal\state_machine\Plugin\Workflow\WorkflowInterface|false
   *   The workflow, or FALSE if unknown at this time.
   */
  public function getWorkflow();

  /**
   * Gets the original state ID.
   *
   * If the state ID has been changed after the entity was constructed/loaded,
   * the original ID will hold the previous value.
   *
   * Use this as an alternative to getting the state ID from $entity->original.
   *
   * @return string
   *   The original state ID.
   */
  public function getOriginalId();

  /**
   * Gets the current state ID.
   *
   * @return string
   *   The current state ID.
   */
  public function getId();

  /**
   * Gets the label of the current state.
   *
   * @return string
   *   The label of the current state.
   */
  public function getLabel();

  /**
   * Gets the label of the original state.
   *
   * @return string
   *   The label of the original state.
   */
  public function getOriginalLabel();

  /**
   * Gets the allowed transitions for the current state.
   *
   * @return \Drupal\state_machine\Plugin\Workflow\WorkflowTransition[]
   *   The allowed transitions, keyed by transition ID.
   */
  public function getTransitions();

  /**
   * Gets whether the given transition is allowed.
   *
   * @param string $transition_id
   *   The transition ID.
   *
   * @return bool
   *   TRUE if the given transition is allowed, FALSE otherwise.
   */
  public function isTransitionAllowed($transition_id);

  /**
   * Applies the given transition, changing the current state.
   *
   * @param \Drupal\state_machine\Plugin\Workflow\WorkflowTransition $transition
   *   The transition to apply.
   *
   * @throws \InvalidArgumentException
   *   Thrown when the transition is not allowed.
   */
  public function applyTransition(WorkflowTransition $transition);

  /**
   * Applies a transition with the given ID, changing the current state.
   *
   * @param string $transition_id
   *   The transition ID.
   *
   * @throws \InvalidArgumentException
   *   Thrown when no matching transition was found.
   */
  public function applyTransitionById($transition_id);

  /**
   * Gets whether the current state is valid.
   *
   * Drupal separates field validation into a separate step, allowing an
   * invalid state to be set before validation is invoked. At that point
   * validation has no access to the previous value, so it can't determine
   * if the transition is allowed. Thus, the field item must track the state
   * changes internally, and answer via this method if the current state is
   * valid.
   *
   * @see \Drupal\state_machine\Plugin\Validation\Constraint\StateConstraintValidator
   *
   * @return bool
   *   TRUE if the current state is valid, FALSE otherwise.
   */
  public function isValid();

}
