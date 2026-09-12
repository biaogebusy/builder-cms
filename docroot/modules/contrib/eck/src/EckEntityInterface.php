<?php

namespace Drupal\eck;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining an ECK entity.
 *
 * @ingroup eck
 */
interface EckEntityInterface extends ContentEntityInterface, EntityOwnerInterface, EntityPublishedInterface, EntityChangedInterface {

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
   * @return static
   *   The class instance that this method is called on.
   */
  public function setCreatedTime($created);

}
