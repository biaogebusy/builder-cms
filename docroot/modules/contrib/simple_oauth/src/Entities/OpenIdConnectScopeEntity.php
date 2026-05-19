<?php

namespace Drupal\simple_oauth\Entities;

use Drupal\Core\Cache\RefinableCacheableDependencyTrait;
use League\OAuth2\Server\Entities\Traits\EntityTrait;

/**
 * The OpenID Connect scope entity.
 */
class OpenIdConnectScopeEntity implements ScopeEntityInterface {

  use EntityTrait, RefinableCacheableDependencyTrait;

  /**
   * The scope description.
   *
   * @var string
   */
  protected string $description;

  /**
   * OpenIdConnectScopeEntity constructor.
   *
   * @param string $identifier
   *   The scope identifier.
   * @param string $description
   *   The scope description.
   */
  public function __construct(string $identifier, string $description) {
    $this->setIdentifier($identifier);
    $this->description = $description;
  }

  /**
   * {@inheritdoc}
   */
  #[\ReturnTypeWillChange]
  public function jsonSerialize() {
    return $this->getIdentifier();
  }

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    return $this->getIdentifier();
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(string $grant_type): string {
    return $this->description;
  }

}
