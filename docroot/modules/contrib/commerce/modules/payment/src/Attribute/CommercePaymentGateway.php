<?php

declare(strict_types=1);

namespace Drupal\commerce_payment\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\commerce_payment\CreditCard;

/**
 * Defines a CommercePaymentGateway attribute.
 *
 * Additional attribute keys for payment gateways can be defined in
 * hook_commerce_payment_gateway_info_alter().
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class CommercePaymentGateway extends Plugin {

  /**
   * Constructs a CommercePaymentGateway attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The payment gateway label.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $display_label
   *   The payment gateway display label.
   * @param array $modes
   *   (optional) An array of mode labels keyed by machine name.
   * @param array $forms
   *   An array of form classes keyed by operation.
   *   For example:
   *   @code
   *    'add-payment-method' => "Drupal\commerce_payment\PluginForm\PaymentMethodAddForm",
   *    'capture-payment' => "Drupal\commerce_payment\PluginForm\PaymentCaptureForm",
   *   @endcode
   * @param string|null $js_library
   *   (optional) The JS library ID.
   * @param string $payment_type
   *   The payment type used by the payment gateway.
   * @param array $payment_method_types
   *   (optional) The payment method types handled by the payment gateway.
   * @param string|null $default_payment_method_type
   *   (optional) The default payment method type.
   *   Defaults to the first payment method type if no value is provided.
   * @param array $credit_card_types
   *   (optional) The credit card types handled by the payment gateway.
   * @param bool $requires_billing_information
   *   Whether the payment gateway requires billing information to be collected.
   *   Defaults to TRUE.
   * @param array $libraries
   *   (optional) The library IDs.
   * @param class-string|null $deriver
   *   (optional) The deriver class.
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
    public readonly TranslatableMarkup $display_label,
    public array $modes = [],
    public readonly array $forms = [],
    public readonly ?string $js_library = NULL,
    public readonly string $payment_type = 'payment_default',
    public readonly array $payment_method_types = ['credit_card'],
    public ?string $default_payment_method_type = NULL,
    public array $credit_card_types = [],
    public readonly bool $requires_billing_information = TRUE,
    public readonly array $libraries = [],
    public readonly ?string $deriver = NULL,
  ) {
    if (empty($this->modes)) {
      $this->modes = [
        'test' => new TranslatableMarkup('Test'),
        'live' => new TranslatableMarkup('Live'),
      ];
    }
    if (empty($this->default_payment_method_type)) {
      $this->default_payment_method_type = $this->payment_method_types[0];
    }
    if (empty($this->credit_card_types)) {
      $this->credit_card_types = array_keys(CreditCard::getTypes());
    }
    if (!empty($this->js_library)) {
      @trigger_error('\Drupal\commerce_payment\Attribute\CommercePaymentGateway::js_library has been deprecated in favor of \Drupal\commerce_payment\Attribute\CommercePaymentGateway::libraries. Use that instead.');
    }
  }

}
