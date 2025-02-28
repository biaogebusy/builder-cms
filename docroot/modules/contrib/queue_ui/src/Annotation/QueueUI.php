<?php

namespace Drupal\queue_ui\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a QueueUI annotation object.
 *
 * Plugin Namespace: Plugin\QueueUI.
 *
 * @Annotation
 */
class QueueUI extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The class name.
   *
   * @var string
   */
  public $class_name;

}
