<?php

declare(strict_types=1);

namespace Drupal\commerce\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a CommerceInlineForm attribute.
 *
 * Additional attribute keys for entity traits can be defined in
 * hook_commerce_inline_form_info_alter().
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class CommerceInlineForm extends Plugin {

  /**
   * Constructs a CommerceInlineForm attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The human-readable name of the inline form.
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
