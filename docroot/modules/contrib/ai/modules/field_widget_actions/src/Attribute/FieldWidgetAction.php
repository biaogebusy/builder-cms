<?php

namespace Drupal\field_widget_actions\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * The field widget action attribute.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class FieldWidgetAction extends Plugin {

  /**
   * Constructs a new FieldWidgetAction instance.
   *
   * @param string $id
   *   The plugin ID. There are some implementation bugs that make the plugin
   *   available only if the ID follows a specific pattern. It must be either
   *   identical to group or prefixed with the group. E.g. if the group is "foo"
   *   the ID must be either "foo" or "foo:bar".
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The human-readable name of the plugin.
   * @param array $widget_types
   *   The list of widget types to work on.
   * @param array $field_types
   *   The list of field types to work on.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $category
   *   The plugin category.
   * @param bool $multiple
   *   If TRUE, the button will be shown for each element of multivalue field.
   * @param class-string|null $deriver
   *   (optional) The deriver class.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $description
   *   The human-readable name of the plugin.
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
    public readonly array $widget_types,
    public readonly array $field_types,
    public readonly ?TranslatableMarkup $category = NULL,
    public readonly bool $multiple = TRUE,
    public readonly ?string $deriver = NULL,
    public readonly ?TranslatableMarkup $description = NULL,
  ) {
  }

}
