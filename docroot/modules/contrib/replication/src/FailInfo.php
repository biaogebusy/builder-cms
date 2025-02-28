<?php

namespace Drupal\replication;

use Drupal\Core\TypedData\TypedData;

/**
 * The 'fail_info' property for history fields.
 */
class FailInfo extends TypedData {

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    // Check if we have explicitly set a value.
    if (isset($this->value) && $this->value !== NULL) {
      return $this->value;
    }
    return '';
  }

}
