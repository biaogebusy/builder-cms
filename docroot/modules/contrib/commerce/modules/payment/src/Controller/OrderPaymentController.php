<?php

namespace Drupal\commerce_payment\Controller;

use Drupal\commerce\Response\NeedsRedirectException;
use Drupal\commerce\Utility\Error;
use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
use Drupal\commerce_payment\Event\FailedPaymentEvent;
use Drupal\commerce_payment\Event\PaymentEvents;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayInterface;
use Drupal\Core\Access\AccessException;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides order payment gateway endpoints for payments.
 */
class OrderPaymentController implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected MessengerInterface $messenger;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static();
    $instance->messenger = $container->get('messenger');
    $instance->logger = $container->get('logger.channel.commerce_payment');
    $instance->eventDispatcher = $container->get('event_dispatcher');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * Provides the merchant-facing "return" page for manual/off-site payments.
   *
   * Called when a merchant uses the "Add payment" form in the order UI and the
   * payment gateway redirects back after attempting a transaction.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentGatewayInterface $commerce_payment_gateway
   *   The payment gateway.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response to the order’s payment collection page.
   *
   * @throws \Drupal\Core\Access\AccessException
   *   If the payment gateway does not implement the required interface.
   */
  public function merchantReturn(PaymentGatewayInterface $commerce_payment_gateway, Request $request, RouteMatchInterface $route_match): RedirectResponse {
    $payment_gateway_plugin = $commerce_payment_gateway->getPlugin();
    if (!$payment_gateway_plugin instanceof OffsitePaymentGatewayInterface) {
      throw new AccessException('The payment gateway for the order does not implement ' . OffsitePaymentGatewayInterface::class);
    }

    /** @var \Drupal\commerce_order\OrderStorageInterface $order_storage */
    $order_storage = $this->entityTypeManager->getStorage('commerce_order');
    $order_id = (int) $route_match->getRawParameter('commerce_order');
    try {
      $order = $order_storage->loadForUpdate($order_id);

      try {
        $payment_gateway_plugin->onReturn($order, $request);
        $order->set('payment_gateway', $commerce_payment_gateway);
        $order->save();
      }
      catch (NeedsRedirectException $e) {
        // Safe to ignore: this method always redirects merchant back to the
        // order's payments collection.
      }
      catch (PaymentGatewayException $e) {
        $event = new FailedPaymentEvent($order, $commerce_payment_gateway, $e);
        $this->eventDispatcher->dispatch($event, PaymentEvents::PAYMENT_FAILURE);
        Error::logException($this->logger, $e);
        $this->messenger->addError(t('Payment failed at the payment server. Please review your information and try again.'));
      }
      catch (\Exception $e) {
        Error::logException($this->logger, $e);
        $this->messenger->addError(t('We encountered an issue recording your payment. Check the logs for details.'));
      }
      // Redirect merchant back to the order's payments collection.
      $redirect_url = Url::fromRoute('entity.commerce_payment.collection', [
        'commerce_order' => $order_id,
      ], ['absolute' => TRUE]);

      return new RedirectResponse($redirect_url->toString());
    }
    finally {
      $order_storage->releaseLock($order_id);
    }
  }

}
