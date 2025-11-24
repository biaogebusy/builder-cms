<?php

namespace Drupal\charts\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Chart annotation object.
 *
 * @Annotation
 */
class Chart extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The plugin name.
   *
   * @var string
   */
  public $name;

  /**
   * An array of chart types the chart library supports.
   *
   * @var array
   */
  public $types = [];

}
