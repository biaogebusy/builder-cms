<?php

namespace Drupal\entity_browser\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines an entity browser widget annotation object.
 *
 * @see hook_entity_browser_widget_info_alter()
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class EntityBrowserWidget extends Plugin {

  /**
   * Constructs a new SensorPlugin instance.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The human-readable name of the widget.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $description
   *   (optional) A brief description of the widget. This will be shown when
   *   adding or configuring this widget.
   * @param bool $auto_select
   *   Indicates that widget supports auto selection of entities.
   * @param string|null $provider
   *    The module providing the plugin.
   * @param class-string|null $deriver
   *    (optional) The deriver class.
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
    public readonly ?TranslatableMarkup $description = NULL,
    public readonly bool $auto_select = FALSE,
    public ?string $provider = NULL,
    public readonly ?string $deriver = NULL,
  ) {}

}
