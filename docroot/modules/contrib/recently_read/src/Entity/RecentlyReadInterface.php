<?php

namespace Drupal\recently_read\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining Recently read entities.
 *
 * @ingroup recently_read
 */
interface RecentlyReadInterface extends ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface {

  /**
   * Gets the Recently read name.
   *
   * @return string
   *   Name of the Recently read.
   */
  public function getName();

  /**
   * Sets the Recently read name.
   *
   * @param string $name
   *   The Recently read name.
   *
   * @return \Drupal\recently_read\Entity\RecentlyReadInterface
   *   The called Recently read entity.
   */
  public function setName($name);

  /**
   * Gets the Recently read creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Recently read.
   */
  public function getCreatedTime();

  /**
   * Sets the Recently read creation timestamp.
   *
   * @param int $timestamp
   *   The Recently read creation timestamp.
   *
   * @return \Drupal\recently_read\Entity\RecentlyReadInterface
   *   The called Recently read entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Get entity id.
   */
  public function getEntityId();

  /**
   * Set entity id.
   */
  public function setEnitityId($entityId);

  /**
   * Get session id.
   */
  public function getSessionId();

  /**
   * Set session id.
   */
  public function setSessionId($sessionId);

}
