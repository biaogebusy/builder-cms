<?php

namespace Drupal\profile\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Url;

/**
 * Provides a confirmation form for deleting a profile entity.
 */
class ProfileDeleteForm extends ContentEntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete %label?', [
      '%label' => $this->getEntity()->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  protected function getDeletionMessage() {
    $entity = $this->getEntity();
    return $this->t('%label has been deleted.', [
      '%label' => $entity->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    /** @var \Drupal\profile\Entity\ProfileInterface $entity */
    $entity = $this->entity;
    if ($entity->getOwnerId()) {
      return Url::fromRoute('entity.user.canonical', [
        'user' => $entity->getOwnerId(),
      ]);
    }
    return Url::fromRoute('entity.profile.collection');
  }

  /**
   * {@inheritdoc}
   */
  protected function getRedirectUrl() {
    return $this->getCancelUrl();
  }

}
