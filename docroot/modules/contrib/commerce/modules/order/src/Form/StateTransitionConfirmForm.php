<?php

namespace Drupal\commerce_order\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\state_machine\Form\StateTransitionConfirmForm as StateTransitionConfirmFormBase;

/**
 * Provides the order state transition confirmation form.
 *
 * Adds a checkbox letting admins opt out of the order receipt email when
 * manually placing an order.
 */
class StateTransitionConfirmForm extends StateTransitionConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $field_name = '', $transition_id = '') {
    $form = parent::buildForm($form, $form_state, $field_name, $transition_id);

    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $this->entity;
    if ($this->transition->getId() === 'place') {
      /** @var \Drupal\commerce_order\Entity\OrderTypeInterface $order_type */
      $order_type = $this->entityTypeManager->getStorage('commerce_order_type')->load($order->bundle());
      if ($order_type->shouldSendReceipt()) {
        $form['send_receipt'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Email the customer an order receipt'),
          '#default_value' => TRUE,
        ];
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if (isset($form['send_receipt']) && !$form_state->getValue('send_receipt')) {
      $this->entity->setData('skip_order_receipt', TRUE);
    }
    parent::submitForm($form, $form_state);
  }

}
