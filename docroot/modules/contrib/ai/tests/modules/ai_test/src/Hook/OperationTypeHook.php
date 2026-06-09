<?php

namespace Drupal\ai_test\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hooks to interact with operation types.
 */
class OperationTypeHook {

  /**
   * Implements hook_ai_operationtype_alter().
   */
  #[Hook('ai_operationtype_alter')]
  public function echoOperationType(array &$operation_types) {
    $operation_types['echo']['label'] = 'Echo altered';
  }

}
