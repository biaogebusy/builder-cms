<?php

namespace Drupal\commerce_payment\Hook;

use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\HasPaymentInstructionsInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsStoredPaymentMethodsInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\Element;

/**
 * Theme hook implementations for Commerce Payment.
 */
class CommercePaymentThemeHooks {

  /**
   * Constructs a new CommercePaymentThemeHooks object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'commerce_payment' => [
        'render element' => 'elements',
        'initial preprocess' => static::class . ':preprocessCommercePayment',
      ],
      'commerce_payment__order_view' => [
        'base hook' => 'commerce_payment',
        'render element' => 'elements',
      ],
      'commerce_payment_method' => [
        'render element' => 'elements',
        'initial preprocess' => static::class . ':preprocessCommercePaymentMethod',
      ],
      'commerce_payment_method__credit_card' => [
        'base hook' => 'commerce_payment_method',
        'render element' => 'elements',
      ],
      'commerce_payment_total_summary' => [
        'variables' => [
          'order_entity' => NULL,
        ],
      ],
      'commerce_admin_payment_form' => [
        'render element' => 'form',
      ],
    ];
  }

  /**
   * Prepares variables for payment method templates.
   *
   * Default template: commerce-payment-method.html.twig.
   *
   * @param array $variables
   *   An associative array containing:
   *   - elements: An associative array containing rendered fields.
   *   - attributes: HTML attributes for the containing element.
   */
  public function preprocessCommercePaymentMethod(array &$variables): void {
    /** @var \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method */
    $payment_method = $variables['elements']['#commerce_payment_method'];

    $variables['payment_method_entity'] = $payment_method;
    $variables['payment_method'] = [
      // The label is generated dynamically, so it's not present in 'elements'.
      'label' => [
        '#markup' => $payment_method->label(),
      ],
    ];
    foreach (Element::children($variables['elements']) as $key) {
      $variables['payment_method'][$key] = $variables['elements'][$key];
    }
  }

  /**
   * Implements hook_theme_suggestions_commerce_payment_method().
   */
  #[Hook('theme_suggestions_commerce_payment_method')]
  public function themeSuggestionsCommercePaymentMethod(array $variables): array {
    return _commerce_entity_theme_suggestions('commerce_payment_method', $variables);
  }

  /**
   * Implements hook_preprocess_HOOK().
   */
  #[Hook('preprocess_commerce_checkout_completion_message')]
  public function preprocessCommerceCheckoutCompletionMessage(&$variables): void {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $variables['order_entity'];
    if ($order->get('payment_gateway')->isEmpty()) {
      return;
    }

    /** @var \Drupal\commerce_payment\Entity\PaymentGatewayInterface $payment_gateway */
    $payment_gateway = $order->get('payment_gateway')->entity;
    /** @var \Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\HasPaymentInstructionsInterface $payment_gateway_plugin */
    $payment_gateway_plugin = $payment_gateway->getPlugin();
    if ($payment_gateway_plugin instanceof HasPaymentInstructionsInterface) {
      $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');
      $payments = $payment_storage->loadMultipleByOrder($order);
      $payments = array_filter($payments, function ($payment) use ($payment_gateway) {
        return $payment->getPaymentGatewayId() == $payment_gateway->id();
      });
      $payment = reset($payments);
      if ($payment) {
        $variables['payment_instructions'] = $payment_gateway_plugin->buildPaymentInstructions($payment);
      }
    }
  }

  /**
   * Implements hook_preprocess_HOOK().
   */
  #[Hook('preprocess_commerce_order')]
  public function preprocessCommerceOrder(&$variables): void {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $variables['order_entity'];
    /** @var \Drupal\commerce_payment\Entity\PaymentGatewayInterface $payment_gateway */
    $payment_gateway = $order->get('payment_gateway')->entity;
    /** @var \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method */
    $payment_method = $order->get('payment_method')->entity;

    // The payment_method variable represents the selected payment option.
    // Uses the payment gateway display label if payment methods are not
    // supported, matching the logic in PaymentOptionsBuilder::buildOptions().
    $variables['payment_method'] = NULL;
    if ($payment_method) {
      $variables['payment_method'] = [
        '#markup' => $payment_method->label(),
      ];
    }
    elseif ($payment_gateway) {
      $payment_gateway_plugin = $payment_gateway->getPlugin();
      if (!($payment_gateway_plugin instanceof SupportsStoredPaymentMethodsInterface)) {
        $variables['payment_method'] = [
          '#markup' => $payment_gateway_plugin->getDisplayLabel(),
        ];
      }
    }
  }

  /**
   * Implements hook_preprocess_HOOK().
   */
  #[Hook('preprocess_commerce_order_receipt')]
  public function preprocessCommerceOrderReceipt(&$variables): void {
    $this->preprocessCommerceOrder($variables);
  }

  /**
   * Implements hook_theme_suggestions_commerce_payment().
   */
  #[Hook('theme_suggestions_commerce_payment')]
  public function themeSuggestionsCommercePayment(array $variables): array {
    return _commerce_entity_theme_suggestions('commerce_payment', $variables);
  }

  /**
   * Prepares variables for payment templates.
   *
   * Default template: commerce-payment.html.twig.
   *
   * @param array $variables
   *   An associative array containing:
   *   - elements: An associative array containing rendered fields.
   *   - attributes: HTML attributes for the containing element.
   */
  public function preprocessCommercePayment(array &$variables): void {
    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
    $payment = $variables['elements']['#commerce_payment'];
    foreach (Element::children($variables['elements']) as $key) {
      $variables['payment'][$key] = $variables['elements'][$key];
    }
    $variables['payment_entity'] = $payment;
  }

}
