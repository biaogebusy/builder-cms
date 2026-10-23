<?php

declare(strict_types=1);

namespace Drupal\Tests\xinshi_ai_usage\Unit;

use Drupal\xinshi_ai_usage\Service\LitellmRateConverter;
use Drupal\xinshi_ai_usage\Service\PriceBookException;
use Drupal\xinshi_ai_usage\Service\PriceBookService;
use PHPUnit\Framework\TestCase;

/** Image prefill keeps billing units and input modalities separate. */
final class LitellmImageRatesTest extends TestCase {

  private const KIND = PriceBookService::KIND_SUPPLIER_IMAGE;

  public function testGptImagePricesKeepTextAndImageInputsSeparate(): void {
    $rates = LitellmRateConverter::modelRates($this->catalog(), ['gpt-image-2', 'fixed-image'], self::KIND);
    $this->assertSame([
      'per_image' => 0,
      'per_million_input' => 5_000_000,
      'per_million_input_image' => 8_000_000,
      'per_million_output' => 30_000_000,
    ], $rates['gpt-image-2']);
    $this->assertSame(['per_image' => 40_000], $rates['fixed-image']);
  }

  public function testSearchAndReportedMatchesStayWithinTheImageBook(): void {
    $catalog = $this->catalog();
    $this->assertSame(['gpt-image-2'], LitellmRateConverter::searchModels($catalog, 'gpt', 10, self::KIND));
    $this->assertSame([], LitellmRateConverter::searchModels($catalog, 'gpt'));
    $this->assertSame(['fixed-image'], LitellmRateConverter::searchModels($catalog, 'fixed', 10, self::KIND));
    $this->assertSame([], LitellmRateConverter::searchModels($catalog, 'mixed', 10, self::KIND));
    $auto = LitellmRateConverter::backendRates($catalog,
      ['GPT_IMAGE_2', 'chat-model', 'mixed-image', 'unknown-image'], self::KIND);
    $this->assertSame(['GPT_IMAGE_2'], array_keys($auto['rates']));
    $this->assertSame(['chat-model', 'mixed-image'], $auto['skipped']);
    $this->assertSame(['unknown-image'], $auto['unmatched']);
  }

  public function testReportedModelsDoNotPickAnArbitrarySizeVariant(): void {
    $catalog = ['vendor/high/1024-x-1024/variant-image' => [
      'mode' => 'image_generation', 'output_cost_per_image' => 0.05,
    ]];
    $result = LitellmRateConverter::backendRates($catalog, ['variant-image'], self::KIND);
    $this->assertSame([], $result['rates']);
    $this->assertSame(['variant-image'], $result['skipped']);
  }

  public function testUnsupportedExactModelDoesNotUseAnotherProvidersFixedPrice(): void {
    $catalog = $this->catalog() + ['vendor/mixed-image' => [
      'mode' => 'image_generation', 'output_cost_per_image' => 0.05,
    ]];
    $result = LitellmRateConverter::backendRates($catalog, ['mixed-image', 'MIXED_IMAGE'], self::KIND);
    $this->assertSame([], $result['rates']);
    $this->assertSame(['mixed-image', 'MIXED_IMAGE'], $result['skipped']);
  }

  public function testUnsupportedManualSelectionsRejectTheWholePrefill(): void {
    foreach (['chat-model', 'missing-image', 'mixed-image', 'pixel-image', 'unknown-image'] as $id) {
      try {
        LitellmRateConverter::modelRates($this->catalog(), ['gpt-image-2', $id], self::KIND);
        $this->fail('Unsupported prices must not produce a partial book.');
      }
      catch (PriceBookException $e) {
        $this->assertSame('catalog_mismatch', $e->priceCode);
        $this->assertStringContainsString($id, $e->getMessage());
      }
    }
  }

  public function testPerImagePricesUseMicrosAndDecimalHalfUpRounding(): void {
    foreach ([[1, 1_000_000], [1.0, 1_000_000], ['0.145', 145_000],
      ['0.0000005', 1], ['0.00000049', 0], [0, 0]] as [$cost, $expected]) {
      $rates = LitellmRateConverter::modelRates(['m' => [
        'mode' => 'image_edit', 'output_cost_per_image' => $cost,
      ]], ['m'], self::KIND);
      $this->assertSame(['per_image' => $expected], $rates['m']);
    }
    $this->assertSame(1_000_000_000_000, LitellmRateConverter::perTokenCostToMicros(1));
  }

  public function testInvalidAndOverflowingImagePricesAreRejected(): void {
    foreach ([-1, 'no-price', TRUE, [], INF, NAN, PHP_INT_MAX,
      '100000000000000000000.0000005', '9223372036854.7758075'] as $cost) {
      try {
        LitellmRateConverter::modelRates(['m' => [
          'mode' => 'image_generation', 'output_cost_per_image' => $cost,
        ]], ['m'], self::KIND);
        $this->fail('Invalid rates must not become zero-cost images.');
      }
      catch (PriceBookException $e) {
        $this->assertContains($e->priceCode, ['invalid_rate', 'rate_overflow']);
      }
      $this->assertSame([], LitellmRateConverter::searchModels(['m' => [
        'mode' => 'image_generation', 'output_cost_per_image' => $cost,
      ]], 'm', 10, self::KIND));
    }
  }

  private function catalog(): array {
    return [
      // Standard LiteLLM price fields captured for gpt-image-2 on 2026-10-23.
      'gpt-image-2' => [
        'mode' => 'image_generation',
        'input_cost_per_token' => 0.000005,
        'input_cost_per_image_token' => 0.000008,
        'output_cost_per_image_token' => 0.00003,
        'cache_read_input_token_cost' => 0.00000125,
        'input_cost_per_token_batches' => 0.0000025,
      ],
      'fixed-image' => ['mode' => 'image_edit', 'output_cost_per_image' => 0.04],
      'chat-model' => ['mode' => 'chat', 'input_cost_per_token' => 0.000001, 'output_cost_per_token' => 0.000002],
      'missing-image' => ['mode' => 'image_generation'],
      'mixed-image' => ['mode' => 'image_generation', 'output_cost_per_image' => 0.04,
        'input_cost_per_token' => 0.000001, 'output_cost_per_image_token' => 0.00003],
      'pixel-image' => ['mode' => 'image_generation', 'output_cost_per_pixel' => 0.0000001],
    ];
  }

}
