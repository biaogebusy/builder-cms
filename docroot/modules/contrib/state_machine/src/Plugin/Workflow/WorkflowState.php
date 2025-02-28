<?php

namespace Drupal\state_machine\Plugin\Workflow;

use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Defines the class for workflow states.
 */
class WorkflowState {

  use StringTranslationTrait;

  /**
   * The state ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The state label.
   *
   * @var string
   */
  protected $label;

  /**
   * Constructs a new WorkflowState object.
   *
   * @param string $id
   *   The state ID.
   * @param string $label
   *   The state label.
   */
  public function __construct($id, $label) {
    $this->id = $id;
    $this->label = $label;
  }

  /**
   * Gets the ID.
   *
   * @return string
   *   The ID.
   */
  public function getId() {
    return $this->id;
  }

  /**
   * Gets the translated label.
   *
   * @return string
   *   The translated label.
   */
  public function getLabel() {
    return (string) $this->t($this->label, [], ['context' => 'workflow state']);
  }

  /**
   * Gets the string representation of the workflow state.
   *
   * @return string
   *   The string representation of the workflow state.
   */
  public function __toString() {
    return $this->getLabel();
  }

}
