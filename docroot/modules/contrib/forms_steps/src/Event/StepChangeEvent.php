<?php

declare(strict_types=1);

namespace Drupal\forms_steps\Event;

use Drupal\Core\Form\FormState;
use Drupal\forms_steps\Entity\FormsSteps;
use Drupal\forms_steps\Step;
use Drupal\Component\EventDispatcher\Event;

/**
 * Event that is fired when before we switch from one step to another.
 */
class StepChangeEvent extends Event {

  const STEP_CHANGE_EVENT = 'forms_steps.step_change_event';

  /**
   * The FormsSteps object.
   *
   * @var \Drupal\forms_steps\Entity\FormsSteps
   */
  public FormsSteps $formsSteps;

  /**
   * The current step.
   *
   * @var \Drupal\forms_steps\StepInterface
   */
  public $fromStep;

  /**
   * The step we are jumping to (can be previous one or next one).
   *
   * @var \Drupal\forms_steps\StepInterface
   */
  public $toStep;

  /**
   * The formState object.
   *
   * @var \Drupal\Core\Form\FormState
   */
  public FormState $formState;

  /**
   * Constructs the object.
   *
   * @param \Drupal\forms_steps\Entity\FormsSteps $forms_steps
   *   The FormsSteps object.
   * @param \Drupal\forms_steps\Step $from_step
   *   The step the user is currently on.
   * @param \Drupal\forms_steps\Step $to_step
   *   The next/previous step the user is going to be redirected.
   * @param \Drupal\Core\Form\FormState $form_state
   *   The formState.
   */
  public function __construct(FormsSteps $forms_steps, Step $from_step, Step $to_step, FormState $form_state) {
    $this->formsSteps = $forms_steps;
    $this->fromStep = $from_step;
    $this->toStep = $to_step;
    $this->formState = $form_state;
  }

}
