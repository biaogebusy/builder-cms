<?php

namespace Drupal\simple_oauth\OpenIdConnect;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Url;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\RegisteredClaims;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\UserEntityInterface;
use OpenIDConnectServer\ClaimExtractor;
use OpenIDConnectServer\Entities\ClaimSetInterface;
use OpenIDConnectServer\IdTokenResponse;
use OpenIDConnectServer\Repositories\IdentityProviderInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

/**
 * OpenId Connect id token response.
 */
class OpenIdConnectIdTokenResponse extends IdTokenResponse implements LoggerAwareInterface {

  use LoggerAwareTrait;

  public function __construct(IdentityProviderInterface $identityProvider, ClaimExtractor $claimExtractor, protected LanguageManagerInterface $languageManager, ?string $keyIdentifier = NULL) {
    parent::__construct($identityProvider, $claimExtractor, $keyIdentifier);
  }

  /**
   * {@inheritdoc}
   */
  protected function getBuilder(AccessTokenEntityInterface $accessToken, UserEntityInterface $userEntity) {
    $builder = parent::getBuilder($accessToken, $userEntity);

    $language_none = $this->languageManager->getLanguage(LanguageInterface::LANGCODE_NOT_APPLICABLE);
    return $builder->issuedBy(Url::fromUri('internal:/', ['language' => $language_none, 'https' => TRUE])->setAbsolute()->toString());
  }

  /**
   * {@inheritdoc}
   */
  protected function getExtraParams(AccessTokenEntityInterface $accessToken): array {
    if (FALSE === $this->isOpenIDRequest($accessToken->getScopes())) {
      return [];
    }

    /** @var \League\OAuth2\Server\Entities\UserEntityInterface $userEntity */
    $userEntity = $this->identityProvider->getUserEntityByIdentifier($accessToken->getUserIdentifier());
    if (FALSE === is_a($userEntity, UserEntityInterface::class)) {
      throw new \RuntimeException('UserEntity must implement UserEntityInterface');
    }
    elseif (FALSE === is_a($userEntity, ClaimSetInterface::class)) {
      throw new \RuntimeException('UserEntity must implement ClaimSetInterface');
    }

    // Add required id_token claims.
    $builder = $this->getBuilder($accessToken, $userEntity);
    $claims = $this->claimExtractor
      ->extract($accessToken->getScopes(), $userEntity->getClaims());

    if (isset($claims['iss'])) {
      $builder = $builder->issuedBy($claims['iss']);
    }
    if (isset($claims['sub'])) {
      $builder = $builder->relatedTo($claims['sub']);
    }
    foreach ($claims as $claim_name => $value) {
      if (in_array($claim_name, RegisteredClaims::ALL)) {
        // Skip registered claims, as they are added above already.
        continue;
      }

      try {
        $builder = $builder->withClaim($claim_name, $value);
      }
      catch (\Exception $e) {
        $this->logger->error('Could not add private claim @claim_name to token: @error_message', [
          '@claim_name' => $claim_name,
          '@error_message' => $e->getMessage(),
        ]);
      }
    }

    if (method_exists($this->privateKey, 'getKeyContents')
      && !empty($this->privateKey->getKeyContents())) {
      $key = InMemory::plainText(
        $this->privateKey->getKeyContents(),
        (string) $this->privateKey->getPassPhrase()
      );
    }
    else {
      $key = InMemory::file(
        $this->privateKey->getKeyPath(),
        (string) $this->privateKey->getPassPhrase()
      );
    }

    $token = $builder->getToken(new Sha256(), $key);

    return [
      'id_token' => $token->toString(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  // phpcs:ignore
  private function isOpenIDRequest($scopes) {
    // Verify scope and make sure openid exists.
    $valid = FALSE;

    foreach ($scopes as $scope) {
      if ($scope->getIdentifier() === 'openid') {
        $valid = TRUE;
        break;
      }
    }

    return $valid;
  }

}
