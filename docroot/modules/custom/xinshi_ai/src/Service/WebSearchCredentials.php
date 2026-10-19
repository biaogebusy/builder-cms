<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Service;

use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\PrivateKey;
use Drupal\Core\Site\Settings;

/** Keeps the DeepSeek key encrypted and outside configuration exports. */
final class WebSearchCredentials {

  public function __construct(
    private readonly KeyValueFactoryInterface $factory,
    private readonly PrivateKey $privateKey,
  ) {}

  private function encryptionKey(): string {
    return hash_hkdf('sha256', $this->privateKey->get(), 32,
      'xinshi_ai.web_search.v1', Settings::getHashSalt());
  }

  public function has(): bool {
    return $this->factory->get('xinshi_ai.web_search')->has('api_key');
  }

  public function get(): string {
    $stored = $this->factory->get('xinshi_ai.web_search')->get('api_key');
    $bytes = is_string($stored) ? base64_decode($stored, TRUE) : FALSE;
    if ($bytes === FALSE || strlen($bytes) < 29) {
      throw new \RuntimeException('DeepSeek key is not configured.');
    }
    $key = openssl_decrypt(substr($bytes, 28), 'aes-256-gcm', $this->encryptionKey(),
      OPENSSL_RAW_DATA, substr($bytes, 0, 12), substr($bytes, 12, 16), 'api_key');
    if ($key === FALSE || $key === '') {
      throw new \RuntimeException('DeepSeek key is unavailable.');
    }
    return $key;
  }

  public function set(string $key): void {
    if ($key === '' || strlen($key) > 4096 || preg_match('/\s/', $key)) {
      throw new \InvalidArgumentException('Invalid DeepSeek key.');
    }
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($key, 'aes-256-gcm', $this->encryptionKey(), OPENSSL_RAW_DATA,
      $iv, $tag, 'api_key');
    if ($cipher === FALSE) {
      throw new \RuntimeException('Could not save DeepSeek key.');
    }
    $this->factory->get('xinshi_ai.web_search')->set('api_key', base64_encode($iv . $tag . $cipher));
  }

  public function delete(): void {
    $this->factory->get('xinshi_ai.web_search')->delete('api_key');
  }

}
