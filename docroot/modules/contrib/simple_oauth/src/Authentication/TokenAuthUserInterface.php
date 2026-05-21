<?php

namespace Drupal\simple_oauth\Authentication;

use Drupal\consumers\Entity\Consumer;
use Drupal\simple_oauth\Entity\Oauth2TokenInterface;
use Drupal\user\UserInterface;

/**
 * The token auth user interface.
 *
 * @internal
 */
interface TokenAuthUserInterface extends \IteratorAggregate, UserInterface {

  /**
   * Get the token.
   *
   * @return \Drupal\simple_oauth\Entity\Oauth2TokenInterface
   *   The provided OAuth2 token.
   */
  public function getToken(): Oauth2TokenInterface;

  /**
   * Get the activated consumer.
   *
   * @return \Drupal\consumers\Entity\Consumer
   *   The activated consumer after authentication.
   */
  public function getConsumer(): Consumer;

  /**
   * Get the decorated subject.
   *
   * @return \Drupal\user\UserInterface
   *   The original user object.
   */
  public function getSubject(): UserInterface;

}
