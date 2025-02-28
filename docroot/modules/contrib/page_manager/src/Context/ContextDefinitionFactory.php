<?php

namespace Drupal\page_manager\Context;

use Drupal\Core\Plugin\Context\ContextDefinition;

/**
 * Provides a simple factory for context definitions.
 */
class ContextDefinitionFactory {

  /**
   * Creates a context definition.
   *
   * @param string $data_type
   *   The context's data type, as known to the core Typed Data system.
   *
   * @return \Drupal\Core\Plugin\Context\ContextDefinition
   *   The context definition.
   */
  public static function create($data_type) {
    // @todo: Remove this class_exists() check once Drupal 8.6 is the earliest
    // supported version of core.
    $class = '\Drupal\Core\Plugin\Context\EntityContextDefinition';

    if (strpos((string) $data_type, 'entity:') === 0 && class_exists($class)) {
      return $class::create($data_type);
    }
    return ContextDefinition::create($data_type);
  }

}
