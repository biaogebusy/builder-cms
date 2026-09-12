<?php

namespace Drupal\entity_browser\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines an entity browser widget validation attribute object.
 *
 * @see hook_entity_browser_widget_validation_info_alter()
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class EntityBrowserWidgetValidation extends Plugin {

  /**
   * Constructs a new SensorPlugin instance.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The human-readable name of the widget validator.
   * @param string|null $data_type
   *   (optional) The data type plugin ID, for which a constraint should be
   *   added.
   * @param string|null $constraint
   *   (optional) The constraint ID.
   * @param string|null $provider
   *    The module providing the plugin.
   * @param class-string|null $deriver
   *    (optional) The deriver class.
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
    public readonly ?string $data_type = NULL,
    public readonly ?string $constraint = NULL,
    public ?string $provider = NULL,
    public readonly ?string $deriver = NULL,
  ) {}

}
