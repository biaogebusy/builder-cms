<?php

namespace Drupal\eck;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining an ECK entity.
 *
 * @ingroup eck
 */
interface EckEntityInterface extends ContentEntityInterface, EntityOwnerInterface {

  /**
   * Gets the timestamp of the last entity change for the current translation.
   *
   * @return int|null
   *   The timestamp of the last entity save or NULL if the "changed" field
   *   does not exist.
   */
  public function getChangedTime();

  /**
   * Sets the timestamp of the last entity change for the current translation.
   *
   * @param int $timestamp
   *   The timestamp of the last entity save operation.
   *
   * @return \Drupal\eck\EckEntityInterface $this
   *   The class instance that this method is called on.
   */
  public function setChangedTime($timestamp);

  /**
   * Returns the time that the entity was created.
   *
   * @return int|null
   *   The timestamp of when the entity was created or NULL if the "created"
   *   field does not exist.
   */
  public function getCreatedTime();

  /**
   * Sets the creation date of the entity.
   *
   * @param int $created
   *   The timestamp of when the entity was created.
   *
   * @return \Drupal\eck\EckEntityInterface
   *   The class instance that this method is called on.
   */
  public function setCreatedTime($created);

}
