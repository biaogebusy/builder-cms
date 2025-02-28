<?php

namespace Drupal\state_machine\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowTransition;

/**
 * Defines the workflow transition event.
 */
class WorkflowTransitionEvent extends Event {

  /**
   * The transition.
   *
   * @var \Drupal\state_machine\Plugin\Workflow\WorkflowTransition
   */
  protected $transition;

  /**
   * The workflow.
   *
   * @var \Drupal\state_machine\Plugin\Workflow\WorkflowInterface
   */
  protected $workflow;

  /**
   * The entity.
   *
   * @var \Drupal\Core\Entity\ContentEntityInterface
   */
  protected $entity;

  /**
   * The state field name.
   *
   * @var string
   */
  protected $fieldName;

  /**
   * Constructs a new WorkflowTransitionEvent object.
   *
   * @param \Drupal\state_machine\Plugin\Workflow\WorkflowTransition $transition
   *   The transition.
   * @param \Drupal\state_machine\Plugin\Workflow\WorkflowInterface $workflow
   *   The workflow.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param string $field_name
   *   The state field name.
   */
  public function __construct(WorkflowTransition $transition, WorkflowInterface $workflow, ContentEntityInterface $entity, $field_name) {
    $this->transition = $transition;
    $this->workflow = $workflow;
    $this->entity = $entity;
    $this->fieldName = $field_name;
  }

  /**
   * Gets the transition.
   *
   * @return \Drupal\state_machine\Plugin\Workflow\WorkflowTransition
   *   The transition.
   */
  public function getTransition() {
    return $this->transition;
  }

  /**
   * Gets the workflow.
   *
   * @return \Drupal\state_machine\Plugin\Workflow\WorkflowInterface
   *   The workflow.
   */
  public function getWorkflow() {
    return $this->workflow;
  }

  /**
   * Gets the entity.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The entity.
   */
  public function getEntity() {
    return $this->entity;
  }

  /**
   * Gets the state field name.
   *
   * @return string
   *   The state field name.
   */
  public function getFieldName() {
    return $this->fieldName;
  }

  /**
   * Gets the state field.
   *
   * @return \Drupal\state_machine\Plugin\Field\FieldType\StateItemInterface
   *   The state field.
   */
  public function getField() {
    /** @var \Drupal\state_machine\Plugin\Field\FieldType\StateItemInterface $field */
    $field = $this->entity->get($this->fieldName)->first();
    return $field;
  }

  /**
   * Gets the "from" state.
   *
   * @deprecated in state_machine:8.x-1.0-rc1 and is removed from state_machine:8.x-2.0.
   *   Use $this->getField()->getOriginalId() instead.
   * @see https://www.drupal.org/node/2982709
   *
   * @return \Drupal\state_machine\Plugin\Workflow\WorkflowState
   *   The "from" state.
   */
  public function getFromState() {
    $original_id = $this->getField()->getOriginalId();
    return $this->workflow->getState($original_id);
  }

  /**
   * Gets the "to" state.
   *
   * @deprecated in state_machine:8.x-1.0-rc1 and is removed from state_machine:8.x-2.0.
   *   Use $this->getTransition->getToState() instead.
   * @see https://www.drupal.org/node/2982709
   *
   * @return \Drupal\state_machine\Plugin\Workflow\WorkflowState
   *   The "to" state.
   */
  public function getToState() {
    return $this->transition->getToState();
  }

}
