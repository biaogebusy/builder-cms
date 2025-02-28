<?php

declare(strict_types=1);

namespace Drupal\forms_steps;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Forms Steps entities configuration interface.
 *
 * @package Drupal\forms_steps
 */
interface FormsStepsInterface extends ConfigEntityInterface {

  /**
   * Adds a step to the forms_steps.
   *
   * @param string $step_id
   *   The step's ID.
   * @param string $label
   *   The step's label.
   * @param string $entityType
   *   The step's entity type.
   * @param string $entityBundle
   *   The step's bundle.
   * @param string $formMode
   *   The step's form_mode.
   * @param string $url
   *   The step's URL.
   *
   * @return \Drupal\forms_steps\FormsStepsInterface
   *   The forms_steps entity.
   */
  public function addStep(string $step_id, string $label, string $entityType, string $entityBundle, string $formMode, string $url): FormsStepsInterface;

  /**
   * Adds a progress step to the forms_steps.
   *
   * @param string $progress_step_id
   *   The progress step's ID.
   * @param string $label
   *   The progress step's label.
   * @param array $routes
   *   The progress step's active routes.
   * @param string $link
   *   The progress step's link.
   * @param array $link_visibility
   *   The progress step's link visibility.
   *
   * @return \Drupal\forms_steps\FormsStepsInterface
   *   The forms_steps entity.
   */
  public function addProgressStep(string $progress_step_id, string $label, array $routes, string $link, array $link_visibility): FormsStepsInterface;

  /**
   * Determines if the forms_steps has a step with the provided ID.
   *
   * @param string $step_id
   *   The step's ID.
   *
   * @return bool
   *   TRUE if the forms_steps has a step with the provided ID, FALSE if not.
   */
  public function hasStep(string $step_id): bool;

  /**
   * Determines if the forms_steps has a progress step with the provided ID.
   *
   * @param string $progress_step_id
   *   The progress step's ID.
   *
   * @return bool
   *   TRUE if the forms_steps has a progress step with the provided ID, FALSE
   *   if not.
   */
  public function hasProgressStep(string $progress_step_id): bool;

  /**
   * Returns the current step route.
   *
   * @param \Drupal\forms_steps\Step $step
   *   Current Step.
   *
   * @return null|string
   *   Returns the current route.
   */
  public function getStepRoute(Step $step): ?string;

  /**
   * Returns the next step route.
   *
   * @param \Drupal\forms_steps\Step $step
   *   Current Step.
   *
   * @return null|string
   *   Returns the next route.
   */
  public function getNextStepRoute(Step $step): ?string;

  /**
   * Gets step objects for the provided step IDs.
   *
   * @param string[] $step_ids
   *   A list of step IDs to get. If NULL then all steps will be returned.
   *
   * @return \Drupal\forms_steps\StepInterface[]
   *   An array of forms_steps steps.
   *
   * @throws \InvalidArgumentException
   *   Thrown if $step_ids contains a step ID that does not exist.
   */
  public function getSteps(array $step_ids = NULL): array;

  /**
   * Gets progress step objects for the provided progress step IDs.
   *
   * @param string[] $progress_step_ids
   *   A list of progress step IDs to get. If NULL then all progress steps will
   *   be returned.
   *
   * @return \Drupal\forms_steps\ProgressStepInterface[]
   *   An array of forms_steps progress steps.
   *
   * @throws \InvalidArgumentException
   *   Thrown if $progress_step_ids contains a progress step ID that does not
   *   exist.
   */
  public function getProgressSteps(array $progress_step_ids = NULL): array;

  /**
   * Retrieve the last step defined on a forms steps entity.
   *
   * @param string|null $steps
   *   The forms_steps steps' IDs.
   *
   * @return \Drupal\forms_steps\StepInterface
   *   The forms_steps step.
   */
  public function getLastStep(string $steps = NULL): StepInterface;

  /**
   * Retrieve the first step defined on a forms steps entity.
   *
   * @param string|null $steps
   *   The forms_steps steps' IDs.
   *
   * @return \Drupal\forms_steps\StepInterface
   *   The forms_steps step.
   */
  public function getFirstStep(string $steps = NULL): StepInterface;

  /**
   * Gets a forms_steps step.
   *
   * @param string $step_id
   *   The step's ID.
   *
   * @return \Drupal\forms_steps\StepInterface
   *   The forms_steps step.
   *
   * @throws \InvalidArgumentException
   *   Thrown if $step_id does not exist.
   */
  public function getStep(string $step_id): StepInterface;

  /**
   * Gets a forms_steps progress step.
   *
   * @param string $progress_step_id
   *   The progress step's ID.
   *
   * @return \Drupal\forms_steps\ProgressStepInterface
   *   The forms_steps progress step.
   *
   * @throws \InvalidArgumentException
   *   Thrown if $progress_step_id does not exist.
   */
  public function getProgressStep(string $progress_step_id): ProgressStepInterface;

  /**
   * Sets a step's label.
   *
   * @param string $step_id
   *   The step ID to set the label for.
   * @param string $label
   *   The step's label.
   *
   * @return \Drupal\forms_steps\FormsStepsInterface
   *   The forms_steps entity.
   */
  public function setStepLabel(string $step_id, string $label): FormsStepsInterface;

  /**
   * Sets a progress step's label.
   *
   * @param string $progress_step_id
   *   The progress step ID to set the label for.
   * @param string $label
   *   The progress step's label.
   *
   * @return \Drupal\forms_steps\FormsStepsInterface
   *   The forms_steps entity.
   */
  public function setProgressStepLabel(string $progress_step_id, string $label): FormsStepsInterface;

  /**
   * Sets a step's weight value.
   *
   * @param string $step_id
   *   The step ID to set the weight for.
   * @param int $weight
   *   The step's weight.
   *
   * @return \Drupal\forms_steps\FormsStepsInterface
   *   The forms_steps entity.
   */
  public function setStepWeight(string $step_id, int $weight): FormsStepsInterface;

  /**
   * Sets a step's Entity bundle.
   *
   * @param string $step_id
   *   The step ID to set the entity_bundle for.
   * @param string $entityBundle
   *   The step's entity bundle.
   *
   * @return \Drupal\forms_steps\FormsStepsInterface
   *   The forms_steps entity.
   */
  public function setStepEntityBundle(string $step_id, string $entityBundle): FormsStepsInterface;

  /**
   * Sets a step's Entity type.
   *
   * @param string $step_id
   *   The step ID to set the entity_bundle for.
   * @param string $entity_type
   *   The step's entity type.
   *
   * @return \Drupal\forms_steps\FormsStepsInterface
   *   The forms_steps entity.
   */
  public function setStepEntityType(string $step_id, string $entity_type): FormsStepsInterface;

  /**
   * Sets a step's form mode value.
   *
   * @param string $step_id
   *   The step ID to set the form mode for.
   * @param string $formMode
   *   The step's form mode.
   *
   * @return \Drupal\forms_steps\FormsStepsInterface
   *   The forms_steps entity.
   */
  public function setStepFormMode(string $step_id, string $formMode): FormsStepsInterface;

  /**
   * Sets a step's URL value.
   *
   * @param string $step_id
   *   The step ID to set the URL for.
   * @param string $url
   *   The step's URL.
   *
   * @return \Drupal\forms_steps\FormsStepsInterface
   *   The forms_steps entity.
   */
  public function setStepUrl(string $step_id, string $url): FormsStepsInterface;

  /**
   * Sets a step's submit label.
   *
   * @param string $step_id
   *   The step ID to set submit label for.
   * @param string $label
   *   The step's submit label.
   *
   * @return \Drupal\forms_steps\FormsStepsInterface
   *   The forms_steps entity.
   */
  public function setStepSubmitLabel(string $step_id, string $label): FormsStepsInterface;

  /**
   * Sets a step's cancel label.
   *
   * @param string $step_id
   *   The step ID to set the cancel label for.
   * @param string $label
   *   The step's cancel label.
   *
   * @return \Drupal\forms_steps\FormsStepsInterface
   *   The forms_steps entity.
   */
  public function setStepCancelLabel(string $step_id, string $label): FormsStepsInterface;

  /**
   * Sets a step's cancel route.
   *
   * @param string $step_id
   *   The step ID to set the route for.
   * @param string $route
   *   The step's cancel route.
   *
   * @return \Drupal\forms_steps\FormsStepsInterface
   *   The forms_steps entity.
   */
  public function setStepCancelRoute(string $step_id, string $route): FormsStepsInterface;

  /**
   * Sets a step's cancel step.
   *
   * @param string $step_id
   *   The step ID to set the cancel step for.
   * @param \Drupal\forms_steps\Step|null $step
   *   The step's cancel step.
   *
   * @return \Drupal\forms_steps\FormsStepsInterface
   *   The forms_steps entity.
   */
  public function setStepCancelStep(string $step_id, Step $step): FormsStepsInterface;

  /**
   * Sets a step's cancel step mode.
   *
   * @param string $step_id
   *   The step ID to set the cancel step mode for.
   * @param string $mode
   *   The step's cancel step mode.
   *
   * @return \Drupal\forms_steps\FormsStepsInterface
   *   The forms_steps entity.
   */
  public function setStepCancelStepMode(string $step_id, string $mode): FormsStepsInterface;

  /**
   * Sets the progress step's active routes.
   *
   * @param string $progress_step_id
   *   The progress step ID to set the active routes for.
   * @param array $routes
   *   The progress step's active routes.
   *
   * @return \Drupal\forms_steps\FormsStepsInterface
   *   The forms_steps entity.
   */
  public function setProgressStepActiveRoutes(string $progress_step_id, array $routes): FormsStepsInterface;

  /**
   * Sets a progress step's link.
   *
   * @param string $progress_step_id
   *   The progress step ID to set the link for.
   * @param string $link
   *   The progress step's link.
   *
   * @return \Drupal\forms_steps\FormsStepsInterface
   *   The forms_steps entity.
   */
  public function setProgressStepLink(string $progress_step_id, string $link): FormsStepsInterface;

  /**
   * Sets a progress step's link visibility.
   *
   * @param string $progress_step_id
   *   The progress step ID to set the link for.
   * @param array $steps
   *   The progress step's link visibility.
   *
   * @return \Drupal\forms_steps\FormsStepsInterface
   *   The forms_steps entity.
   */
  public function setProgressStepLinkVisibility(string $progress_step_id, array $steps): FormsStepsInterface;

  /**
   * Deletes a step from the forms_steps.
   *
   * @param string $step_id
   *   The step ID to delete.
   *
   * @return \Drupal\forms_steps\FormsStepsInterface
   *   The forms_steps entity.
   *
   * @throws \InvalidArgumentException
   *   Thrown if $step_id does not exist.
   */
  public function deleteStep(string $step_id): FormsStepsInterface;

  /**
   * Deletes a progress step from the forms_steps.
   *
   * @param string $progress_step_id
   *   The progress step ID to delete.
   *
   * @return \Drupal\forms_steps\FormsStepsInterface
   *   The forms_steps entity.
   *
   * @throws \InvalidArgumentException
   *   Thrown if $progress_step_id does not exist.
   */
  public function deleteProgressStep(string $progress_step_id): FormsStepsInterface;

  /**
   * Returns the next step to $step.
   *
   * @param \Drupal\forms_steps\Step $step
   *   The current Step.
   *
   * @return \Drupal\forms_steps\Step|null
   *   Returns the next Step or null if no next step found.
   */
  public function getNextStep(Step $step): ?Step;

  /**
   * Returns the previous step to $step.
   *
   * @param \Drupal\forms_steps\Step $step
   *   The current Step.
   *
   * @return \Drupal\forms_steps\Step|null
   *   Returns the previous Step or first step if no previous step found.
   */
  public function getPreviousStep(Step $step): ?Step;

  /**
   * Set the label of the delete button of the step.
   *
   * @param string $step_id
   *   Step id.
   * @param mixed $label
   *   Label to set.
   *
   * @return \Drupal\forms_steps\FormsStepsInterface
   *   The forms steps.
   */
  public function setStepDeleteLabel(string $step_id, $label): FormsStepsInterface;

  /**
   * Set the delete state (hidden or shown) of the step.
   *
   * @param string $step_id
   *   Step id.
   * @param bool $state
   *   State to set.
   *
   * @return \Drupal\forms_steps\FormsStepsInterface
   *   The forms steps.
   */
  public function setStepDeleteState(string $step_id, bool $state): FormsStepsInterface;

  /**
   * Set the label of the previous button of the step.
   *
   * @param string $step_id
   *   Step id.
   * @param mixed $label
   *   Label to set.
   *
   * @return \Drupal\forms_steps\FormsStepsInterface
   *   The forms steps.
   */
  public function setStepPreviousLabel(string $step_id, $label): FormsStepsInterface;

  /**
   * Set the previous state (hidden or displayed) of the step.
   *
   * @param string $step_id
   *   Step id.
   * @param bool $state
   *   State to set.
   *
   * @return \Drupal\forms_steps\FormsStepsInterface
   *   The forms steps.
   */
  public function setStepPreviousState(string $step_id, bool $state): FormsStepsInterface;

}
