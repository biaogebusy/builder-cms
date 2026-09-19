<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_usage\Service;

use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\Core\PrivateKey;
use Drupal\Core\Site\Settings;

/**
 * Encrypted, non-exportable registry of usage producers managed in the admin UI.
 *
 * Secrets never enter configuration, so they cannot leak through config export
 * or version control. Each entry is `['secret', 'site_id', 'created_at']`. An
 * entry that no longer decrypts (site private key or hash salt rotated) is
 * treated as unregistered; the administrator re-issues the secret.
 */
final class ProducerVault {

  public const PRODUCER_PATTERN = '/^[A-Za-z0-9_.-]{1,64}\z/';
  public const SITE_PATTERN = '/^[\x21-\x7E]{1,128}\z/';
  private const COLLECTION = 'xinshi_ai_usage.producers';

  private KeyValueStoreInterface $values;

  public function __construct(KeyValueFactoryInterface $factory, private readonly PrivateKey $privateKey) {
    $this->values = $factory->get(self::COLLECTION);
  }

  /** Producers that are registered and still readable, keyed by producer ID. */
  public function all(): array {
    $producers = [];
    foreach ($this->values->getAll() as $id => $stored) {
      $producer = $this->decrypt((string) $id, $stored);
      if ($producer !== NULL) {
        $producers[$id] = $producer;
      }
    }
    ksort($producers);
    return $producers;
  }

  public function get(string $id): ?array {
    return $this->decrypt($id, $this->values->get($id));
  }

  /** Registers or rotates a producer; the previous secret stops working immediately. */
  public function set(string $id, string $secret, string $siteId, int $createdAt): void {
    $iv = random_bytes(12);
    $tag = '';
    $plain = json_encode(['secret' => $secret, 'site_id' => $siteId, 'created_at' => $createdAt], JSON_THROW_ON_ERROR);
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', $this->key(), OPENSSL_RAW_DATA, $iv, $tag, $id);
    if ($cipher === FALSE) {
      throw new \RuntimeException('Unable to encrypt the producer secret.');
    }
    $this->values->set($id, base64_encode($iv . $tag . $cipher));
  }

  public function delete(string $id): void {
    $this->values->delete($id);
  }

  /** A fresh URL-safe secret; 32 random bytes are enough for HMAC-SHA256. */
  public static function generateSecret(): string {
    return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
  }

  private function key(): string {
    return hash_hkdf('sha256', $this->privateKey->get(), 32, 'xinshi_ai_usage.producer.v1', Settings::getHashSalt());
  }

  private function decrypt(string $id, mixed $stored): ?array {
    $bytes = is_string($stored) ? base64_decode($stored, TRUE) : FALSE;
    if ($bytes === FALSE || strlen($bytes) < 29) {
      return NULL;
    }
    $plain = openssl_decrypt(substr($bytes, 28), 'aes-256-gcm', $this->key(), OPENSSL_RAW_DATA,
      substr($bytes, 0, 12), substr($bytes, 12, 16), $id);
    $decoded = $plain === FALSE ? NULL : json_decode($plain, TRUE);
    if (!is_array($decoded) || !is_string($decoded['secret'] ?? NULL) || $decoded['secret'] === ''
      || !is_string($decoded['site_id'] ?? NULL) || $decoded['site_id'] === '') {
      return NULL;
    }
    return [
      'secret' => $decoded['secret'],
      'site_id' => $decoded['site_id'],
      'created_at' => is_int($decoded['created_at'] ?? NULL) ? $decoded['created_at'] : 0,
    ];
  }

}
