<?php

namespace Drupal\footable\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\footable\Entity\FooTableBreakpoint;

/**
 * Edit form for FooTable Breakpoints.
 */
class FooTableBreakpointEditForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    /** @var FooTableBreakpoint $breakpoint */
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

}
