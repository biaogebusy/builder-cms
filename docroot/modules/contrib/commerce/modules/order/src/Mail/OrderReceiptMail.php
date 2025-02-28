<?php

namespace Drupal\commerce_order\Mail;

use Drupal\commerce\MailHandlerInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\OrderTotalSummaryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Utility\Token;

class OrderReceiptMail implements OrderReceiptMailInterface {

  use StringTranslationTrait;

  /**
   * Constructs a new OrderReceiptMail object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\commerce\MailHandlerInterface $mailHandler
   *   The mail handler.
   * @param \Drupal\commerce_order\OrderTotalSummaryInterface $orderTotalSummary
   *   The order total summary.
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   */
  public function __construct(protected EntityTypeManagerInterface $entityTypeManager, protected MailHandlerInterface $mailHandler, protected OrderTotalSummaryInterface $orderTotalSummary, protected Token $token) {}

  /**
   * {@inheritdoc}
   */
  public function send(OrderInterface $order, $to = NULL, $bcc = NULL, bool $resend = FALSE) {
    $to = $to ?? $order->getEmail();
    if (!$to) {
      // The email should not be empty.
      return FALSE;
    }

    /** @var \Drupal\Core\Entity\EntityStorageInterface $order_type_storage */
    $order_type_storage = $this->entityTypeManager->getStorage('commerce_order_type');
    /** @var \Drupal\commerce_order\Entity\OrderTypeInterface $order_type */
    $order_type = $order_type_storage->load($order->bundle());
    $subject = $order_type->getReceiptSubject();
    if (!empty($subject)) {
      $subject = $this->token->replace($subject, [
        'commerce_order' => $order,
      ]);
    }
    // Provide a default value if the subject line was blank.
    if (empty($subject)) {
      $subject = $this->t('Order #@number confirmed', ['@number' => $order->getOrderNumber()]);
    }
    $body = [
      '#theme' => 'commerce_order_receipt',
      '#order_entity' => $order,
      '#totals' => $this->orderTotalSummary->buildTotals($order),
    ];
    if ($billing_profile = $order->getBillingProfile()) {
      $profile_view_builder = $this->entityTypeManager->getViewBuilder('profile');
      $body['#billing_information'] = $profile_view_builder->view($billing_profile);
    }

    $params = [
      'id' => 'order_receipt',
      'from' => $order->getStore()->getEmailFromHeader(),
      'bcc' => $bcc,
      'order' => $order,
      'resend' => $resend,
    ];
    $customer = $order->getCustomer();
    if (!$customer->isAnonymous()) {
      $params['langcode'] = $customer->getPreferredLangcode();
    }

    return $this->mailHandler->sendMail($to, $subject, $body, $params);
  }

}
