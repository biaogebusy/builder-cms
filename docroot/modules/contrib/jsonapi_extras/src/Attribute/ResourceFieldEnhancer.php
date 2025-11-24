<?php

namespace Drupal\jsonapi_extras\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a Plugin annotation object for resource field enhancers.
 *
 * @see \Drupal\jsonapi_extras\Plugin\ResourceFieldEnhancerInterface
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class ResourceFieldEnhancer extends Plugin {

  /**
   * Constructs a ResourceFieldEnhancer attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The human-readable name of the formatter type.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $description
   *   A short description of the formatter type.
   * @param array $dependencies
   *   The name of modules that are required for this Field Enhancer to be usable.
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
    public readonly TranslatableMarkup $description,
    public readonly array $dependencies = [],
  ) {
  }

}
