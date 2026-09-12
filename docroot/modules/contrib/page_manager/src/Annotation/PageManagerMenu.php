<?php

namespace Drupal\page_manager\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a PageManagerMenu annotation object.
 *
 * @Annotation
 */
class PageManagerMenu extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The label of the menu type.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

}
