<?php

namespace Drupal\state_machine_test\Guard;

use Drupal\Core\Entity\EntityInterface;
use Drupal\state_machine\Guard\GuardInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowTransition;

class FulfillmentGuard implements GuardInterface {

  /**
   * {@inheritdoc}
   */
  public function allowed(WorkflowTransition $transition, WorkflowInterface $workflow, EntityInterface $entity) {
    // Don't allow entities in fulfillment to be cancelled.
    if ($transition->getId() == 'cancel' && $entity->field_state->first()->value == 'fulfillment') {
      return FALSE;
    }
  }

}
