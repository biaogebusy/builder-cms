<?php

namespace Drupal\state_machine\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines an access checker for the state transition confirmation form.
 */
class StateTransitionAccessCheck implements AccessInterface {

  /**
   * Checks access to the state transition confirmation form.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(RouteMatchInterface $route_match, AccountInterface $account) {
    // Get the entity type from the route name.
    // The entity route name is 'entity.{entity_type}.state_transition_form'.
    $parts = explode('.', $route_match->getRouteName());
    $entity_type = $parts[1];
    $parameters = $route_match->getParameters();

    // Check if one of the required parameter is missing.
    foreach ([$entity_type, 'field_name', 'transition_id'] as $required_parameter) {
      if (!$parameters->has($required_parameter)) {
        return AccessResult::neutral();
      }
    }

    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $route_match->getParameter($entity_type);
    $field_name = $route_match->getParameter('field_name');
    // Ensures the passed entity has a state field matching the field name
    // passed in the url.
    if (!$entity || !$entity->hasField($field_name)) {
      return AccessResult::forbidden();
    }

    /** @var \Drupal\state_machine\Plugin\Field\FieldType\StateItemInterface $state_item */
    $state_item = $entity->get($field_name)->first();
    $allowed_transitions = array_keys($state_item->getTransitions());
    // Now check if the requested transition is allowed.
    $requested_transition = $route_match->getParameter('transition_id');

    if (!in_array($requested_transition, $allowed_transitions, TRUE)) {
      return AccessResult::forbidden()->addCacheableDependency($entity);
    }

    // Now finally check that the current user can update the given entity.
    return $entity->access('update', $account, TRUE);
  }

}
