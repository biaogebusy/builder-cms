<?php

namespace Drupal\simple_oauth\Entities;

use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\Traits\RefreshTokenTrait;

/**
 * The entity for the Refresh token.
 */
class RefreshTokenEntity implements RefreshTokenEntityInterface {

  use RefreshTokenTrait, EntityTrait;

}
