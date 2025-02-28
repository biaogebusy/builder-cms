<?php

declare(strict_types=1);

namespace Drupal\forms_steps\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form to add a Progress step.
 */
class FormsStepsProgressStepAddForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'forms_steps_progress_step_add_form';
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\forms_steps\FormsStepsInterface $forms_steps */
    $forms_steps = $this->entity;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => '',
      '#description' => $this->t('Label for the progress step.'),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#machine_name' => [
        'exists' => [$this, 'exists'],
      ],
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
      ];

      $form['link'] = [
        '#type' => 'select',
        '#title' => $this->t('Link'),
        '#description' => $this->t('Select the step for which the current progress step should redirect on click. Leave empty for no link on this progress step.'),
        '#empty_option' => $this->t('- None -'),
        '#options' => $options,
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
      ];
    }

    return $form;
  }

  /**
   * Determines if the forms steps progress step already exists.
   *
   * @param string $progress_step_id
   *   The forms steps progress step ID.
   *
   * @return bool
   *   TRUE if the forms steps progress step exists, FALSE otherwise.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function exists(string $progress_step_id): bool {
    /** @var \Drupal\forms_steps\FormsStepsInterface $original_forms_steps */
    $original_forms_steps = $this->entityTypeManager
      ->getStorage('forms_steps')
      ->loadUnchanged($this->getEntity()->id());
    return $original_forms_steps->hasProgressStep($progress_step_id);
  }

  /**
   * Copies top-level form values to entity properties.
   *
   * This form can only change values for a progress step, which is part of
   * forms_steps.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity the current form should operate upon.
   * @param array $form
   *   A nested array of form elements comprising the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current progress step of the form.
   */
  protected function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state) {
    /** @var \Drupal\forms_steps\FormsStepsInterface $entity */
    $values = $form_state->getValues();

    // This is fired twice, so we have to check that the entity does not already
    // have the progress step.
    if (!$entity->hasProgressStep($values['id'])) {
      $entity->addProgressStep($values['id'], $values['label'], $values['routes'], $values['link'], $values['link_visibility']);
    }
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

    $this->messenger()->addMessage($this->t('Created %label progress step.', [
      '%label' => $forms_steps->getProgressStep($form_state->getValue('id'))
        ->label(),
    ]));
    $form_state->setRedirectUrl($forms_steps->toUrl('edit-form'));
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
    return $actions;
  }

}
