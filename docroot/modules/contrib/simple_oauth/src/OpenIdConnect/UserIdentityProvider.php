<?php

namespace Drupal\simple_oauth\OpenIdConnect;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\simple_oauth\Entities\UserEntityWithClaims;
use Drupal\user\UserInterface;
use OpenIDConnectServer\Repositories\IdentityProviderInterface;

/**
 * A user identity provider for the OpenID Connect integration.
 */
class UserIdentityProvider implements IdentityProviderInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * UserIdentityProvider constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getUserEntityByIdentifier($identifier) {
    $user = $this->entityTypeManager->getStorage('user')->load($identifier);
    assert($user instanceof UserInterface);

    $user_entity = new UserEntityWithClaims();
    $user_entity->setIdentifier($identifier);

    $claims = \Drupal::service('serializer')
      ->normalize($user_entity, 'json', [$identifier => $user]);

    $user_entity->setClaims($claims);
    return $user_entity;
  }

}
