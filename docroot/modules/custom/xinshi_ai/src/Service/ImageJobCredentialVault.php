<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Service;

use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface;
use Drupal\Core\PrivateKey;
use Drupal\Core\Site\Settings;

/**
 * Encrypted, short-lived store for the connection secrets of custom-platform image jobs (UB2.7).
 *
 * A user who brings their own key hands the endpoint and API key to the job
 * request; from there they must reach the worker and nothing else. The job
 * node and its revisions, JSON:API, Views, the SSE stream, usage events and
 * logs never hold them: they live here keyed by the job UUID, encrypted with a
 * key derived from the site's private key and hash salt, bound to that UUID,
 * and deleted when the job ends. Entries also expire on their own, so a crash
 * between the last retry and the cleanup cannot leave a key behind.
 */
class ImageJobCredentialVault {

  public const COLLECTION = 'xinshi_ai.image_job_credentials';
  /** Longest a job may wait for its worker before its key is gone. */
  public const TTL_SECONDS = 86_400;
  /** Request parameter keys that carry connection secrets and never persist. */
  public const SECRET_PARAM_KEYS = ['endpoint', 'api_key', 'apiKey', 'baseURL'];
  public const REDACTED = '[redacted]';
  private const INFO = 'xinshi_ai.image_job_credentials.v1';
  private const MAX_KEY_LENGTH = 4096;
  /** Shorter strings are not keys; replacing them would mangle unrelated text. */
  private const MIN_REDACT_LENGTH = 8;

  private KeyValueStoreExpirableInterface $values;

  public function __construct(KeyValueExpirableFactoryInterface $factory, private readonly PrivateKey $privateKey) {
    $this->values = $factory->get(self::COLLECTION);
  }

  /**
   * Pulls the connection secrets out of a create request.
   *
   * Current clients send them as a top-level `credentials` object; older ones
   * put them into `params`, from where they are removed so the job entity never
   * stores them. Values are trimmed; absent values are NULL.
   *
   * @return array{endpoint: ?string, api_key: ?string}
   */
  public static function fromPayload(array $payload, array &$params): array {
    $credentials = is_array($payload['credentials'] ?? NULL) ? $payload['credentials'] : [];
    $endpoint = self::text($credentials['endpoint'] ?? $params['endpoint'] ?? NULL);
    $apiKey = self::text($credentials['apiKey'] ?? $credentials['api_key'] ?? $params['api_key'] ?? $params['apiKey'] ?? NULL);
    foreach (self::SECRET_PARAM_KEYS as $key) {
      unset($params[$key]);
    }
    return ['endpoint' => $endpoint, 'api_key' => $apiKey];
  }

  /**
   * Checks the credentials a platform needs; NULL when they are acceptable.
   */
  public static function validate(array $credentials, string $platform): ?string {
    if ($platform !== 'custom') {
      return NULL;
    }
    $endpoint = $credentials['endpoint'] ?? NULL;
    if (!is_string($endpoint) || !preg_match('~^https?://[^\s/]+~i', $endpoint)
      || filter_var($endpoint, FILTER_VALIDATE_URL) === FALSE) {
      return 'credentials.endpoint must be an absolute http(s) URL.';
    }
    $apiKey = $credentials['api_key'] ?? NULL;
    if (!is_string($apiKey) || $apiKey === '') {
      return 'credentials.apiKey is required for the custom platform.';
    }
    if (strlen($apiKey) > self::MAX_KEY_LENGTH || preg_match('/[^\x21-\x7e]/', $apiKey)) {
      return 'credentials.apiKey must be printable ASCII of at most 4096 characters.';
    }
    return NULL;
  }

  /**
   * Stores the secrets of a job until it ends or the entry expires.
   *
   * @throws \RuntimeException
   *   When the secrets could not be encrypted or written.
   */
  public function store(string $jobUuid, string $endpoint, string $apiKey): void {
    $iv = random_bytes(12);
    $tag = '';
    $plain = json_encode(['endpoint' => $endpoint, 'api_key' => $apiKey],
      JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', $this->key(), OPENSSL_RAW_DATA, $iv, $tag, $jobUuid);
    if ($cipher === FALSE) {
      throw new \RuntimeException('Unable to encrypt the image job credentials.');
    }
    $this->values->setWithExpire($jobUuid, base64_encode($iv . $tag . $cipher), self::TTL_SECONDS);
  }

  /**
   * The secrets of a job, or NULL when none are stored, they expired, or they do not decrypt.
   *
   * @return array{endpoint: string, api_key: string}|null
   */
  public function get(string $jobUuid): ?array {
    $stored = $this->values->get($jobUuid);
    $bytes = is_string($stored) ? base64_decode($stored, TRUE) : FALSE;
    if ($bytes === FALSE || strlen($bytes) < 29) {
      return NULL;
    }
    $plain = openssl_decrypt(substr($bytes, 28), 'aes-256-gcm', $this->key(), OPENSSL_RAW_DATA,
      substr($bytes, 0, 12), substr($bytes, 12, 16), $jobUuid);
    $decoded = $plain === FALSE ? NULL : json_decode($plain, TRUE);
    if (!is_array($decoded) || !is_string($decoded['endpoint'] ?? NULL) || $decoded['endpoint'] === ''
      || !is_string($decoded['api_key'] ?? NULL) || $decoded['api_key'] === '') {
      return NULL;
    }
    return ['endpoint' => $decoded['endpoint'], 'api_key' => $decoded['api_key']];
  }

  public function delete(string $jobUuid): void {
    $this->values->delete($jobUuid);
  }

  /**
   * Replaces the job's API key wherever it appears in a text, e.g. a provider error or URL.
   */
  public function redact(string $jobUuid, string $text): string {
    if ($text === '') {
      return $text;
    }
    $credentials = $this->get($jobUuid);
    if ($credentials === NULL || strlen($credentials['api_key']) < self::MIN_REDACT_LENGTH) {
      return $text;
    }
    return str_replace($credentials['api_key'], self::REDACTED, $text);
  }

  private function key(): string {
    return hash_hkdf('sha256', $this->privateKey->get(), 32, self::INFO, Settings::getHashSalt());
  }

  private static function text(mixed $value): ?string {
    if (!is_string($value)) {
      return NULL;
    }
    $value = trim($value);
    return $value === '' ? NULL : $value;
  }

}
