<?php

namespace Drupal\views_add_button\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a ViewsAddButton annotation object.
 *
 * Plugin Namespace: Plugin\views_add_button .
 *
 * @see plugin_api
 *
 * @Annotation
 */
class ViewsAddButton extends Plugin {
  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable name of the ViewsAddButton.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * The category under which the ViewsAddButton should be listed in the UI.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $category;

}
