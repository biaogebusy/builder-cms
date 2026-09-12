<?php

namespace Drupal\queue_ui\Annotation;

use Drupal\Component\Annotation\Plugin;

// phpcs:ignore Drupal.Semantics.FunctionTriggerError
@trigger_error('\Drupal\queue_ui\Annotation\QueueUI is deprecated in queue_ui:3.2.3 and will be removed in queue_ui:4.0.0. Use \Drupal\queue_ui\Attribute\QueueUI instead. See https://www.drupal.org/node/3395575', E_USER_DEPRECATED);

/**
 * Defines a QueueUI annotation object.
 *
 * Plugin Namespace: Plugin\QueueUI.
 *
 * @Annotation
 *
 * @deprecated in queue_ui:3.2.3 and is removed from queue_ui:4.0.0. Use
 *   \Drupal\queue_ui\Attribute\QueueUI instead.
 *
 * @see https://www.drupal.org/node/3395575
 */
class QueueUI extends Plugin {

  /**
   * The plugin ID.
   */
  public string $id;

  /**
   * The name of the queue backend implementation class.
   *
   * This can be either the short name (e.g., "DatabaseQueue") or the full
   * class name (e.g., "\Drupal\Core\Queue\DatabaseQueue").
   */
  public string $class_name;

}
