<?php

namespace Drupal\entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Duplicates bundle entities, their fields and displays.
 */
interface BundleEntityDuplicatorInterface {

  /**
   * Duplicates the bundle entity, its fields and displays.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $bundle_entity
   *   The bundle entity.
   * @param array $values
   *   An array of values to set, keyed by property name. Needs to at least
   *   contain a new ID.
   *
   * @return \Drupal\Core\Config\Entity\ConfigEntityInterface
   *   The new bundle entity, after it has been saved.
   *
   * @throws \InvalidArgumentException
   *   Thrown if the given entity is not a bundle entity, or if $values does
   *   not contain a new ID.
   */
  public function duplicate(ConfigEntityInterface $bundle_entity, array $values);

  /**
   * Duplicates the bundle entity's fields.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $bundle_entity
   *   The bundle entity.
   * @param string $target_bundle_id
   *   The target bundle ID.
   *
   * @throws \InvalidArgumentException
   *   Thrown if the given entity is not a bundle entity.
   */
  public function duplicateFields(ConfigEntityInterface $bundle_entity, $target_bundle_id);

  /**
   * Duplicates the bundle entity's view/form displays.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $bundle_entity
   *   The bundle entity.
   * @param string $target_bundle_id
   *   The target bundle ID.
   *
   * @throws \InvalidArgumentException
   *   Thrown if the given entity is not a bundle entity.
   */
  public function duplicateDisplays(ConfigEntityInterface $bundle_entity, $target_bundle_id);

}
