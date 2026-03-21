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
  protected static $entityTypeId = 'oauth2_token';

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The serializer.
   *
   * @var \Symfony\Component\Serializer\SerializerInterface
   */
  protected $serializer;

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
   * {@inheritdoc}
   */
  public function persistNew($token_entity) {
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
   * {@inheritdoc}
   */
  public function revoke($token_id) {
    if (!$tokens = $this
      ->entityTypeManager
      ->getStorage(static::$entityTypeId)
      ->loadByProperties(['value' => $token_id])) {
      return;
    }
    /** @var \Drupal\simple_oauth\Entity\Oauth2TokenInterface $token */
    $token = reset($tokens);
    $token->revoke();
    $token->save();
  }

  /**
   * {@inheritdoc}
   */
  public function isRevoked($token_id) {
    if (!$tokens = $this
      ->entityTypeManager
      ->getStorage(static::$entityTypeId)
      ->loadByProperties(['value' => $token_id])) {
      return TRUE;
    }
    /** @var \Drupal\simple_oauth\Entity\Oauth2TokenInterface $token */
    $token = reset($tokens);

    return $token->isRevoked();
  }

  /**
   * {@inheritdoc}
   */
  public function getNew() {
    $class = static::$entityClass;
    return new $class();
  }

}
