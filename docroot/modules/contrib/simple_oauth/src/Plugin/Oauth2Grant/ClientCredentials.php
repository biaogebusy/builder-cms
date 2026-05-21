<?php

namespace Drupal\simple_oauth\Plugin\Oauth2Grant;

use Drupal\consumers\Entity\ConsumerInterface;
use Drupal\simple_oauth\Plugin\Oauth2GrantBase;
use League\OAuth2\Server\Grant\ClientCredentialsGrant;
use League\OAuth2\Server\Grant\GrantTypeInterface;

/**
 * The client credentials grant plugin.
 *
 * @Oauth2Grant(
 *   id = "client_credentials",
 *   label = @Translation("Client Credentials")
 * )
 */
class ClientCredentials extends Oauth2GrantBase {

  /**
   * {@inheritdoc}
   */
  public function getGrantType(ConsumerInterface $client): GrantTypeInterface {
    return new ClientCredentialsGrant();
  }

}
