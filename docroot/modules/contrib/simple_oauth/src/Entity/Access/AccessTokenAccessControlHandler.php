<?php

namespace Drupal\simple_oauth\Entity\Access;

use Drupal\consumers\AccessControlHandler;

/**
 * Access controller for the Access Token entity.
 *
 * @see \Drupal\simple_oauth\Entity\AccessToken.
 */
class AccessTokenAccessControlHandler extends AccessControlHandler {

  /**
   * {@inheritdoc}
   */
  public static $name = 'simple_oauth';

}
