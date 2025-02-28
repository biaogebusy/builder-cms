<?php

declare(strict_types=1);

namespace Drupal\forms_steps\Form;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\forms_steps\FormsStepsInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provides a form to delete a Step.
 */
class FormsStepsStepDeleteForm extends ConfirmFormBase {

  /**
   * The forms_steps entity the step being deleted belongs to.
   *
   * @var \Drupal\forms_steps\FormsStepsInterface
   */
  protected FormsStepsInterface $formsSteps;

  /**
   * The step being deleted.
   *
   * @var string
   */
  protected string $stepId;

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'forms_steps_step_delete_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    return $this->t('Are you sure you want to delete %step from %forms_steps?', [
      '%step' => $this->formsSteps->getStep($this->stepId)
        ->label(),
      '%forms_steps' => $this->formsSteps->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function getCancelUrl(): Url {
    return $this->formsSteps->toUrl();
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText(): TranslatableMarkup {
    return $this->t('Delete');
  }

  /**
   * Form constructor.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Drupal\forms_steps\FormsStepsInterface|null $forms_steps
   *   The forms_steps entity being edited.
   * @param string|null $forms_steps_step
   *   The forms_steps step being deleted.
   *
   * @return array
   *   The form structure.
   */
  public function buildForm(array $form, FormStateInterface $form_state, FormsStepsInterface $forms_steps = NULL, string $forms_steps_step = NULL): array {
    if (!$forms_steps->hasStep($forms_steps_step)) {
      throw new NotFoundHttpException();
    }
    $this->formsSteps = $forms_steps;
    $this->stepId = $forms_steps_step;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $forms_steps_label = $this->formsSteps->getStep($this->stepId)
      ->label();
    $this->formsSteps
      ->deleteStep($this->stepId)
      ->save();

    // @todo Check if there is a way to just update the current route ?!
    /** @var \Drupal\Core\Routing\RouteBuilder $routeBuilderService */
    $routeBuilderService = \Drupal::service('router.builder');
    $routeBuilderService->rebuild();

    $this->messenger()->addMessage($this->t(
      'Step %label deleted.',
      ['%label' => $forms_steps_label]
    ));

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
