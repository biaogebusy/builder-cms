<?php

namespace Drupal\profile\Form;

use Drupal\Core\Entity\BundleEntityFormBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\entity\Form\EntityDuplicateFormTrait;
use Drupal\field_ui\FieldUI;
use Drupal\user\Entity\Role;

/**
 * Form controller for profile type forms.
 */
class ProfileTypeForm extends BundleEntityFormBase {

  use EntityDuplicateFormTrait;

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    /** @var \Drupal\profile\Entity\ProfileTypeInterface $profile_type */
    $profile_type = $this->entity;

    $form['label'] = [
      '#title' => t('Label'),
      '#type' => 'textfield',
      '#default_value' => $profile_type->label(),
      '#description' => t('The admin-facing name.'),
      '#required' => TRUE,
      '#size' => 30,
    ];
    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $profile_type->id(),
      '#maxlength' => EntityTypeInterface::BUNDLE_MAX_LENGTH,
      '#machine_name' => [
        'exists' => '\Drupal\profile\Entity\ProfileType::load',
        'source' => ['label'],
      ],
    ];
    $form['display_label'] = [
      '#title' => t('Display label'),
      '#type' => 'textfield',
      '#default_value' => $profile_type->getDisplayLabel(),
      '#description' => t('The user-facing name. If provided, shown on user pages instead of the admin-facing name.'),
      '#size' => 30,
    ];
    $form['multiple'] = [
      '#type' => 'checkbox',
      '#title' => t('Allow multiple profiles per user'),
      '#default_value' => $profile_type->allowsMultiple(),
    ];
    $form['registration'] = [
      '#type' => 'checkbox',
      '#title' => t('Include in user registration form'),
      '#default_value' => $profile_type->getRegistration(),
    ];
    $form['roles'] = [
      '#type' => 'checkboxes',
      '#title' => t('Allowed roles'),
      '#description' => $this->t('Limit the users that can have this profile by role.</br><em>None will indicate that all users can have this profile type.</em>'),
      '#options' => [],
      '#default_value' => $profile_type->getRoles(),
    ];
    foreach (Role::loadMultiple() as $role) {
      /** @var \Drupal\user\RoleInterface $role */
      if ($role->id() !== Role::ANONYMOUS_ID) {
        $form['roles']['#options'][$role->id()] = $role->label();
      }
    }

    $form['allow_revisions'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow profiles of this type to be revisioned'),
      '#default_value' => $profile_type->allowsRevisions(),
    ];
    $form['new_revision'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Create a new revision when a profile is modified'),
      '#default_value' => $profile_type->shouldCreateNewRevision(),
      '#states' => [
        'visible' => [
          ':input[name="allow_revisions"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return $this->protectBundleIdElement($form);
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    if ($this->moduleHandler->moduleExists('field_ui') &&
      $this->getEntity()->isNew()
    ) {
      $actions['save_continue'] = $actions['submit'];
      $actions['save_continue']['#value'] = $this->t('Save and manage fields');
      $actions['save_continue']['#submit'][] = [$this, 'redirectToFieldUi'];
    }
    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function buildEntity(array $form, FormStateInterface $form_state) {
    // Filter out unchecked roles.
    $form_state->setValue('roles', array_filter($form_state->getValue('roles')));
    return parent::buildEntity($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\profile\Entity\ProfileTypeInterface $profile_type */
    $profile_type = $this->entity;
    $save = $profile_type->save();
    $this->postSave($profile_type, $this->operation);

    $this->messenger()->addMessage($this->t('Saved the %label profile type.', [
      '%label' => $this->entity->label(),
    ]));
    $form_state->setRedirect('entity.profile_type.collection');
    return $save;
  }

  /**
   * Form submission handler to redirect to Manage fields page of Field UI.
   */
  public function redirectToFieldUi(array $form, FormStateInterface $form_state) {
    if ($form_state->getTriggeringElement()['#parents'][0] === 'save_continue' && $route_info = FieldUI::getOverviewRouteInfo('profile', $this->entity->id())) {
      $form_state->setRedirectUrl($route_info);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function delete(array $form, FormStateInterface $form_state) {
    $form_state->setRedirect('entity.profile_type.delete_form', [
      'profile_type' => $this->entity->id(),
    ]);
  }

}
