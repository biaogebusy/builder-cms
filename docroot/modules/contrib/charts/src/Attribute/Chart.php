<?php

namespace Drupal\charts\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a Chart attribute object.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class Chart extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public readonly string $id;

  /**
   * The human-readable name of the action plugin.
   *
   * @var \Drupal\Core\StringTranslation\TranslatableMarkup
   *
   * @ingroup plugin_translatable
   */
  public readonly TranslatableMarkup $name;

  /**
   * An array of chart types the chart library supports.
   *
   * @var array
   */
  public array $types = [];

  /**
   * The route name of the library's API example page, if it provides one.
   *
   * Set by a charts_*_api_example submodule so the example directory can link
   * to it without hard-coding which libraries exist.
   *
   * @var string|null
   */
  public readonly ?string $example_route;

  /**
   * Constructs an Action attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $name
   *   The label of the action.
   * @param array $types
   *   The chart types that the chart library can use.
   * @param string|null $example_route
   *   The route name of the library's API example page, if any.
   * @param string|null $deriver
   *   The deriver class.
   */
  public function __construct(
    string $id,
    TranslatableMarkup $name,
    array $types = [],
    ?string $example_route = NULL,
    ?string $deriver = NULL,
  ) {
    parent::__construct($id, $deriver);
    $this->name = $name;
    $this->types = $types;
    $this->example_route = $example_route;
  }

}
