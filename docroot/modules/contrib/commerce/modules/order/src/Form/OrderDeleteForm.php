<?php

namespace Drupal\commerce_order\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;

/**
 * Provides a form for deleting an order.
 */
class OrderDeleteForm extends ContentEntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete %label?', [
      '%label' => $this->getEntity()->label() ?? $this->getEntity()->id(),
    ]);
  }

}
