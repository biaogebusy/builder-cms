<?php

namespace Drupal\footable\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form for FooTable breakpoint entities.
 */
class FooTableBreakpointForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    /* @var \Drupal\footable\Entity\FooTableBreakpointInterface $breakpoint */
    $breakpoint = $this->entity;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $breakpoint->label(),
      '#required' => TRUE,
    ];

    $form['name'] = [
      '#type' => 'machine_name',
      '#maxlength' => EntityTypeInterface::BUNDLE_MAX_LENGTH,
      '#machine_name' => [
        'exists' => '\Drupal\footable\Entity\FooTableBreakpoint::load',
      ],
      '#default_value' => $breakpoint->id(),
      '#disabled' => !$breakpoint->isNew(),
      '#required' => TRUE,
    ];

    $form['breakpoint'] = [
      '#type' => 'number',
      '#title' => $this->t('Breakpoint'),
      '#min' => 1,
      '#field_suffix' => 'px',
      '#default_value' => $breakpoint->getBreakpoint(),
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $status = parent::save($form, $form_state);
    /* @var \Drupal\footable\Entity\FooTableBreakpointInterface $breakpoint */
    $breakpoint = $this->entity;

    $message = $this->t('The FooTable breakpoint %label has been updated.', [
      '%label' => $breakpoint->label(),
    ]);

    if ($status === SAVED_NEW) {
      $message = $this->t('The FooTable breakpoint %label has been added.', [
        '%label' => $breakpoint->label(),
      ]);
    }

    $this->messenger()->addStatus($message);
    $form_state->setRedirect('entity.footable_breakpoint.collection');
  }

}
