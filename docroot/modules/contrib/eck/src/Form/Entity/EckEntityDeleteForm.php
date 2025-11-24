<?php

namespace Drupal\eck\Form\Entity;

use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Url;

/**
 * Provides a form for deleting an ECK entity.
 *
 * @ingroup eck
 */
class EckEntityDeleteForm extends ContentEntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function getRedirectUrl() {
    return new Url('eck.entity.' . $this->entity->getEntityTypeId() . '.list');
  }

  /**
   * {@inheritdoc}
   */
  protected function logDeletionMessage() {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $this->getEntity();

    if (!$entity->isDefaultTranslation()) {
      $this->logger($entity->getEntityType()->getProvider())->notice('The @entity-type %label @language translation has been deleted.', [
        '@entity-type' => $entity->getEntityType()->getLabel(),
        '%label'       => $entity->getUntranslated()->label(),
        '@language'    => $entity->language()->getName(),
      ]);
    }
    else {
      $this->traitLogDeletionMessage();
    }
  }

}
