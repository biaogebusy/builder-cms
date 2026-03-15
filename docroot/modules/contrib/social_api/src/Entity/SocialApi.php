<?php

namespace Drupal\social_api\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Site\Settings;

/**
 * Defines a base class for Social API content entities.
 */
class SocialApi extends ContentEntityBase implements ContentEntityInterface {

  /**
   * {@inheritdoc}
   */
  public static function create(array $values = []): EntityInterface {
    if (isset($values['token'])) {
      $values['token'] = static::encryptToken($values['token']);
    }
    return parent::create($values);
  }

  /**
   * Gets the Drupal user ID associated to the record.
   *
   * @return int
   *   User ID.
   */
  public function getUserId(): int {
    return (int) $this->get('user_id')->target_id;
  }

  /**
   * Sets the encrypted, serialized token.
   *
   * @param string $token
   *   The serialized access token.
   *
   * @return static
   */
  public function setToken(string $token): static {
    $this->set('token', $this->encryptToken($token));
    return $this;
  }

  /**
   * Returns the unencrypted, serialized access token.
   *
   * @return string
   *   The serialized access token.
   */
  public function getToken(): string {
    $token = $this->get('token')->value;
    return $this->decryptToken($token);
  }

  /**
   * Returns the encrypted token.
   *
   * @param string $token
   *   The plain-text token provided by the provider.
   *
   * @return string
   *   The encrypted token.
   */
  protected static function encryptToken(string $token): string {
    $key = static::getEncryptionKey();

    // Remove the base64 encoding from our key.
    $encryption_key = base64_decode($key);

    // Generates an initialization vector.
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));

    // Encrypts the data using AES 256 encryption in CBC mode using our
    // encryption key and initialization vector.
    $encrypted = openssl_encrypt($token, 'aes-256-cbc', $encryption_key, 0, $iv);

    // The $iv is just as important as the key for decrypting, so save it with
    // our encrypted data using a unique separator (::).
    return base64_encode("$encrypted::$iv");
  }

  /**
   * Decrypts the stored token.
   *
   * @param string $token
   *   Encrypted token stored in database.
   *
   * @return string
   *   The plain-text token provided by the provider.
   */
  protected function decryptToken(string $token): string {
    $key = $this->getEncryptionKey();

    // Removes the base64 encoding from our key.
    $encryption_key = base64_decode($key);

    // Split the encrypted data from our IV - our unique separator used was
    // "::".
    [$encrypted_data, $iv] = explode('::', base64_decode($token), 2);

    return openssl_decrypt($encrypted_data, 'aes-256-cbc', $encryption_key, 0, $iv);
  }

  /**
   * Gets the hash salt for this drupal installation.
   *
   * @return string
   *   The encryption key.
   */
  protected static function getEncryptionKey(): string {
    return Settings::getHashSalt();
  }

}
