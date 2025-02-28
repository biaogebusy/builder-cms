<?php

namespace Drupal\conflict\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a FieldComparator annotation object.
 *
 * Plugin Namespace: Plugin\Conflict\FieldComparator
 *
 * @Annotation
 */
class FieldComparator extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The entity type ID this plugin applies to.
   *
   * @var string
   */
  public $entity_type_id;

  /**
   * The bundle of the entity type this plugin applies to.
   *
   * @var string
   */
  public $bundle;

  /**
   * The field type this plugin applies to.
   *
   * @var string
   */
  public $field_type;

  /**
   * The field name of the entity type this plugin applies to.
   *
   * @var string
   */
  public $field_name;

  /**
   * The weight of the plugin.
   *
   * @var int
   */
  public $weight;

}
