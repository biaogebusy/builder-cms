<?php

declare(strict_types=1);

namespace Drupal\forms_steps\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides a form to edit a Step.
 */
class FormsStepsStepEditForm extends FormsStepsStepFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'forms_steps_step_edit_form';
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);

    $form['id']['#default_value'] = $this->stepId;
    $form['id']['#disabled'] = TRUE;

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
    $this->messenger()->addMessage($this->t('Saved %label step.', [
      '%label' => $forms_steps->getStep($this->stepId)->label(),
    ]));
    $form_state->setRedirectUrl($forms_steps->toUrl('edit-form'));

    // We force the cache clearing as the core doesn't do it by itself.
    Cache::invalidateTags([
      'entity_types',
      'routes',
      'local_tasks',
      'local_task',
      'local_action',
      'rendered',
    ]);
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
      '#access' => $this->entity->access('delete-state:' . $this->stepId),
      '#attributes' => [
        'class' => ['button', 'button--danger'],
      ],
      '#url' => Url::fromRoute('entity.forms_steps.delete_step_form', [
        'forms_steps' => $this->entity->id(),
        'forms_steps_step' => $this->stepId,
      ]),
    ];

    return $actions;
  }

}
