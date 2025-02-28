<?php

declare(strict_types=1);

namespace Drupal\forms_steps;

use Drupal\forms_steps\Entity\FormsSteps;

/**
 * An interface for step value objects.
 */
interface StepInterface {

  /**
   * Gets the step's ID.
   *
   * @return string
   *   The step's ID.
   */
  public function id(): string;

  /**
   * Gets the step's label.
   *
   * @return string
   *   The step's label.
   */
  public function label(): string;

  /**
   * Gets the step's weight.
   *
   * @return int
   *   The step's weight.
   */
  public function weight(): int;

  /**
   * Gets the step's bundle.
   *
   * @return string
   *   The step's entity bundle.
   */
  public function entityBundle(): string;

  /**
   * Gets the step's entity type.
   *
   * @return string
   *   The step's entity type.
   */
  public function entityType(): string;

  /**
   * Gets the step's form mode.
   *
   * @return string
   *   The step's form mode.
   */
  public function formMode(): string;

  /**
   * Gets the step's url.
   *
   * @return string
   *   The step's url.
   */
  public function url(): string;

  /**
   * Gets the submit label.
   *
   * @return string
   *   The submit label.
   */
  public function submitLabel(): string;

  /**
   * Set the submit label.
   *
   * @param string $label
   *   The label to set.
   */
  public function setSubmitLabel(string $label);

  /**
   * Gets the cancel label.
   *
   * @return string
   *   The cancel label.
   */
  public function cancelLabel(): string;

  /**
   * Set the cancel label.
   *
   * @param string $label
   *   The label to set.
   */
  public function setCancelLabel(string $label);

  /**
   * Gets the cancel route.
   *
   * @return string
   *   The cancel route.
   */
  public function cancelRoute(): string;

  /**
   * Set the cancel route.
   *
   * @param string $route
   *   The cancel route to set.
   */
  public function setCancelRoute(string $route);

  /**
   * Gets the cancel step.
   *
   * @return \Drupal\forms_steps\Step|null
   *   The cancel step.
   */
  public function cancelStep(): ?Step;

  /**
   * Set the cancel step.
   *
   * @param \Drupal\forms_steps\Step $step
   *   The step to go when the user click the cancel button.
   */
  public function setCancelStep(Step $step);

  /**
   * Gets the cancel step mode.
   *
   * @return string
   *   The cancel step mode.
   */
  public function cancelStepMode(): string;

  /**
   * Set the cancel step mode.
   *
   * @param mixed $mode
   *   Mode.
   */
  public function setCancelStepMode($mode);

  /**
   * Get the hidden status of the delete button.
   *
   * @return bool
   *   TRUE if hidden | FALSE otherwise.
   */
  public function hideDelete(): bool;

  /**
   * Set the hidden state of the delete button.
   *
   * @param bool|int $value
   *   TRUE if hidden | FALSE otherwise.
   */
  public function setHideDelete($value);

  /**
   * Set the delete label.
   *
   * @param mixed $label
   *   The label to set.
   */
  public function setDeleteLabel($label);

  /**
   * Get the forms steps object parent to this step.
   *
   * @return \Drupal\forms_steps\Entity\FormsSteps
   *   The forms steps object.
   */
  public function formsSteps(): FormsSteps;

  /**
   * Get the display status of the previous button.
   *
   * @return bool
   *   TRUE if displayed | FALSE otherwise.
   */
  public function displayPrevious(): bool;

  /**
   * Set the previous label.
   *
   * @param mixed $label
   *   The label to set.
   */
  public function setPreviousLabel($label);

  /**
   * Gets the previous label.
   *
   * @return string
   *   The previous label.
   */
  public function previousLabel(): string;

  /**
   * Set the display state of the previous button.
   *
   * @param bool|int $value
   *   TRUE if displayed | FALSE otherwise.
   */
  public function setDisplayPrevious($value);

  /**
   * Determines if the step is the last one on its forms steps entity.
   */
  public function isLast();

}
