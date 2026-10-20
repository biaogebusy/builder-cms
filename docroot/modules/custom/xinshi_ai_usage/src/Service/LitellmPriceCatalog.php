<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_usage\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Site\Settings;
use Drupal\xinshi_ai_usage\Contract\PriceCatalogFetcher;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\StreamInterface;

/**
 * Fetches the LiteLLM model price catalog for prefilling price book drafts.
 *
 * The catalog is public list-price data maintained by the LiteLLM community;
 * it is not the contract price of any supplier. It is cached for one hour so
 * repeated prefill clicks do not re-download a multi-megabyte file.
 */
final class LitellmPriceCatalog implements PriceCatalogFetcher {

  public const DEFAULT_URL = 'https://raw.githubusercontent.com/BerriAI/litellm/main/model_prices_and_context_window.json';
  public const DEFAULT_MAX_BYTES = 16777216;

  private const CACHE_CID = 'xinshi_ai_usage:price_catalog:litellm:v1';
  private const CACHE_TTL_SECONDS = 3600;

  public function __construct(
    private readonly ClientInterface $httpClient,
    private readonly CacheBackendInterface $cache,
    private readonly Settings $settings,
    private readonly TimeInterface $time,
    private readonly int $maxBytes = self::DEFAULT_MAX_BYTES,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function fetchCatalog(): array {
    $cached = $this->cache->get(self::CACHE_CID);
    if ($cached !== FALSE && is_array($cached->data)) {
      /** @var array<string, array> $data */
      return $cached->data;
    }

    try {
      $response = $this->httpClient->request('GET', $this->catalogUrl(), [
        'connect_timeout' => 5,
        'timeout' => 30,
        // The catalog host is fixed; following redirects would let a
        // changed upstream send the request anywhere.
        'allow_redirects' => FALSE,
        'headers' => ['Accept' => 'application/json'],
      ]);
    }
    catch (GuzzleException $e) {
      throw new PriceBookException('fetch_failed', 'could not fetch the LiteLLM price catalog: ' . $e->getMessage(), $e);
    }
    if ($response->getStatusCode() !== 200) {
      throw new PriceBookException('fetch_failed',
        'the LiteLLM price catalog request returned HTTP ' . $response->getStatusCode());
    }

    $decoded = json_decode($this->readBody($response->getBody()), TRUE);
    if (!is_array($decoded) || $decoded === []) {
      throw new PriceBookException('catalog_invalid', 'the LiteLLM price catalog is not a non-empty JSON object');
    }
    foreach ($decoded as $modelId => $entry) {
      if (!is_string($modelId) || $modelId === '' || !is_array($entry)) {
        throw new PriceBookException('catalog_invalid', 'the LiteLLM price catalog has an unexpected entry shape');
      }
    }

    $this->cache->set(self::CACHE_CID, $decoded, $this->time->getRequestTime() + self::CACHE_TTL_SECONDS);
    return $decoded;
  }

  /**
   * The catalog URL: the fixed default, or an https mirror set in settings.php.
   */
  private function catalogUrl(): string {
    /** @var string $url */
    $url = $this->settings->get('xinshi_ai_usage.litellm_price_url') ?? self::DEFAULT_URL;
    $parts = parse_url($url);
    if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])
      || ($parts['host'] === '')) {
      throw new PriceBookException('invalid_catalog_url',
        'xinshi_ai_usage.litellm_price_url must be an https URL');
    }
    return $url;
  }

  /**
   * Reads the response body up to the configured size cap.
   */
  private function readBody(StreamInterface $body): string {
    $body->rewind();
    $chunks = [];
    $length = 0;
    while (!$body->eof()) {
      $chunk = $body->read(65536);
      $length += strlen($chunk);
      if ($length > $this->maxBytes) {
        throw new PriceBookException('catalog_too_large',
          'the LiteLLM price catalog exceeds the ' . $this->maxBytes . ' byte limit');
      }
      $chunks[] = $chunk;
    }
    return implode('', $chunks);
  }

}
