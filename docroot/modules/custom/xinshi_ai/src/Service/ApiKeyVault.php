<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Service;

use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\PrivateKey;
use Drupal\Core\Site\Settings;

/** Keeps API keys encrypted and outside configuration exports. */
final class ApiKeyVault {

  public function __construct(
    private readonly KeyValueFactoryInterface $factory,
    private readonly PrivateKey $privateKey,
    private readonly string $collection = 'xinshi_ai.web_search',
  ) {}

  private function encryptionKey(): string {
    return hash_hkdf('sha256', $this->privateKey->get(), 32,
      $this->collection . '.v1', Settings::getHashSalt());
  }

  public function has(string $id = 'api_key'): bool {
    return $this->factory->get($this->collection)->has($id);
  }

  public function get(string $id = 'api_key'): string {
    $stored = $this->factory->get($this->collection)->get($id);
    $bytes = is_string($stored) ? base64_decode($stored, TRUE) : FALSE;
    if ($bytes === FALSE || strlen($bytes) < 29) {
      throw new \RuntimeException('API key is not configured.');
    }
    $key = openssl_decrypt(substr($bytes, 28), 'aes-256-gcm', $this->encryptionKey(),
      OPENSSL_RAW_DATA, substr($bytes, 0, 12), substr($bytes, 12, 16), $id);
    if ($key === FALSE || $key === '') {
      throw new \RuntimeException('API key is unavailable.');
    }
    return $key;
  }

  public function set(string $key, string $id = 'api_key'): void {
    if ($key === '' || strlen($key) > 4096 || preg_match('/[\s\x00-\x1f\x7f]/', $key)) {
      throw new \InvalidArgumentException('Invalid API key.');
    }
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($key, 'aes-256-gcm', $this->encryptionKey(), OPENSSL_RAW_DATA,
      $iv, $tag, $id);
    if ($cipher === FALSE) {
      throw new \RuntimeException('Could not save API key.');
    }
    $this->factory->get($this->collection)->set($id, base64_encode($iv . $tag . $cipher));
  }

  public function delete(string $id = 'api_key'): void {
    $this->factory->get($this->collection)->delete($id);
  }

}
