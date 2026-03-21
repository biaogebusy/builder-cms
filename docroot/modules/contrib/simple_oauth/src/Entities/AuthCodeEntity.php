<?php

namespace Drupal\simple_oauth\Entities;

use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use League\OAuth2\Server\Entities\Traits\AuthCodeTrait;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\Traits\TokenEntityTrait;

/**
 * The entity for the Auth Code grant.
 */
class AuthCodeEntity implements AuthCodeEntityInterface {

  use EntityTrait, TokenEntityTrait, AuthCodeTrait;

}
