<?php

declare(strict_types=1);

namespace Drupal\forms_steps\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form to add a Step.
 */
class FormsStepsStepAddForm extends FormsStepsStepFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'forms_steps_step_add_form';
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

    // @todo Check if there is a way to just update the current route ?!
    /** @var \Drupal\Core\Routing\RouteBuilder $routeBuilderService */
    $routeBuilderService = \Drupal::service('router.builder');
    $routeBuilderService->rebuild();

    $this->messenger()->addMessage($this->t('Created %label step.', [
      '%label' => $forms_steps->getStep($form_state->getValue('id'))
        ->label(),
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
    return $actions;
  }

}
