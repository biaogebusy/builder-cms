<?php

declare(strict_types=1);

namespace Drupal\ai\Attribute;

use Drupal\Component\Plugin\Attribute\AttributeBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * The ai function group attribute.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class FunctionGroup extends AttributeBase {

  /**
   * A group to wrap around a function call method.
   *
   * @param string $id
   *   The plugin ID. There are some implementation bugs that make the plugin
   *   available only if the ID follows a specific pattern. It must be either
   *   identical to group or prefixed with the group. E.g. if the group is "foo"
   *   the ID must be either "foo" or "foo:bar".
   *   Try to keep module name as a prefix and camelcase to underscore.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $group_name
   *   The group name.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $description
   *   The description of the group .
   * @param int $weight
   *   (optional) The weight of the group. Default is 0.
   * @param class-string|null $deriver
   *   (optional) The deriver class.
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $group_name,
    public readonly TranslatableMarkup $description,
    public readonly int $weight = 0,
    public readonly ?string $deriver = NULL,
  ) {
  }

}
