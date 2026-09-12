<?php

namespace Drupal\commerce_order\Form;

use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Base class for all order forms with a predefined list of components.
 */
abstract class OrderStaticDisplayFormBase extends OrderFormBase {

  /**
   * Returns the list of required components in called form.
   */
  abstract protected function getRequiredComponents(): array;

  /**
   * {@inheritdoc}
   */
  public function setFormDisplay(EntityFormDisplayInterface $form_display, FormStateInterface $form_state) {
    // Return default form display when list of required components is empty.
    $required_components = $this->getRequiredComponents();
    if (empty($required_components)) {
      return parent::setFormDisplay($form_display, $form_state);
    }

    // Reorder and override form components.
    $hidden_components = $form_display->get('hidden');
    foreach (array_keys($form_display->getComponents()) as $component_name) {
      if (!in_array($component_name, array_keys($required_components))) {
        $form_display->removeComponent($component_name);
        $hidden_components[$component_name] = TRUE;
        continue;
      }
      if (!empty($required_components[$component_name])) {
        $form_display->setComponent($component_name, $required_components[$component_name]);
      }
    }
    ksort($hidden_components);
    $form_display->set('hidden', $hidden_components);

    return parent::setFormDisplay($form_display, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    foreach ($actions as $operation => &$action) {
      if ($operation !== 'submit') {
        $action['#access'] = FALSE;
      }
    }

    $actions['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => $this->entity->toUrl(),
      '#attributes' => [
        'class' => ['button', 'dialog-cancel'],
      ],
    ];

    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $form_state->setRedirectUrl($this->entity->toUrl());
  }

}
