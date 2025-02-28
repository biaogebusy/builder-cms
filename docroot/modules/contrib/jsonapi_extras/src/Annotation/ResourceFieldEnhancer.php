<?php

namespace Drupal\jsonapi_extras\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Plugin annotation object for resource field enhancers.
 *
 * @see \Drupal\jsonapi_extras\Plugin\ResourceFieldEnhancerInterface
 *
 * @Annotation
 */
class ResourceFieldEnhancer extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable name of the formatter type.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * A short description of the formatter type.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description;

  /**
   * The name of the field formatter class.
   *
   * This is not provided manually, it will be added by the discovery mechanism.
   *
   * @var string
   */
  public $class;

  /**
   * The name of modules that are required for this Field Enhancer to be usable.
   *
   * @var array
   */
  public $dependencies;

}
