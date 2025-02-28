<?php

namespace Drupal\conflict\ConflictResolver;

use Drupal\Core\Entity\EntityInterface;
use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * Provides an interface for conflict resolver managers.
 */
interface ConflictResolverManagerInterface {

  /**
   * Resolves the conflicts between two entities based on their common parent.
   *
   * @param \Drupal\Core\Entity\EntityInterface $local
   *   The local part of the comparision - for example the entity built of the
   *   user input on an entity form submission. This is basically the active
   *   entity object.
   * @param \Drupal\Core\Entity\EntityInterface $remote
   *   The remote part of the comparision - for example the current version of
   *   the entity from the storage or from a remote branch.
   * @param \Drupal\Core\Entity\EntityInterface $base
   *   The initial entity version in concurrent editing or the lowest common
   *   ancestor in a revision tree scenario.
   * @param \Drupal\Core\Entity\EntityInterface $result
   *   (optional) The result entity, on which to apply the result. Usually this
   *   will be the active entity object - the local entity. If none given, then
   *   the conflict resolutions will be applied on the local entity.
   * @param \Symfony\Component\HttpFoundation\ParameterBag $context
   *   (optional) The context parameter bag.
   * @param array $conflicts
   *   (optional) The conflicts as returned by ::getConflicts() or a sub-set of
   *   them in order to limit the conflict resolution only to certain conflicts.
   *   If none conflicts are provided then a conflict detection will be
   *   performed.
   *
   * @return array
   *   An associative array keyed by the conflicting properties, having as
   *   values the corresponding conflict type.
   */
  public function resolveConflicts(EntityInterface $local, EntityInterface $remote, EntityInterface $base, EntityInterface $result = NULL, ParameterBag $context = NULL, array $conflicts = NULL) : array;

  /**
   * Returns the conflicts between two entities based on their common parent.
   *    *
   * @param \Drupal\Core\Entity\EntityInterface $local
   *   The local part of the comparision - for example the entity built of the
   *   user input on an entity form submission. This is basically the active
   *   entity object.
   * @param \Drupal\Core\Entity\EntityInterface $remote
   *   The remote part of the comparision - for example the current version of
   *   the entity from the storage.
   * @param \Drupal\Core\Entity\EntityInterface $base
   *   The initial entity version in concurrent editing or the lowest common
   *   ancestor in a revision tree scenario.
   * @param \Symfony\Component\HttpFoundation\ParameterBag $context
   *   (optional) The context parameter bag.
   *
   * @return array
   *   An associative array keyed by the conflicting properties, having as
   *   values the corresponding conflict type.
   */
  public function getConflicts(EntityInterface $local, EntityInterface $remote, EntityInterface $base, ParameterBag $context = NULL) : array;

}
