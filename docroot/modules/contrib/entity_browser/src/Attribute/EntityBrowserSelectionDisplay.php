<?php

namespace Drupal\entity_browser\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines an entity browser selection display attribute object.
 *
 * @see hook_entity_browser_selection_display_info_alter()
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class EntityBrowserSelectionDisplay extends Plugin {

  /**
   * Constructs a new SensorPlugin instance.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The human-readable name of the selection display.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $description
   *   A brief description of the selection display. This will be shown when
   *   adding or configuring this selection display.
   * @param bool $acceptPreselection
   *   Preselection support. This will be used by entity browser form element to
   *   check, if selection display accepts preselection of entities.
   * @param bool $js_commands
   *   Indicates that javascript commands can be executed for Selection display.
   *   Currently supported javascript commands are adding and removing selection
   *   from selection display. Javascript commands use Ajax requests to load
   *   relevant changes and makes user experience way better, because form is
   *   not flashed every time.
   * @param string|null $provider
   *    The module providing the plugin.
   * @param class-string|null $deriver
   *    (optional) The deriver class.
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
    public readonly ?TranslatableMarkup $description = NULL,
    public readonly bool $acceptPreselection = FALSE,
    public readonly bool $js_commands = FALSE,
    public ?string $provider = NULL,
    public readonly ?string $deriver = NULL,
  ) {}

}
