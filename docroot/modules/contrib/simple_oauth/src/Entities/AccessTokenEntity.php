<?php

namespace Drupal\simple_oauth\Entities;

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\Traits\AccessTokenTrait;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\Traits\TokenEntityTrait;

class AccessTokenEntity implements AccessTokenEntityInterface {

  use AccessTokenTrait, TokenEntityTrait, EntityTrait;

  /**
   * {@inheritdoc}
   */
  public function convertToJWT() {
    $private_claims = [];
    \Drupal::moduleHandler()
      ->alter('simple_oauth_private_claims', $private_claims, $this);
    if (!is_array($private_claims)) {
      $message = 'An implementation of hook_simple_oauth_private_claims_alter ';
      $message .= 'returns an invalid $private_claims value. $private_claims ';
      $message .= 'must be an array.';
      throw new \InvalidArgumentException($message);
    }

    $id = $this->getIdentifier();
    $now = new \DateTimeImmutable('@' . \Drupal::time()->getCurrentTime());
    $key_path = $this->privateKey->getKeyPath();
    $key = InMemory::file($key_path);
    $config = Configuration::forSymmetricSigner(new Sha256(), $key);

    $builder = $config->builder()
      ->permittedFor($this->getClient()->getIdentifier())
      ->identifiedBy($id)
      ->withHeader('jti', $id)
      ->issuedAt($now)
      ->canOnlyBeUsedAfter($now)
      ->expiresAt($this->getExpiryDateTime())
      ->relatedTo($this->getUserIdentifier())
      ->withClaim('scope', $this->getScopes());

    foreach ($private_claims as $claim_name => $value) {
      $builder->withClaim($claim_name, $value);
    }

    return $builder->getToken($config->signer(), $config->signingKey());
  }

}
