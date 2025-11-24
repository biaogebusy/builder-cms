<?php

declare(strict_types=1);

namespace Drupal\commerce\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a CommerceEntityTrait attribute.
 *
 * Additional attribute keys for entity traits can be defined in
 * hook_commerce_entity_trait_info_alter().
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class CommerceEntityTrait extends Plugin {

  /**
   * Constructs a CommerceEntityTrait attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The human-readable name of the entity trait.
   * @param array $entity_types
   *   (optional) The content entity types that can have this trait.
   *   The bundle entities of the content entity type will reference the trait,
   *   and receive any fields it defines.
   * @param class-string|null $deriver
   *   (optional) The deriver class.
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
    public readonly array $entity_types = [],
    public readonly ?string $deriver = NULL,
  ) {
  }

}
