<?php

namespace Drupal\eck\Form\Entity;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for the ECK entity forms.
 *
 * @ingroup eck
 */
class EckEntityForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    parent::save($form, $form_state);
    $form_state->setRedirect('entity.' . $this->entity->getEntityTypeId() . '.canonical', [$this->entity->getEntityTypeId() => $this->entity->id()]);
  }

}
