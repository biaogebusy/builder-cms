<?php

namespace Drupal\commerce_payment\Controller;

use Drupal\Core\Access\AccessException;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\commerce\Response\NeedsRedirectException;
use Drupal\commerce\Utility\Error;
use Drupal\commerce_checkout\CheckoutOrderManagerInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Event\FailedPaymentEvent;
use Drupal\commerce_payment\Event\PaymentEvents;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides checkout endpoints for off-site payments.
 */
class PaymentCheckoutController implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The checkout order manager.
   *
   * @var \Drupal\commerce_checkout\CheckoutOrderManagerInterface
   */
  protected $checkoutOrderManager;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * Constructs a new PaymentCheckoutController object.
   *
   * @param \Drupal\commerce_checkout\CheckoutOrderManagerInterface $checkout_order_manager
   *   The checkout order manager.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   */
  public function __construct(CheckoutOrderManagerInterface $checkout_order_manager, MessengerInterface $messenger, LoggerInterface $logger, EntityTypeManagerInterface $entity_type_manager, EventDispatcherInterface $event_dispatcher) {
    $this->checkoutOrderManager = $checkout_order_manager;
    $this->messenger = $messenger;
    $this->logger = $logger;
    $this->entityTypeManager = $entity_type_manager;
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('commerce_checkout.checkout_order_manager'),
      $container->get('messenger'),
      $container->get('logger.channel.commerce_payment'),
      $container->get('entity_type.manager'),
      $container->get('event_dispatcher')
    );
  }

  /**
   * Provides the "return" checkout payment page.
   *
   * Redirects to the next checkout page, completing checkout.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   */
  public function returnPage(Request $request, RouteMatchInterface $route_match) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order_id = (int) $route_match->getRawParameter('commerce_order');
    $step_id = $route_match->getParameter('step');

    /** @var \Drupal\commerce_order\OrderStorageInterface $order_storage */
    $order_storage = $this->entityTypeManager->getStorage('commerce_order');
    try {
      /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
      $order = $order_storage->loadForUpdate($order_id);
      $this->validateStepId($step_id, $order);

      /** @var \Drupal\commerce_payment\Entity\PaymentGatewayInterface $payment_gateway */
      $payment_gateway = $order->get('payment_gateway')->entity;
      $payment_gateway_plugin = $payment_gateway->getPlugin();
      if (!$payment_gateway_plugin instanceof OffsitePaymentGatewayInterface) {
        throw new AccessException('The payment gateway for the order does not implement ' . OffsitePaymentGatewayInterface::class);
      }
      /** @var \Drupal\commerce_checkout\Entity\CheckoutFlowInterface $checkout_flow */
      $checkout_flow = $order->get('checkout_flow')->entity;
      $checkout_flow_plugin = $checkout_flow->getPlugin();
      // Reset the order so it's not extracted from the route match parameters.
      // This ensures the latest version of the order is used.
      $checkout_flow_plugin->setOrder($order);

      try {
        $payment_gateway_plugin->onReturn($order, $request);
        $redirect_step_id = $checkout_flow_plugin->getNextStepId($step_id);
      }
      catch (NeedsRedirectException $e) {
        throw $e;
      }
      catch (PaymentGatewayException $e) {
        $event = new FailedPaymentEvent($order, $payment_gateway, $e);
        $this->eventDispatcher->dispatch($event, PaymentEvents::PAYMENT_FAILURE);
        Error::logException($this->logger, $e);
        $this->messenger->addError($this->t('Payment failed at the payment server. Please review your information and try again.'));
        $redirect_step_id = $checkout_flow_plugin->getPreviousStepId($step_id);
      }
      catch (\Exception $e) {
        Error::logException($this->logger, $e);
        $this->messenger->addError($this->t('We encountered an issue recording your payment. Please contact customer service to resolve the issue.'));
        $redirect_step_id = $checkout_flow_plugin->getPreviousStepId($step_id);
      }
      $checkout_flow_plugin->redirectToStep($redirect_step_id);
    }
    finally {
      $order_storage->releaseLock($order_id);
    }
  }

  /**
   * Provides the "cancel" checkout payment page.
   *
   * Redirects to the previous checkout page.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   */
  public function cancelPage(Request $request, RouteMatchInterface $route_match) {
    /** @var \Drupal\commerce_order\OrderStorageInterface $order_storage */
    $order_storage = $this->entityTypeManager->getStorage('commerce_order');
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order_id = (int) $route_match->getRawParameter('commerce_order');
    $step_id = $route_match->getParameter('step');
    try {
      $order = $order_storage->loadForUpdate($order_id);
      $this->validateStepId($step_id, $order);

      /** @var \Drupal\commerce_payment\Entity\PaymentGatewayInterface $payment_gateway */
      $payment_gateway = $order->get('payment_gateway')->entity;
      $payment_gateway_plugin = $payment_gateway->getPlugin();
      if (!$payment_gateway_plugin instanceof OffsitePaymentGatewayInterface) {
        throw new AccessException('The payment gateway for the order does not implement ' . OffsitePaymentGatewayInterface::class);
      }
      /** @var \Drupal\commerce_checkout\Entity\CheckoutFlowInterface $checkout_flow */
      $checkout_flow = $order->get('checkout_flow')->entity;
      $checkout_flow_plugin = $checkout_flow->getPlugin();
      // Reset the order so it's not extracted from the route match parameters.
      // This ensures the latest version of the order is used.
      $checkout_flow_plugin->setOrder($order);

      $payment_gateway_plugin->onCancel($order, $request);
      $previous_step_id = $checkout_flow_plugin->getPreviousStepId($step_id);
      $checkout_flow_plugin->redirectToStep($previous_step_id);
    }
    finally {
      $order_storage->releaseLock($order_id);
    }
  }

  /**
   * Validates the requested step ID.
   *
   * Redirects to the actual step ID if the requested one is no longer
   * available. This can happen if payment was already cancelled, or if the
   * payment "notify" endpoint created the payment and placed the order
   * before the customer returned to the site.
   *
   * @param string $requested_step_id
   *   The requested step ID, usually "payment".
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   *
   * @throws \Drupal\commerce\Response\NeedsRedirectException
   */
  protected function validateStepId($requested_step_id, OrderInterface $order) {
    $step_id = $this->checkoutOrderManager->getCheckoutStepId($order);
    if ($requested_step_id != $step_id) {
      throw new NeedsRedirectException(Url::fromRoute('commerce_checkout.form', [
        'commerce_order' => $order->id(),
        'step' => $step_id,
      ])->toString());
    }
  }

}
