<?php

declare(strict_types=1);

namespace Drupal\pathauto\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a AliasType attribute.
 *
 * Plugin Namespace: Plugin\pathauto\AliasType.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class AliasType extends Plugin {

  /**
   * Constructs a migrate source plugin attribute object.
   *
   * @param string $id
   *   A unique identifier for the alias type plugin.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $label
   *   The alias type label required unless a deriver is used.
   * @param array $types
   *   The token types.
   * @param class-string|null $deriver
   *   (optional) The deriver class.
   * @param string|null $provider
   *   The module providing this plugin.
   * @param \Drupal\Core\Plugin\Context\ContextDefinitionInterface[] $context_definitions
   *   (optional) An array of context definitions describing the context used by
   *    the plugin. The array is keyed by context names.
   */
  public function __construct(
    public readonly string $id,
    public ?TranslatableMarkup $label = NULL,
    public readonly array $types = [],
    public readonly ?string $deriver = NULL,
    public ?string $provider = NULL,
    public readonly array $context_definitions = [],
  ) {}

}
