<?php

namespace Drupal\simple_oauth\Entities;

use Drupal\consumers\Entity\Consumer;
use League\OAuth2\Server\Entities\ClientEntityInterface as LeagueClientEntityInterface;

/**
 * The client entity interface.
 */
interface ClientEntityInterface extends LeagueClientEntityInterface {

  /**
   * Set the name of the client.
   *
   * @param string $name
   *   The name to set.
   */
  public function setName(string $name): void;

  /**
   * Returns the associated Drupal entity.
   *
   * @return \Drupal\consumers\Entity\Consumer
   *   The Drupal entity.
   */
  public function getDrupalEntity(): Consumer;

  /**
   * Set the redirect uri of the client.
   *
   * @param \Drupal\consumers\Entity\Consumer $entity
   *   The consumer entity.
   */
  public function setRedirectUri(Consumer $entity): void;

}
