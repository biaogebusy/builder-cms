<?php

namespace Drupal\queue_ui\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;

/**
 * Defines a QueueUI attribute object.
 *
 * Plugin Namespace: Plugin\QueueUI.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class QueueUI extends Plugin {

  /**
   * Constructs a QueueUI object.
   *
   * @param string $id
   *   The plugin ID.
   * @param string $class_name
   *   The name of the queue backend implementation class.
   *   This can be either the short name (e.g., "DatabaseQueue") or the full
   *   class name (e.g., "\Drupal\Core\Queue\DatabaseQueue").
   * @param class-string|null $deriver
   *   (optional) The deriver class.
   */
  public function __construct(
    string $id,
    public readonly string $class_name,
    ?string $deriver = NULL,
  ) {
    parent::__construct($id, $deriver);
  }

}
