<?php

namespace Drupal\eck;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface defining an ECK entity type.
 *
 * @ingroup eck
 */
interface EckEntityTypeInterface extends ConfigEntityInterface {

  /**
   * Determines if the entity type has an 'author' field.
   *
   * @return bool
   *   True if it has one.
   */
  public function hasAuthorField();

  /**
   * Determines if the entity type has a 'changed' field.
   *
   * @return bool
   *   True if it has one.
   */
  public function hasChangedField();

  /**
   * Determines if the entity type has a 'created' field.
   *
   * @return bool
   *   True if it has one.
   */
  public function hasCreatedField();

  /**
   * Determines if the entity type has a 'title' field.
   *
   * @return bool
   *   True if it has one.
   */
  public function hasTitleField();

}
