<?php

namespace Drupal\bootstrap_styles\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a styles group form plugin annotation object.
 *
 * @Annotation
 */
class StylesGroup extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The title of the styles group plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $title;

  /**
   * An integer to determine the weight of this style group.
   *
   * @var intoptional
   */
  public $weight = NULL;

}
