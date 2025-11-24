<?php

namespace Drupal\commerce_order\Form;

use Drupal\Core\Entity\ContentEntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a confirmation form for resending order receipts.
 */
class OrderReceiptResendForm extends ContentEntityConfirmFormBase {

  /**
   * The order receipt mail.
   *
   * @var \Drupal\commerce_order\Mail\OrderReceiptMailInterface
   */
  protected $orderReceiptMail;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->orderReceiptMail = $container->get('commerce_order.order_receipt_mail');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to resend the receipt for %label?', [
      '%label' => $this->entity->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Resend receipt');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return $this->entity->toUrl();
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $this->entity;
    $form['to'] = [
      '#type' => 'email',
      '#title' => $this->t('Send to'),
      '#default_value' => $order->getEmail(),
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $this->entity;
    $result = $this->orderReceiptMail->send($order, $form_state->getValue('to'), NULL, TRUE);
    // Drupal's MailManager sets an error message itself, if the sending failed.
    if ($result) {
      $this->messenger()->addMessage($this->t('Order receipt resent.'));
    }

    $form_state->setRedirectUrl($this->entity->toUrl());
  }

}
