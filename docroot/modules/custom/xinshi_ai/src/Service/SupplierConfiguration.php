<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Site\Settings;

/** Supplies server-only credentials independently of the public model catalog. */
final class SupplierConfiguration {

  public const PLATFORMS = ['xinshi', 'aliyun'];

  public function __construct(
    private readonly ConfigFactoryInterface $config,
    private readonly ApiKeyVault $vault,
    private readonly Settings $settings,
  ) {}

  /** An explicit deployment override wins, including an empty value that disables the key. */
  private function override(string $platform): array {
    $keys = $this->settings->get('xinshi_ai.supplier_keys', []);
    return is_array($keys) && array_key_exists($platform, $keys) ? [$keys[$platform]] : [];
  }

  public function hasKey(string $platform): bool {
    $override = $this->override($platform);
    return $override !== [] ? $override[0] !== '' : $this->vault->has($platform);
  }

  /** Configuration exports never contain this value. */
  public function apiKey(string $platform): string {
    $override = $this->override($platform);
    $key = $override !== [] ? $override[0] : $this->vault->get($platform);
    if (!is_string($key) || $key === '' || strlen($key) > 4096 || preg_match('/[\s\x00-\x1f\x7f]/', $key)) {
      throw new \RuntimeException('Supplier credentials are unavailable.');
    }
    return $key;
  }

  /** Both CMS image execution and Node chat use the same gateway URL normalization. */
  public function baseUrl(string $platform): string {
    $path = $platform === 'xinshi' ? 'gateway.base_url' : 'suppliers.aliyun.base_url';
    $url = rtrim(trim((string) $this->config->get('xinshi_ai.settings')->get($path)), '/');
    if ($url === '' || !self::validUrl($url)) {
      throw new \RuntimeException('Supplier endpoint is unavailable.');
    }
    return $platform === 'xinshi' && !preg_match('#/v\d+$#', $url) ? $url . '/v1' : $url;
  }

  /** Only local development endpoints may use HTTP. URLs cannot carry credentials or queries. */
  public static function validUrl(string $url): bool {
    if (strlen($url) > 2048 || preg_match('/[\s\x00-\x1f\x7f]/', $url)) {
      return FALSE;
    }
    $parts = parse_url($url);
    if (!$parts || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
      return FALSE;
    }
    $host = strtolower($parts['host'] ?? '');
    $local = in_array($host, ['localhost', '127.0.0.1', '[::1]'], TRUE) || str_ends_with($host, '.docker.localhost');
    return filter_var($url, FILTER_VALIDATE_URL) !== FALSE &&
      (($parts['scheme'] ?? '') === 'https' || $local && ($parts['scheme'] ?? '') === 'http');
  }

  /** Omit unconfigured platforms; malformed or unreadable configured keys fail closed. */
  public function snapshot(): array {
    $platforms = [];
    foreach (self::PLATFORMS as $platform) {
      if ($this->hasKey($platform)) {
        $platforms[$platform] = [
          'apiKey' => $this->apiKey($platform), 'baseURL' => $this->baseUrl($platform),
          'protocol' => 'openai', 'account' => ['ref' => $platform, 'payer' => 'platform'],
        ];
      }
    }
    return ['version' => 1, 'platforms' => (object) $platforms];
  }

}
