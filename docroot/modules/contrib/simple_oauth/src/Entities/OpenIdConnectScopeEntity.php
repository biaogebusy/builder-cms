<?php

namespace Drupal\simple_oauth\Entities;

use Drupal\simple_oauth\Entities\ScopeEntity;
use Drupal\simple_oauth\Entities\ScopeEntityNameInterface;

/**
 * The OpenID Connect scope entity.
 */
class OpenIdConnectScopeEntity extends ScopeEntity implements ScopeEntityNameInterface {

  /**
   * OpenIdConnectScopeEntity constructor.
   *
   * @param string $identifier
   *   The scope identifier.
   * @param string $name
   *   The scope name.
   */
  public function __construct($identifier, $name) {
    $this->setIdentifier($identifier);
    $this->name = $name;
  }

}
