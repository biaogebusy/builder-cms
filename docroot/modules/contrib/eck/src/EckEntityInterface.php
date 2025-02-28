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

}
