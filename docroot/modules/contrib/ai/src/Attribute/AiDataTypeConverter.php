<?php

declare(strict_types=1);

namespace Drupal\ai\Attribute;

use Drupal\Component\Plugin\Attribute\AttributeBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * The ai_data_type_converter attribute.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class AiDataTypeConverter extends AttributeBase {

  /**
   * Constructs a new AiDataTypeConverter instance.
   *
   * @param string $id
   *   The plugin ID. There are some implementation bugs that make the plugin
   *   available only if the ID follows a specific pattern. It must be either
   *   identical to group or prefixed with the group. E.g. if the group is "foo"
   *   the ID must be either "foo" or "foo:bar".
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $label
   *   (optional) The human-readable name of the plugin.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $description
   *   (optional) A brief description of the plugin.
   * @param int $weight
   *   (optional) The weight of the group. Default is 0.
   * @param class-string|null $deriver
   *   (optional) The deriver class.
   */
  public function __construct(
    public readonly string $id,
    public readonly ?TranslatableMarkup $label,
    public readonly ?TranslatableMarkup $description = NULL,
    public readonly int $weight = 0,
    public readonly ?string $deriver = NULL,
  ) {}

}
