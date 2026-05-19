<?php

namespace Drupal\simple_oauth\Annotation;

use Drupal\Component\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;

/**
 * Defines a Scope Provider annotation object.
 *
 * @see \Drupal\simple_oauth\Plugin\ScopeProviderManager
 * @see plugin_api
 *
 * @Annotation
 */
class ScopeProvider extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public string $id;

  /**
   * The label of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public Translation $label;

  /**
   * The scope provider adapter class.
   *
   * @var string
   */
  public string $adapter_class;

}
