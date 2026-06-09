<?php

declare(strict_types=1);

namespace Drupal\ai_translate\Attribute;

use Drupal\Component\Plugin\Attribute\AttributeBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * The ai_translate field text extractor plugin attribute.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class FieldTextExtractor extends AttributeBase {

  /**
   * Constructs a new FieldTextExtractor instance.
   *
   * @param string $id
   *   The plugin ID. There are some implementation bugs that make the plugin
   *   available only if the ID follows a specific pattern. It must be either
   *   identical to group or prefixed with the group. E.g. if the group is "foo"
   *   the ID must be either "foo" or "foo:bar".
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The human-readable name of the plugin.
   * @param array $field_types
   *   Array of supported field types.
   * @param class-string|null $deriver
   *   (optional) The deriver class.
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
    public readonly array $field_types = [],
    public readonly ?string $deriver = NULL,
  ) {}

}
