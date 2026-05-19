<?php

namespace Drupal\simple_oauth\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\consumers\Entity\Consumer;
use League\OAuth2\Server\Grant\GrantTypeInterface;

/**
 * Defines an interface for OAuth2 Grant plugins.
 */
interface Oauth2GrantInterface extends PluginInspectionInterface {

  /**
   * Gets the grant object.
   *
   * @param \Drupal\consumers\Entity\Consumer $client
   *   The consumer entity.
   *
   * @return \League\OAuth2\Server\Grant\GrantTypeInterface
   *   The grant type object.
   *
   * @throws \Exception
   */
  public function getGrantType(Consumer $client): GrantTypeInterface;

  /**
   * Get the grant type label.
   *
   * @return string
   *   Returns the grant type label.
   */
  public function label(): string;

}
