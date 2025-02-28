<?php

namespace Drupal\field_validation\Form;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Controller for FieldValidationRuleSet addition forms.
 */
class FieldValidationRuleSetAddForm extends FieldValidationRuleSetFormBase {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state, $entity_type = '') {

    $entity_types = \Drupal::entityTypeManager()->getDefinitions();
    $entity_type_options = [
      '' => $this->t('- Select -'),
    ];

    foreach ($entity_types as $key => $entitytype) {
      if ($entitytype instanceof ContentEntityTypeInterface) {
        $entity_type_options[$key] = $entitytype->getLabel();
      }
    }
    $form['entity_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Entity Type'),
      '#options' => $entity_type_options,
      '#default_value' => $entity_type,
      '#required' => TRUE,
      '#ajax' => [
        'callback' => '::updateBundle',
        'wrapper' => 'edit-bundle-wrapper',
      ],
    ];
    $default_entity_type = $form_state->getValue('entity_type', $entity_type);
    // $default_entity_type = 'node';
    $form['bundle'] = [
      '#type' => 'select',
      '#title' => $this->t('Bundle'),
      '#options' => $this->findBundle($default_entity_type),
      '#required' => TRUE,
      '#prefix' => '<div id="edit-bundle-wrapper">',
      '#suffix' => '</div>',
      '#validated' => TRUE,
    ];
    return parent::form($form, $form_state);
  }

  /**
   * Handles switching the configuration type selector.
   */
  public function updateBundle($form, FormStateInterface $form_state) {
    $form['bundle']['#default_value'] = '';
    $form['bundle']['#options'] = $this->findBundle($form_state->getValue('entity_type'));
    return $form['bundle'];

  }

  /**
   * Handles switching the bundle selector.
   */
  protected function findBundle($entity_type) {
    // \Drupal::logger('field_validation')->notice('1234:' . $field_name);
    $bundle_options = [
      '' => $this->t('- Select -'),
    ];
    if (empty($entity_type)) {
      return $bundle_options;
    }
    else {
      $bundles = \Drupal::service('entity_type.bundle.info')->getBundleInfo($entity_type);
      foreach ($bundles as $key => $bundle) {
        $bundle_options[$key] = $bundle['label'] ?? $key;
      }
    }
    return $bundle_options;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // The fieldValidationRule configuration is stored in the 'data' key in the form,
    // pass that through for validation.
    $entity_type = $form_state->getValue('entity_type');
    $bundle = $form_state->getValue('bundle');
    $ruleset_name = $entity_type . '_' . $bundle;
    $ruleset = \Drupal::entityTypeManager()->getStorage('field_validation_rule_set')->load($ruleset_name);
    if (empty($ruleset)) {
      $form_state->setValue('name', $entity_type . '_' . $bundle);
      $form_state->setValue('label', $entity_type . ' ' . $bundle . ' ' . 'validation');
    }
    else {
      $form_state->setErrorByName('bundle', $this->t('A field validation rule set already exists for this bundle'));
    }

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $this->messenger()->addMessage($this->t('Field validation rule set %name was created.', ['%name' => $this->entity->label()]));
  }

  /**
   * {@inheritdoc}
   */
  public function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    $actions['submit']['#value'] = $this->t('Create new field validation rule set');

    return $actions;
  }

}
