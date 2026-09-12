<?php

namespace Drupal\commerce_order\Form;

use Drupal\Core\Entity\ContentEntityConfirmFormBase;
use Drupal\Core\Entity\EntityDeleteFormTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides the order item removal form.
 */
class OrderItemDeleteForm extends ContentEntityConfirmFormBase {

  use EntityDeleteFormTrait {
    getQuestion as traitGetQuestion;
  }

  /**
   * The order item.
   *
   * @var \Drupal\commerce_order\Entity\OrderItemInterface
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return $this->entity->getOrder()?->toUrl() ?? Url::fromRoute('<front>');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    if ($this->getRequest()->isXmlHttpRequest()) {
      return $this->traitGetQuestion();
    }
    return parent::getDescription();
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->entity->isNew() ? $this->t('Remove order item') : $this->t('Delete order item');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $order = $this->entity->getOrder();

    // Resave related order to update totals.
    if ($order) {
      $order->removeItem($this->entity);
      $order->save();
      $form_state->setRedirectUrl($order->toUrl());
    }
    $this->entity->delete();
  }

}
