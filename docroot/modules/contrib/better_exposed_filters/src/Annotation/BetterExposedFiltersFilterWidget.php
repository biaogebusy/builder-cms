<?php

namespace Drupal\better_exposed_filters\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Better exposed filters widget item annotation object.
 *
 * @see \Drupal\better_exposed_filters\Plugin\BetterExposedFiltersFilterWidgetManager
 * @see plugin_api
 *
 * @Annotation
 */
class BetterExposedFiltersFilterWidget extends Plugin {


  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The label of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

}
