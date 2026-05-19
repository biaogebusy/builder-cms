<?php

namespace Drupal\simple_oauth\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * OAuth2 scope reference constraint.
 *
 * Verifies that referenced OAuth2 scopes are valid.
 *
 * @Constraint(
 *   id = "Oauth2ScopeReference",
 *   label = @Translation("OAuth2 scope reference", context = "Validation")
 * )
 */
class Oauth2ScopeReference extends Constraint {

  /**
   * Violation message when the OAuth2 scope does not exist.
   *
   * @var string
   */
  public string $nonExistingMessage = "The referenced OAuth2 scope '%id' does not exist.";

}
