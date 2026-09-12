<?php

namespace Drupal\entity_browser\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines an entity browser widget selector attribute object.
 *
 * @see hook_entity_browser_widget_selector_info_alter()
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class EntityBrowserWidgetSelector extends Plugin {

  /**
   * Constructs a new SensorPlugin instance.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The human-readable name of the widget selector.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $description
   *   (optional) A brief description of the widget selector. This will be shown
   *   when adding or configuring this widget selector.
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
