<?php

namespace Drupal\jsonapi_extras\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Builds the form to delete JSON:API Resource Config entities.
 */
class JsonapiResourceConfigDeleteForm extends EntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to revert %id to default?', ['%id' => $this->entity->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.jsonapi_resource_config.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Revert');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->entity->delete();
    $this->messenger()->addStatus($this->t('Resource %id has been reverted to default.', [
      '%id' => $this->entity->id(),
    ]));

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
