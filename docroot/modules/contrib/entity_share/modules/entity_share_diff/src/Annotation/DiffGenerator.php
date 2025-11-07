<?php

declare(strict_types = 1);

namespace Drupal\entity_share_diff\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a DiffGenerator annotation object.
 *
 * @see \Drupal\entity_share_diff\DiffGenerator\DiffGeneratorPluginManager
 * @see plugin_api
 *
 * @Annotation
 *
 * @SuppressWarnings(PHPMD.CamelCasePropertyName)
 */
class DiffGenerator extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable name of the generator type.
   *
   * @var \Drupal\Core\Annotation\Translation
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * Array of applicable field types.
   *
   * @var string[]
   */
  public $field_types;

}
