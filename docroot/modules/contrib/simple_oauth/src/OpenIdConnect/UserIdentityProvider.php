<?php

namespace Drupal\simple_oauth\OpenIdConnect;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\simple_oauth\Entities\UserEntityWithClaims;
use Drupal\user\UserInterface;
use OpenIDConnectServer\Repositories\IdentityProviderInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * A user identity provider for the OpenID Connect integration.
 */
class UserIdentityProvider implements IdentityProviderInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The serializer.
   *
   * @var \Symfony\Component\Serializer\SerializerInterface
   */
  protected SerializerInterface $serializer;

  /**
   * UserIdentityProvider constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Symfony\Component\Serializer\SerializerInterface $serializer
   *   The serializer.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, SerializerInterface $serializer) {
    $this->entityTypeManager = $entity_type_manager;
    $this->serializer = $serializer;
  }

  /**
   * {@inheritdoc}
   */
  public function getUserEntityByIdentifier($identifier) {
    $user = $this->entityTypeManager->getStorage('user')->load($identifier);
    assert($user instanceof UserInterface);

    $user_entity = new UserEntityWithClaims();
    $user_entity->setIdentifier($identifier);

    $claims = $this->serializer->normalize($user_entity, 'json', [$identifier => $user]);

    $user_entity->setClaims($claims);
    return $user_entity;
  }

}
