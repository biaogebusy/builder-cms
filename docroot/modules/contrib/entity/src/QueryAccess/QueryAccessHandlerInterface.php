<?php

namespace Drupal\entity\QueryAccess;

use Drupal\Core\Session\AccountInterface;

/**
 * Query access handlers control access to entities in queries.
 *
 * An entity defines a query access handler in its annotation:
 * @code
 *   query_access = "\Drupal\entity\QueryAccess\QueryAccessHandler"
 * @code
 * The handler builds a set of conditions which are then applied to a query
 * to filter it. For example, if the user #22 only has access to view
 * their own entities, a uid = '22' condition will be built and applied.
 *
 * The following query types are supported:
 * - Entity queries with the $entity_type_id . '_access' tag.
 * - Views queries.
 */
interface QueryAccessHandlerInterface {

  /**
   * Gets the conditions for the given operation and user.
   *
   * The "entity.query_access.$entity_type_id" event is fired to allow
   * modules to alter the conditions.
   *
   * @param string $operation
   *   The access operation. Usually one of "view", "update", "duplicate",
   *   or "delete".
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user for which to restrict access, or NULL
   *   to assume the current user. Defaults to NULL.
   *
   * @return \Drupal\entity\QueryAccess\ConditionGroup
   *   The conditions.
   */
  public function getConditions($operation, AccountInterface $account = NULL);

}
