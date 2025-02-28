<?php

namespace Drupal\advancedqueue\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines an advanced queue job type.
 *
 * Plugin Namespace: Plugin\AdvancedQueue\JobType.
 *
 * @Annotation
 */
class AdvancedQueueJobType extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable name of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * The maximum number of retries.
   *
   * @var int
   */
  public $max_retries = 0;

  /**
   * The retry delay.
   *
   * @var int
   */
  public $retry_delay = 10;

}
