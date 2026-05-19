<?php

namespace Drupal\simple_oauth\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\simple_oauth\Oauth2ScopeInterface;

/**
 * Defines the interface for OAuth2 Scope entity.
 *
 * @ingroup simple_oauth
 */
interface Oauth2ScopeEntityInterface extends Oauth2ScopeInterface, ConfigEntityInterface {}
