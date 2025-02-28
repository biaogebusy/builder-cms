<?php

declare(strict_types=1);

namespace Drupal\forms_steps\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides a form to edit a Progress step.
 */
class FormsStepsProgressStepEditForm extends EntityForm {

  /**
   * The ID of the progress step that is being edited.
   *
   * @var string
   */
  protected string $progressStepId;

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'forms_steps_progress_step_edit_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $forms_steps_progress_step = NULL) {
    $this->progressStepId = $forms_steps_progress_step;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\forms_steps\FormsStepsInterface $forms_steps */
    $forms_steps = $this->getEntity();
    $progress_step = $forms_steps->getProgressStep($this->progressStepId);

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $progress_step->label(),
      '#description' => $this->t('Label for the progress step.'),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#machine_name' => [
        'exists' => [$this, 'exists'],
      ],
      '#default_value' => $progress_step->id(),
      '#disabled' => TRUE,
    ];

    $steps = $forms_steps->getSteps();

    // Warn the user if there are no steps.
    if (empty($steps)) {
      $this->messenger()->addWarning(
        $this->t(
          'This Forms Steps has no steps and will be disabled until there is at least one, <a href=":add-step">add a new step.</a>',
          [':add-step' => $forms_steps->toUrl('add-step-form')->toString()]
        )
      );
    }

    // [$this->t('There are no steps yet.')].
    $options = [];
    foreach ($steps as $step) {
      $options[$step->id()] = $step->label();
    }

    if (!empty($steps)) {
      $form['routes'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Steps of activation'),
        '#description' => $this->t('Select the steps for which the current progress step should be active.'),
        '#required' => TRUE,
        '#options' => $options,
        '#default_value' => $progress_step->activeRoutes(),
      ];

      $form['link'] = [
        '#type' => 'select',
        '#title' => $this->t('Link'),
        '#description' => $this->t('Select the step for which the current progress step should redirect on click. Leave empty for no link on this progress step.'),
        '#empty_option' => $this->t('- None -'),
        '#options' => $options,
        '#default_value' => $progress_step->link(),
      ];

      $form['link_visibility'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Link visibility'),
        '#description' => $this->t('Select the steps for which the link will be shown.'),
        '#options' => $options,
        '#states' => [
          'invisible' => [
            ':input[name="link"]' => [
              'value' => '',
            ],
          ],
        ],
        '#default_value' => $progress_step->linkVisibility(),
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\forms_steps\FormsStepsInterface $forms_steps */
    $forms_steps = $this->entity;

    $forms_steps->save();
    $this->messenger()->addMessage($this->t('Saved %label progress step.', [
      '%label' => $forms_steps->getProgressStep($this->progressStepId)->label(),
    ]));
    $form_state->setRedirectUrl($forms_steps->toUrl('edit-form'));
  }

  /**
   * Copies top-level form values to entity properties.
   *
   * This form can only change values for a step, which is part of forms_steps.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity the current form should operate upon.
   * @param array $form
   *   A nested array of form elements comprising the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  protected function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state) {
    /** @var \Drupal\forms_steps\FormsStepsInterface $entity */
    $values = $form_state->getValues();

    $entity->setProgressStepLabel($values['id'], $values['label']);
    $entity->setProgressStepActiveRoutes($values['id'], $values['routes']);
    $entity->setProgressStepLink($values['id'], $values['link']);
    $entity->setProgressStepLinkVisibility($values['id'], $values['link_visibility']);
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state): array {
    $actions['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#submit' => ['::submitForm', '::save'],
    ];

    $actions['delete'] = [
      '#type' => 'link',
      '#title' => $this->t('Delete'),
      '#access' => $this->entity->access('delete-progress-step:' . $this->progressStepId),
      '#attributes' => [
        'class' => ['button', 'button--danger'],
      ],
      '#url' => Url::fromRoute('entity.forms_steps.delete_progress_step_form', [
        'forms_steps' => $this->entity->id(),
        'forms_steps_progress_step' => $this->progressStepId,
      ]),
    ];

    return $actions;
  }

}
