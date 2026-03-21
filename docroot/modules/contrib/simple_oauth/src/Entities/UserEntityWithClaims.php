<?php

namespace Drupal\simple_oauth\Entities;

use Drupal\simple_oauth\Entities\UserEntity;
use OpenIDConnectServer\Entities\ClaimSetInterface;

/**
 * A user entity with claims.
 */
class UserEntityWithClaims extends UserEntity implements ClaimSetInterface {

  /**
   * The claims.
   *
   * @var array
   */
  protected $claims;

  /**
   * Returns the claims.
   *
   * @return array
   *   List of claims.
   */
  public function getClaims() {
    return $this->claims;
  }

  /**
   * Sets the claims.
   *
   * @param array $claims
   *   List of claims.
   */
  public function setClaims(array $claims) {
    $this->claims = $claims;
  }

}
