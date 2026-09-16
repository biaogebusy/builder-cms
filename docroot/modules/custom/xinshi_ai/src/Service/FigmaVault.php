<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Service;

use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\Core\PrivateKey;
use Drupal\Core\Site\Settings;
use Drupal\xinshi_ai\Exception\FigmaException;

/** Encrypted, non-exportable application secret and per-account OAuth grants. */
final class FigmaVault {

  private KeyValueStoreInterface $values;

  public function __construct(KeyValueFactoryInterface $factory, private readonly PrivateKey $privateKey) {
    $this->values = $factory->get('xinshi_ai.figma');
  }

  private function key(): string {
    return hash_hkdf('sha256', $this->privateKey->get(), 32, 'xinshi_ai.figma.v1', Settings::getHashSalt());
  }

  public function has(string $id): bool {
    return $this->values->has($id);
  }

  public function get(string $id): ?array {
    $value = $this->values->get($id);
    if ($value === NULL) {
      return NULL;
    }
    try {
      $bytes = is_string($value) ? base64_decode($value, TRUE) : FALSE;
      if ($bytes === FALSE || strlen($bytes) < 29) {
        throw new \RuntimeException();
      }
      $plain = openssl_decrypt(substr($bytes, 28), 'aes-256-gcm', $this->key(), OPENSSL_RAW_DATA,
        substr($bytes, 0, 12), substr($bytes, 12, 16), $id);
      $decoded = $plain === FALSE ? NULL : json_decode($plain, TRUE, 32, JSON_THROW_ON_ERROR);
      if (!is_array($decoded)) {
        throw new \RuntimeException();
      }
      return $decoded;
    }
    catch (\Throwable) {
      throw new FigmaException('figma_connection_required', 409);
    }
  }

  public function set(string $id, array $value): void {
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt(json_encode($value, JSON_THROW_ON_ERROR), 'aes-256-gcm', $this->key(),
      OPENSSL_RAW_DATA, $iv, $tag, $id);
    if ($cipher === FALSE) {
      throw new FigmaException('figma_unavailable', 503);
    }
    $this->values->set($id, base64_encode($iv . $tag . $cipher));
  }

  public function delete(string $id): void {
    $this->values->delete($id);
  }

}
