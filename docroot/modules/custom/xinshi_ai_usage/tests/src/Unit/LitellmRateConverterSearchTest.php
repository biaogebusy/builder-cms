<?php

declare(strict_types=1);

namespace Drupal\Tests\xinshi_ai_usage\Unit;

use Drupal\xinshi_ai_usage\Service\LitellmRateConverter;
use PHPUnit\Framework\TestCase;

/**
 * The prefill can search the catalog and match reported backend model ids.
 */
final class LitellmRateConverterSearchTest extends TestCase {

  public function testSearchReturnsOnlyUsableChatModels(): void {
    $catalog = $this->catalog();
    $this->assertSame([
      'deepseek/deepseek-chat',
      'deepseek/deepseek-v4-pro',
      'azure_ai/deepseek-chat',
    ], LitellmRateConverter::searchModels($catalog, 'deepseek'));
    $this->assertSame(['deepseek/deepseek-v4-pro'],
      LitellmRateConverter::searchModels($catalog, 'deepseek/deepseek-v4-pro', 1));
    $this->assertSame([], LitellmRateConverter::searchModels($catalog, 'embedding'));
    $this->assertSame([], LitellmRateConverter::searchModels($catalog, '  '));
  }

  public function testReportedIdsMatchTheShortestCatalogKey(): void {
    $result = LitellmRateConverter::backendRates($this->catalog(), [
      'deepseek-chat',
      'deepseek/deepseek-v4-pro',
      'qwen-image',
      'text-embedding-3-small',
      'unknown-model',
    ]);

    $this->assertSame(['deepseek-chat', 'deepseek/deepseek-v4-pro'], array_keys($result['rates']));
    $this->assertSame(280000, $result['rates']['deepseek-chat']['per_million_input']);
    $this->assertSame(420000, $result['rates']['deepseek-chat']['per_million_output']);
    $this->assertSame(['qwen-image', 'text-embedding-3-small'], $result['skipped']);
    $this->assertSame(['unknown-model'], $result['unmatched']);
  }

  public function testUnderscoresAndCaseDoNotMatter(): void {
    $result = LitellmRateConverter::backendRates($this->catalog(), ['DEEPSEEK_CHAT']);
    $this->assertSame(280000, $result['rates']['DEEPSEEK_CHAT']['per_million_input']);
    $this->assertSame([], $result['unmatched']);
  }

  public function testEmptyReportedListReturnsEmptyResult(): void {
    $this->assertSame(['rates' => [], 'unmatched' => [], 'skipped' => []],
      LitellmRateConverter::backendRates($this->catalog(), []));
  }

  /**
   * Catalog with provider duplicates, a non-chat and an unknown id.
   */
  private function catalog(): array {
    $chat = static fn(float $input, float $output): array => [
      'mode' => 'chat',
      'input_cost_per_token' => $input,
      'output_cost_per_token' => $output,
    ];
    return [
      'deepseek/deepseek-chat' => $chat(0.00000028, 0.00000042),
      'azure_ai/deepseek-chat' => $chat(0.00000029, 0.00000043),
      'deepseek/deepseek-v4-pro' => $chat(0.00000132, 0.00000396),
      'gpt-5.5' => $chat(0.000005, 0.00003),
      'text-embedding-3-small' => ['mode' => 'embedding', 'input_cost_per_token' => 0.00000002],
      'qwen-image' => ['mode' => 'image_generation'],
    ];
  }

}
