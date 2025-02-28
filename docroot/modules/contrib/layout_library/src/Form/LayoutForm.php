<?php

namespace Drupal\layout_library\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines a default form.
 */
class LayoutForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    $form['message'] = [
      '#markup' => $this->t('<a href=":url">Manage layout</a>', [
        ':url' => $this->entity->toUrl('layout-builder')->toString(),
      ]),
    ];
    return $form;
  }

}
