<?php

declare(strict_types=1);

namespace Drupal\Tests\xinshi_ai_usage\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\MemoryBackend;
use Drupal\Core\Site\Settings;
use Drupal\xinshi_ai_usage\Service\LitellmPriceCatalog;
use Drupal\xinshi_ai_usage\Service\LitellmRateConverter;
use Drupal\xinshi_ai_usage\Service\PriceBookException;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

/**
 * LiteLLM prefill: per-token USD costs become exact micros per million tokens,
 * the catalog fetch is bounded, cached and shape-checked.
 */
final class LitellmPriceCatalogTest extends TestCase {

  private int $now = 1_700_000_000;

  protected function setUp(): void {
    parent::setUp();
    // Settings is a static singleton; re-initialise for isolation.
    new Settings(['hash_salt' => 'isolated-hash-salt']);
  }

  public function testPerTokenCostToMicros(): void {
    // Typical JSON floats keep exact micros after rounding.
    $this->assertSame(3000000, LitellmRateConverter::perTokenCostToMicros(0.000003));
    $this->assertSame(280000, LitellmRateConverter::perTokenCostToMicros(0.00000028));
    $this->assertSame(28000, LitellmRateConverter::perTokenCostToMicros(0.000000028));
    $this->assertSame(10000000, LitellmRateConverter::perTokenCostToMicros(1.0E-5));
    $this->assertSame(0, LitellmRateConverter::perTokenCostToMicros(0));
    $this->assertSame(0, LitellmRateConverter::perTokenCostToMicros(0.0));

    // Numeric strings scale in exact decimal arithmetic, half up at scale 12.
    $this->assertSame(1500000, LitellmRateConverter::perTokenCostToMicros('0.0000015'));
    $this->assertSame(0, LitellmRateConverter::perTokenCostToMicros('0'));
    $this->assertSame(1, LitellmRateConverter::perTokenCostToMicros('0.0000000000005'));
    $this->assertSame(0, LitellmRateConverter::perTokenCostToMicros('0.00000000000049'));
    $this->assertSame(125000, LitellmRateConverter::perTokenCostToMicros('0.000000125'));

    foreach ([-0.1, -1, 'abc', '1e-6', '', '0.1.2', INF, NAN] as $invalid) {
      try {
        LitellmRateConverter::perTokenCostToMicros($invalid);
        $this->fail('expected invalid_rate for ' . var_export($invalid, TRUE));
      }
      catch (PriceBookException $e) {
        $this->assertStringStartsWith('invalid_rate', $e->getMessage());
      }
    }
    foreach ([10000000.0, '10000000000000.0'] as $overflow) {
      try {
        LitellmRateConverter::perTokenCostToMicros($overflow);
        $this->fail('expected rate_overflow for ' . var_export($overflow, TRUE));
      }
      catch (PriceBookException $e) {
        $this->assertStringStartsWith('rate_overflow', $e->getMessage());
      }
    }
  }

  public function testChatModelRates(): void {
    $catalog = json_decode('{
      "deepseek/deepseek-chat": {"mode": "chat", "input_cost_per_token": 0.00000028,
        "output_cost_per_token": 0.00000042, "cache_read_input_token_cost": 0.000000028,
        "cache_creation_input_token_cost": 0.0},
      "anthropic/claude-sonnet-4-5": {"mode": "chat", "input_cost_per_token": 0.000003,
        "output_cost_per_token": 0.000015, "cache_read_input_token_cost": 0.0000003,
        "cache_creation_input_token_cost": 0.00000375},
      "no-cache-model": {"mode": "chat", "input_cost_per_token": 0.0000001,
        "output_cost_per_token": 0.0000002},
      "null-cache-model": {"mode": "chat", "input_cost_per_token": 0.0000001,
        "output_cost_per_token": 0.0000002, "cache_read_input_token_cost": null},
      "text-embedding-3-small": {"mode": "embedding", "input_cost_per_token": 0.00000002},
      "no-cost-model": {"mode": "chat", "max_tokens": 100}
    }', TRUE);

    $rates = LitellmRateConverter::modelRates($catalog,
      ['deepseek/deepseek-chat', '  anthropic/claude-sonnet-4-5  ', '', 'no-cache-model', 'null-cache-model']);
    $this->assertSame([
      'deepseek/deepseek-chat' => [
        'per_million_input' => 280000,
        'per_million_cache_read' => 28000,
        'per_million_cache_write' => 0,
        'per_million_output' => 420000,
      ],
      'anthropic/claude-sonnet-4-5' => [
        'per_million_input' => 3000000,
        'per_million_cache_read' => 300000,
        'per_million_cache_write' => 3750000,
        'per_million_output' => 15000000,
      ],
      'no-cache-model' => [
        'per_million_input' => 100000,
        'per_million_cache_read' => 0,
        'per_million_cache_write' => 0,
        'per_million_output' => 200000,
      ],
      'null-cache-model' => [
        'per_million_input' => 100000,
        'per_million_cache_read' => 0,
        'per_million_cache_write' => 0,
        'per_million_output' => 200000,
      ],
    ], $rates);

    foreach ([
      ['does/not-exist', 'not in the catalog'],
      ['text-embedding-3-small', 'not models for supplier_chat'],
      ['no-cost-model', 'no supported standard costs'],
      ['', 'no model id was requested'],
    ] as [$modelId, $expectedFragment]) {
      try {
        LitellmRateConverter::modelRates($catalog, [$modelId]);
        $this->fail("expected catalog failure for {$modelId}");
      }
      catch (PriceBookException $e) {
        $this->assertStringContainsString($expectedFragment, $e->getMessage());
      }
    }
  }

  public function testFetchCatalogFetchesValidatesAndCaches(): void {
    $body = '{"deepseek/deepseek-chat": {"mode": "chat", "input_cost_per_token": 0.00000028}}';
    $handler = new MockHandler([new Response(200, ['Content-Type' => 'application/json'], $body)]);
    $cache = $this->cache();
    $catalog = new LitellmPriceCatalog(new Client(['handler' => $handler]), $cache,
      Settings::getInstance(), $this->time());

    $this->assertSame(json_decode($body, TRUE), $catalog->fetchCatalog());
    // The fixed default URL was requested.
    $this->assertSame(LitellmPriceCatalog::DEFAULT_URL, (string) $handler->getLastRequest()->getUri());

    // The catalog is cached: a second service with a dead client returns it.
    $dead = new Client(['handler' => function () {
      throw new \RuntimeException('the network must not be used');
    }]);
    $cached = (new LitellmPriceCatalog($dead, $cache, Settings::getInstance(), $this->time()))->fetchCatalog();
    $this->assertSame(json_decode($body, TRUE), $cached);
  }

  public function testFetchCatalogUsesTheHttpsMirrorFromSettings(): void {
    $handler = new MockHandler([new Response(200, [], '{}')]);
    $settings = new Settings([
      'hash_salt' => 'isolated-hash-salt',
      'xinshi_ai_usage.litellm_price_url' => 'https://mirror.example/prices.json',
    ]);
    try {
      (new LitellmPriceCatalog(new Client(['handler' => $handler]), $this->cache(), $settings, $this->time()))
        ->fetchCatalog();
      $this->fail('expected an empty catalog to be rejected');
    }
    catch (PriceBookException $e) {
      $this->assertStringStartsWith('catalog_invalid', $e->getMessage());
    }
    $this->assertSame('https://mirror.example/prices.json', (string) $handler->getLastRequest()->getUri());

    // Non-https mirrors are refused before any request leaves.
    $bad = new Settings([
      'hash_salt' => 'isolated-hash-salt',
      'xinshi_ai_usage.litellm_price_url' => 'http://mirror.example/prices.json',
    ]);
    try {
      (new LitellmPriceCatalog(new Client(['handler' => new MockHandler()]), $this->cache(), $bad, $this->time()))
        ->fetchCatalog();
      $this->fail('expected an http mirror to be refused');
    }
    catch (PriceBookException $e) {
      $this->assertStringStartsWith('invalid_catalog_url', $e->getMessage());
    }
  }

  public function testFetchCatalogRejectsFailuresAndBadShapes(): void {
    $cases = [
      [new Response(500, [], 'oops'), 'fetch_failed'],
      [new Response(200, [], 'not json'), 'catalog_invalid'],
      [new Response(200, [], '[1,2]'), 'catalog_invalid'],
      [new Response(200, [], '{"": {"mode": "chat"}}'), 'catalog_invalid'],
      [new Response(200, [], '{"a": 1}'), 'catalog_invalid'],
    ];
    foreach ($cases as [$response, $code]) {
      try {
        (new LitellmPriceCatalog(new Client(['handler' => new MockHandler([$response])]), $this->cache(),
          Settings::getInstance(), $this->time()))->fetchCatalog();
        $this->fail("expected {$code}");
      }
      catch (PriceBookException $e) {
        $this->assertStringStartsWith($code, $e->getMessage());
      }
    }

    // The response body is capped at the configured size.
    try {
      (new LitellmPriceCatalog(new Client(['handler' => new MockHandler([new Response(200, [], '{"a": {"b": 1}}')])]),
        $this->cache(), Settings::getInstance(), $this->time(), 10))->fetchCatalog();
      $this->fail('expected catalog_too_large');
    }
    catch (PriceBookException $e) {
      $this->assertStringStartsWith('catalog_too_large', $e->getMessage());
    }
  }

  private function cache(): MemoryBackend {
    return new MemoryBackend($this->time());
  }

  private function time(): TimeInterface {
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn($this->now);
    $time->method('getCurrentTime')->willReturn($this->now);
    return $time;
  }

}
