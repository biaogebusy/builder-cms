<?php

namespace Drupal\simple_oauth\Repositories;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Common methods for token repositories on different grants.
 */
trait RevocableTokenRepositoryTrait {

  /**
   * The entity type ID.
   *
   * @var string
   */
  protected static string $entityTypeId = 'oauth2_token';

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
   * Construct a revocable token.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Symfony\Component\Serializer\SerializerInterface $serializer
   *   The normalizer for tokens.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, SerializerInterface $serializer) {
    $this->entityTypeManager = $entity_type_manager;
    $this->serializer = $serializer;
  }

  /**
   * Persists a new access token to permanent storage.
   *
   * @param mixed $token_entity
   *   The token entity.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
   */
  public function persistNew($token_entity): void {
    if (!is_a($token_entity, static::$entityInterface)) {
      throw new \InvalidArgumentException(sprintf('%s does not implement %s.', get_class($token_entity), static::$entityInterface));
    }
    $values = $this->serializer->normalize($token_entity);
    $values['bundle'] = static::$bundleId;
    $new_token = $this->entityTypeManager->getStorage(static::$entityTypeId)->create($values);

    if ($token_entity instanceof RefreshTokenEntityInterface) {
      $access_token = $token_entity->getAccessToken();
      if (!empty($access_token->getUserIdentifier())) {
        $new_token->set('auth_user_id', $access_token->getUserIdentifier());
      }
    }

    $new_token->save();
  }

  /**
   * Revoke an access token.
   *
   * @param string $token_id
   *   The token id.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function revoke(string $token_id): void {
    $tokens = $this
      ->entityTypeManager
      ->getStorage(static::$entityTypeId)
      ->loadByProperties(['value' => $token_id]);
    if ($tokens) {
      /** @var \Drupal\simple_oauth\Entity\Oauth2TokenInterface $token */
      $token = reset($tokens);
      $token->revoke();
      $token->save();
    }
  }

  /**
   * Check if the token has been revoked.
   *
   * @param string $token_id
   *   The token id.
   *
   * @return bool
   *   Return true if this token has been revoked.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function isRevoked(string $token_id): bool {
    $tokens = $this
      ->entityTypeManager
      ->getStorage(static::$entityTypeId)
      ->loadByProperties(['value' => $token_id]);
    /** @var \Drupal\simple_oauth\Entity\Oauth2TokenInterface|null $token */
    $token = $tokens ? reset($tokens) : NULL;

    return !$token || $token->isRevoked();
  }

  /**
   * Create a new token.
   *
   * @return mixed
   *   Returns a new token entity.
   */
  public function getNew() {
    $class = static::$entityClass;
    return new $class();
  }

}
