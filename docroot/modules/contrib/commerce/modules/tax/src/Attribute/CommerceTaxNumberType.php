<?php

declare(strict_types=1);

namespace Drupal\commerce_tax\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a CommerceTaxNumberType attribute.
 *
 * Additional attribute keys for tax number types can be defined in
 * hook_commerce_tax_number_type_info_alter().
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class CommerceTaxNumberType extends Plugin {

  /**
   * Constructs a CommerceTaxNumberType attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The human-readable name of the tax number type.
   * @param array $countries
   *   (optional) The supported countries.
   * @param array $examples
   *   (optional) Example tax numbers.
   * @param class-string|null $deriver
   *   (optional) The deriver class.
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
    public readonly array $countries = [],
    public readonly array $examples = [],
    public readonly ?string $deriver = NULL,
  ) {
  }

}
