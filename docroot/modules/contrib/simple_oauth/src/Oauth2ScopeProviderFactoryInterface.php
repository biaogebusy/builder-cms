<?php

namespace Drupal\simple_oauth;

/**
 * OAuth2 scope provider factory interface.
 */
interface Oauth2ScopeProviderFactoryInterface {

  /**
   * Get the OAuth2 scope provider based on config.
   *
   * @return \Drupal\simple_oauth\Oauth2ScopeAdapterInterface
   *   Returns the OAuth2 scope provider.
   */
  public function get(): Oauth2ScopeAdapterInterface;

}
