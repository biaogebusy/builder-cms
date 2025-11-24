<?php

declare(strict_types=1);

namespace Drupal\diff\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a FieldDiffBuilder attribute object.
 *
 * Diff builders handle how fields are compared by the diff module.
 *
 * Additional attribute keys for diff builders can be defined in
 * hook_field_diff_builder_info_alter().
 *
 * @see \Drupal\diff\FieldDiffBuilderPluginManager
 * @see \Drupal\diff\FieldDiffBuilderInterface
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class FieldDiffBuilder extends Plugin {

  /**
   * Constructs a FieldDiffBuilder attribute.
   */
  public function __construct(
    public readonly string $id,
    public readonly ?TranslatableMarkup $label = NULL,
    public readonly array $field_types = [],
    public readonly int $weight = 0,
  ) {}

}
