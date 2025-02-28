<?php

namespace Drupal\jsonapi_extras\ResourceType;

use Drupal\jsonapi_extras\Entity\JsonapiResourceConfig;

/**
 * Null pattern class resources without overridden configuration.
 */
class NullJsonapiResourceConfig extends JsonapiResourceConfig {

  /**
   * {@inheritdoc}
   */
  public function get($key) {
    return $key == 'resourceFields' ? [] : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigDependencyName() {
    return __CLASS__;
  }

}
