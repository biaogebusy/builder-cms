<?php

namespace Drupal\simple_oauth\Entities;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\RefinableCacheableDependencyTrait;
use Drupal\simple_oauth\Oauth2ScopeInterface;
use League\OAuth2\Server\Entities\Traits\EntityTrait;

/**
 * The scope entity class.
 */
class ScopeEntity implements ScopeEntityInterface, CacheableDependencyInterface {

  use EntityTrait, RefinableCacheableDependencyTrait;

  /**
   * The scope object.
   *
   * @var \Drupal\simple_oauth\Oauth2ScopeInterface
   */
  protected Oauth2ScopeInterface $scope;

  /**
   * Construct a ScopeEntity instance.
   *
   * @param \Drupal\simple_oauth\Oauth2ScopeInterface $scope
   *   The associated scope.
   */
  public function __construct(Oauth2ScopeInterface $scope) {
    $this->scope = $scope;
    $this->setIdentifier($scope->getName());
    $this->addCacheableDependency($scope);
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
    return $this->identifier;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(string $grant_type): string {
    $grant_type_description = $this->scope->getGrantTypeDescription($grant_type);
    return $grant_type_description ?: $this->scope->getDescription();
  }

  /**
   * Returns the scope object.
   *
   * @return \Drupal\simple_oauth\Oauth2ScopeInterface
   *   The scope object.
   */
  public function getScopeObject(): Oauth2ScopeInterface {
    return $this->scope;
  }

}
