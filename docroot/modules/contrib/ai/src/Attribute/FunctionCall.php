<?php

declare(strict_types=1);

namespace Drupal\ai\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;

/**
 * The ai function call attribute.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class FunctionCall extends Plugin {

  /**
   * An attribute to wrap around a function call method.
   *
   * @param string $id
   *   The plugin ID. There are some implementation bugs that make the plugin
   *   available only if the ID follows a specific pattern. It must be either
   *   identical to group or prefixed with the group. E.g. if the group is "foo"
   *   the ID must be either "foo" or "foo:bar".
   *   Try to keep module name as a prefix and camelcase to underscore.
   * @param string $function_name
   *   The function name, only alphanumeric characters and underscores.
   * @param string $name
   *   The human-readable name of the function.
   * @param string|null $description
   *   The (optional) description of the function.
   * @param string|null $group
   *   The (optional) group of the function.
   * @param array $module_dependencies
   *   The (optional) array of module dependencies.
   * @param array $context_definitions
   *   The (optional) An array of context definitions describing the contexts
   *   used by the plugin.
   * @param class-string|null $deriver
   *   (optional) The deriver class.
   */
  public function __construct(
    public readonly string $id,
    public readonly string $function_name,
    public readonly string $name,
    public readonly ?string $description,
    public readonly ?string $group = "other_fallback",
    public readonly array $module_dependencies = [],
    public readonly array $context_definitions = [],
    public readonly ?string $deriver = NULL,
  ) {}

}
