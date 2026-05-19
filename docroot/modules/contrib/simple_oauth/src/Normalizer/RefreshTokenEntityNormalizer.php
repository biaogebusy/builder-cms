<?php

namespace Drupal\simple_oauth\Normalizer;

use Drupal\serialization\Normalizer\NormalizerBase;
use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;

/**
 * Normalizes refresh token entity.
 */
class RefreshTokenEntityNormalizer extends NormalizerBase implements TokenEntityNormalizerInterface {

  /**
   * The interface or class that this Normalizer supports.
   *
   * @var string|array
   */
  protected $supportedInterfaceOrClass = '\League\OAuth2\Server\Entities\RefreshTokenEntityInterface';

  /**
   * {@inheritdoc}
   */
  public function normalize(mixed $token_entity, ?string $format = NULL, array $context = []): array|string|int|float|bool|\ArrayObject|null {
    /** @var \League\OAuth2\Server\Entities\TokenInterface $token_entity */
    return [
      'value' => $token_entity->getIdentifier(),
      'expire' => $token_entity->getExpiryDateTime()->format('U'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedTypes(?string $format): array {
    return [
      RefreshTokenEntityInterface::class => TRUE,
    ];
  }

}
