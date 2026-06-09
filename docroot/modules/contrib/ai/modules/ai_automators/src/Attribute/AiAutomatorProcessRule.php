<?php

declare(strict_types=1);

namespace Drupal\ai_automators\Attribute;

use Drupal\Component\Plugin\Attribute\AttributeBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * The ai automator rules attribute.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class AiAutomatorProcessRule extends AttributeBase {

  /**
   * Constructs a new AiAutomatorProcessRule instance.
   *
   * @param string $id
   *   The plugin ID. There are some implementation bugs that make the plugin
   *   available only if the ID follows a specific pattern. It must be either
   *   identical to group or prefixed with the group. E.g. if the group is "foo"
   *   the ID must be either "foo" or "foo:bar".
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $title
   *   The human-readable name of the plugin.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $description
   *   The human-readable description of the plugin.
   * @param class-string|null $deriver
   *   (optional) The deriver class.
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $title,
    public readonly TranslatableMarkup $description,
    public readonly ?string $deriver = NULL,
  ) {
  }

}
