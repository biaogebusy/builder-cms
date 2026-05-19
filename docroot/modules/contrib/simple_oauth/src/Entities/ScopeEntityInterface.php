<?php

namespace Drupal\simple_oauth\Entities;

use League\OAuth2\Server\Entities\ScopeEntityInterface as LeagueScopeEntityInterface;

/**
 * The scope entity interface.
 */
interface ScopeEntityInterface extends LeagueScopeEntityInterface {

  /**
   * Returns a name for the scope.
   *
   * @return string
   *   The name of the scope.
   */
  public function getName(): string;

  /**
   * Returns a description for the scope.
   *
   * @param string $grant_type
   *   The grant type to retrieve the description from.
   *
   * @return string
   *   The description of the scope.
   */
  public function getDescription(string $grant_type): string;

}
