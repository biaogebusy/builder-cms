<?php

namespace Drupal\simple_oauth\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Validation constraint for valid OAuth2 redirect URI.
 *
 * @Constraint(
 *   id = "Oauth2RedirectUri",
 *   label = @Translation("OAuth2 redirect URI", context = "Validation"),
 *   type = "string"
 * )
 */
class Oauth2RedirectUri extends Constraint {

  /**
   * The default violation message.
   *
   * @var string
   */
  public string $oauth2RedirectUriMessage = 'The URL %url is not valid.';

}
