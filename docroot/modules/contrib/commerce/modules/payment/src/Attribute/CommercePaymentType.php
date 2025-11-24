<?php

declare(strict_types=1);

namespace Drupal\commerce_payment\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a CommercePaymentType attribute.
 *
 * Additional attribute keys for payment types can be defined in
 * hook_commerce_payment_type_info_alter().
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class CommercePaymentType extends Plugin {

  /**
   * Constructs a CommercePaymentType attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The human-readable name of the payment type.
   * @param string $workflow
   *   (optional) The workflow (defaults to "payment_default").
   * @param class-string|null $deriver
   *   (optional) The deriver class.
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
    public readonly string $workflow = 'payment_default',
    public readonly ?string $deriver = NULL,
  ) {
  }

}
