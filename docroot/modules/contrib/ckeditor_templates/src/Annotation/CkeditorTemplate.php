<?php

namespace Drupal\ckeditor_templates\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines ckeditor_template annotation object.
 *
 * @Annotation
 */
class CkeditorTemplate extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * An integer to determine the weight of this template.
   *
   * This property is optional and it does not need to be declared.
   *
   * @var int
   */
  public $weight = NULL;

  /**
   * The human-readable name of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * The description of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description;

}
