<?php

declare(strict_types=1);

namespace Drupal\commerce_payment\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a CommercePaymentMethodType attribute.
 *
 * Additional attribute keys for payment method types can be defined in
 * hook_commerce_payment_method_type_info_alter().
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class CommercePaymentMethodType extends Plugin {

  /**
   * Constructs a CommercePaymentMethodType attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The human-readable name of the payment type.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $create_label
   *   (optional) The payment method type create label, defaults to the label.
   * @param class-string|null $deriver
   *   (optional) The deriver class.
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
    public ?TranslatableMarkup $create_label = NULL,
    public readonly ?string $deriver = NULL,
  ) {
    if (empty($this->create_label)) {
      $this->create_label = $this->label;
    }
  }

}
