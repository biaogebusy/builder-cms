<?php

namespace Drupal\replication\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a ReplicationFilter annotation object.
 *
 * Plugin Namespace: Plugin\ReplicationFilter
 *
 * For a working example, see
 * \Drupal\replication\Plugin\ReplicationFilter\PublishedFilter
 *
 * @see \Drupal\replication\Plugin\ReplicationFilterInterface
 * @see \Drupal\replication\Plugin\ReplicationFilterManager
 * @see plugin_api
 *
 * @Annotation
 */
class ReplicationFilter extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable name of the ReplicationFilter plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * A short description of the ReplicationFilter plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description;

}
