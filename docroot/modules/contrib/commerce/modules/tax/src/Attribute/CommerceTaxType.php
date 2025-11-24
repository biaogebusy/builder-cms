<?php

declare(strict_types=1);

namespace Drupal\commerce_tax\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a CommerceTaxType attribute.
 *
 * Additional attribute keys for tax types can be defined in
 * hook_commerce_tax_type_info_alter().
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class CommerceTaxType extends Plugin {

  /**
   * Constructs a CommerceTaxType attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The human-readable name of the tax type.
   * @param int $weight
   *   (optional) The weight of the tax type.
   * @param class-string|null $deriver
   *   (optional) The deriver class.
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
    public readonly int $weight = 0,
    public readonly ?string $deriver = NULL,
  ) {
  }

}
