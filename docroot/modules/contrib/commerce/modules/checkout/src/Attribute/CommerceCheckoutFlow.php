<?php

declare(strict_types=1);

namespace Drupal\commerce_checkout\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a CommerceCheckoutFlow attribute.
 *
 * Additional attribute keys for checkout flows can be defined in
 * hook_commerce_checkout_flow_info_alter().
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class CommerceCheckoutFlow extends Plugin {

  /**
   * Constructs a CommerceCheckoutFlow attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The checkout flow label.
   * @param class-string|null $deriver
   *   (optional) The deriver class.
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
    public readonly ?string $deriver = NULL,
  ) {
  }

}
