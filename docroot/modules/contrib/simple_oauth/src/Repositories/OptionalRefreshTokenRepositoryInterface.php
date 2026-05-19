<?php

namespace Drupal\simple_oauth\Repositories;

use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;

/**
 * The optional refresh token repository interface.
 */
interface OptionalRefreshTokenRepositoryInterface extends RefreshTokenRepositoryInterface {

  /**
   * Disable the refresh token.
   */
  public function disableRefreshToken(): void;

}
