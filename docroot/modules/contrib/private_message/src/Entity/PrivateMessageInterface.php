<?php

declare(strict_types=1);

namespace Drupal\private_message\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a Private Message entity.
 */
interface PrivateMessageInterface extends ContentEntityInterface, EntityOwnerInterface {

  /**
   * Gets the time at which the private message was created.
   *
   * @return int
   *   A Unix timestamp indicating the time at which the private message was
   *   created.
   */
  public function getCreatedTime(): int;

}
