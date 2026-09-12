<?php

namespace Drupal\entity_browser\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines an entity browser field widget display attribute object.
 *
 * @see hook_entity_browser_field_widget_display_info_alter()
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class EntityBrowserFieldWidgetDisplay extends Plugin {

  /**
   * Constructs a new SensorPlugin instance.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The human-readable name of the field widget display.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $description
   *   (optional) A brief description of the field widget display. This will be
   *   shown when adding or configuring this display.
   * @param string|null $provider
   *    The module providing the plugin.
   * @param class-string|null $deriver
   *    (optional) The deriver class.
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
    public readonly ?TranslatableMarkup $description = NULL,
    public ?string $provider = NULL,
    public readonly ?string $deriver = NULL,
  ) {}

}
