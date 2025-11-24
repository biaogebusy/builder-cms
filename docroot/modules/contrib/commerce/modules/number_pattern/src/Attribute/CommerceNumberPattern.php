<?php

declare(strict_types=1);

namespace Drupal\commerce_number_pattern\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a CommerceNumberPattern attribute.
 *
 * Additional attribute keys for number patterns can be defined in
 * hook_commerce_number_pattern_info_alter().
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class CommerceNumberPattern extends Plugin {

  /**
   * Constructs a CommerceNumberPattern attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The number pattern label.
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
