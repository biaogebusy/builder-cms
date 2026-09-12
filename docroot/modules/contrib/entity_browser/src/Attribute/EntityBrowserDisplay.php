<?php

namespace Drupal\entity_browser\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines an entity browser display attribute object.
 *
 * @see hook_entity_browser_display_info_alter()
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class EntityBrowserDisplay extends Plugin {

  /**
   * Constructs a new EntityBrowserDisplay instance.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The human-readable name of the display.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $description
   *   (optional) A brief description of the display. This will be shown when
   *   adding or configuring this display.
   * @param bool $uses_route
   *   Indicates that display uses route.
   * @param string|null $provider
   *   The module providing the plugin.
   * @param class-string|null $deriver
   *   (optional) The deriver class.
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
    public readonly ?TranslatableMarkup $description = NULL,
    public bool $uses_route = FALSE,
    public ?string $provider = NULL,
    public readonly ?string $deriver = NULL,
  ) {}
}
