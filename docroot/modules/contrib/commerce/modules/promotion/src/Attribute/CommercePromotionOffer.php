<?php

declare(strict_types=1);

namespace Drupal\commerce_promotion\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a CommercePromotionOffer attribute.
 *
 * Additional attribute keys for promotion offers can be defined in
 * hook_commerce_promotion_offer_info_alter().
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class CommercePromotionOffer extends Plugin {

  /**
   * Constructs a CommercePromotionOffer attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The condition label.
   * @param string $entity_type
   *   The offer entity type ID. This is the entity type ID of the entity
   *   passed to the plugin during execution.
   *   For example: 'commerce_order'.
   * @param class-string|null $deriver
   *   (optional) The deriver class.
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
    public readonly string $entity_type,
    public readonly ?string $deriver = NULL,
  ) {
  }

}
