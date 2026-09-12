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
   * Defines the max length of entity id machine name.
   */
  public const ECK_ENTITY_ID_MAX_LENGTH = 27;

  /**
   * Gets the description of the entity type.
   *
   * @return string|null
   *   The description of the entity type, or NULL if not set.
   */
  public function getDescription();

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

  /**
   * Determines if the entity type has a 'status' field.
   *
   * @return bool
   *   True if it has one.
   */
  public function hasStatusField();

  /**
   * Determines if the entity type can be viewed at /{entity_type}/{id}.
   *
   * @return bool
   *   True if it has one.
   */
  public function hasStandaloneUrl();

}
