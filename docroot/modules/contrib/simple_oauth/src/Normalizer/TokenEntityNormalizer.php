<?php

namespace Drupal\simple_oauth\Normalizer;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\serialization\Normalizer\NormalizerBase;
use Drupal\simple_oauth\Entities\ScopeEntity;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Entities\TokenInterface;

/**
 * Normalizes token entity.
 */
class TokenEntityNormalizer extends NormalizerBase implements TokenEntityNormalizerInterface {

  /**
   * The interface or class that this Normalizer supports.
   *
   * @var string|array
   */
  protected $supportedInterfaceOrClass = '\League\OAuth2\Server\Entities\TokenInterface';

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function normalize(mixed $token_entity, ?string $format = NULL, array $context = []): array|string|int|float|bool|\ArrayObject|null {
    /** @var \League\OAuth2\Server\Entities\TokenInterface $token_entity */
    $scopes = array_map(function (ScopeEntityInterface $scope_entity) {
      $scope_id = $scope_entity instanceof ScopeEntity ? $scope_entity->getScopeObject()->id() : $scope_entity->getIdentifier();
      return ['scope_id' => $scope_id];
    }, $token_entity->getScopes());

    /** @var \Drupal\simple_oauth\Entities\ClientEntityInterface $client */
    $client = $token_entity->getClient();
    $client_drupal_entity = $client->getDrupalEntity();
    $auth_user_id = $token_entity->getUserIdentifier() ? ['target_id' => $token_entity->getUserIdentifier()] : NULL;

    return [
      'auth_user_id' => $auth_user_id,
      'client' => ['target_id' => $client_drupal_entity->id()],
      'scopes' => $scopes,
      'value' => $token_entity->getIdentifier(),
      'expire' => $token_entity->getExpiryDateTime()->format('U'),
      'status' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedTypes(?string $format): array {
    return [
      TokenInterface::class => TRUE,
    ];
  }

}
