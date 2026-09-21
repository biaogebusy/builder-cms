<?php

declare(strict_types=1);

namespace Drupal\Tests\xinshi_ai_usage\Unit;

use Drupal\xinshi_ai_usage\Contract\ImageUsageNormalizer;
use Drupal\xinshi_ai_usage\Contract\UsageEventValidator;
use PHPUnit\Framework\TestCase;

/**
 * Pure tests of the Images API usage normalizer (UB2.4).
 *
 * The supplier bills an image call per returned image, so a countable `data`
 * list is a reported observation even without token usage; contradictory
 * token figures are invalid and never summed.
 */
final class ImageUsageNormalizerTest extends TestCase {

  public function testImageCountAloneIsReported(): void {
    $usage = ImageUsageNormalizer::normalize(['created' => 1, 'data' => [['url' => 'a'], ['url' => 'b']]]);
    $this->assertSame(ImageUsageNormalizer::VERSION, $usage['normalizer_version']);
    $this->assertSame('reported', $usage['quality']);
    $this->assertSame('2', $usage['images_generated']);
    $this->assertNull($usage['input_tokens_total']);
    $this->assertNull($usage['output_tokens_total']);
    $this->assertNull($usage['provider_total_tokens']);
    $this->assertNull($usage['input_tokens_cache_read']);
    $this->assertSame(['data.count' => 2], $usage['raw_usage']);
    $this->assertSame(hash('sha256', UsageEventValidator::canonicalJson(['data.count' => 2])), $usage['raw_usage_hash']);
    $this->assertArrayNotHasKey('invalid_reason', $usage);
  }

  public function testEmptyDataListIsReportedAsZeroImages(): void {
    $usage = ImageUsageNormalizer::normalize(['data' => []]);
    $this->assertSame('reported', $usage['quality']);
    $this->assertSame('0', $usage['images_generated']);
  }

  public function testTokenUsageOfGptImageModelsIsWhitelisted(): void {
    $usage = ImageUsageNormalizer::normalize([
      'data' => [['b64_json' => 'x']],
      'usage' => [
        'total_tokens' => 1100, 'input_tokens' => 100, 'output_tokens' => 1000,
        'input_tokens_details' => ['text_tokens' => 60, 'image_tokens' => 40, 'other' => 1],
        'not_in_contract' => 5,
      ],
    ]);
    $this->assertSame('reported', $usage['quality']);
    $this->assertSame('1', $usage['images_generated']);
    $this->assertSame('100', $usage['input_tokens_total']);
    $this->assertSame('1000', $usage['output_tokens_total']);
    $this->assertSame('1100', $usage['provider_total_tokens']);
    $this->assertNull($usage['input_tokens_cache_read']);
    $this->assertNull($usage['output_tokens_reasoning']);
    $this->assertSame([
      'data.count' => 1, 'total_tokens' => 1100, 'input_tokens' => 100, 'output_tokens' => 1000,
      'input_tokens_details.text_tokens' => 60, 'input_tokens_details.image_tokens' => 40,
    ], $usage['raw_usage']);
  }

  public function testMissingWhenNothingUsableIsPresent(): void {
    foreach ([NULL, 'string', 42, [], ['created' => 1], ['data' => NULL, 'usage' => NULL]] as $raw) {
      $usage = ImageUsageNormalizer::normalize($raw);
      $this->assertSame('missing', $usage['quality'], json_encode($raw));
      $this->assertNull($usage['images_generated']);
      $this->assertNull($usage['raw_usage']);
      $this->assertNull($usage['raw_usage_hash']);
      $this->assertArrayNotHasKey('invalid_reason', $usage);
    }
  }

  public function testContradictoryOrMalformedUsageIsInvalidNotSummed(): void {
    $image = [['url' => 'a']];
    $cases = [
      'malformed:data' => ['data' => ['url' => 'a']],
      'malformed:usage' => ['data' => $image, 'usage' => 5],
      'malformed:input_tokens' => ['data' => $image, 'usage' => ['input_tokens' => -1]],
      'malformed:total_tokens' => ['data' => $image, 'usage' => ['total_tokens' => 1.5]],
      'malformed:input_tokens_details' => ['data' => $image, 'usage' => ['input_tokens_details' => 'x']],
      'missing_data' => ['usage' => ['total_tokens' => 10]],
      'total_below_parts' => ['data' => $image, 'usage' => ['total_tokens' => 5, 'input_tokens' => 4, 'output_tokens' => 4]],
      'input_split_mismatch' => ['data' => $image, 'usage' => ['input_tokens' => 10,
        'input_tokens_details' => ['text_tokens' => 3, 'image_tokens' => 3]]],
    ];
    foreach ($cases as $reason => $raw) {
      $usage = ImageUsageNormalizer::normalize($raw);
      $this->assertSame('invalid', $usage['quality'], $reason);
      $this->assertSame($reason, $usage['invalid_reason']);
      $this->assertNull($usage['images_generated'], $reason);
      $this->assertNull($usage['input_tokens_total'], $reason);
      $this->assertNull($usage['provider_total_tokens'], $reason);
    }
    // Whitelisted values that were readable stay with the invalid block for reconciliation.
    $usage = ImageUsageNormalizer::normalize($cases['total_below_parts']);
    $this->assertSame(['data.count' => 1, 'total_tokens' => 5, 'input_tokens' => 4, 'output_tokens' => 4], $usage['raw_usage']);
    $this->assertNotNull($usage['raw_usage_hash']);
    // Nothing readable at all: no snapshot and no hash, but still invalid.
    $usage = ImageUsageNormalizer::normalize($cases['malformed:data']);
    $this->assertNull($usage['raw_usage']);
    $this->assertNull($usage['raw_usage_hash']);
  }

  public function testEveryBlockSatisfiesTheEventContract(): void {
    $blocks = [
      ImageUsageNormalizer::normalize(['data' => [['url' => 'a'], ['url' => 'b']]]),
      ImageUsageNormalizer::normalize(['data' => [['url' => 'a']],
        'usage' => ['total_tokens' => 1, 'input_tokens' => 4, 'output_tokens' => 4]]),
      ImageUsageNormalizer::normalize(NULL),
    ];
    foreach ($blocks as $block) {
      $validated = UsageEventValidator::validate($this->observedEvent($block));
      $this->assertSame($block['quality'], $validated['usage']['quality']);
      $this->assertSame($block['images_generated'], $validated['usage']['images_generated']);
      $this->assertSame($block['raw_usage'], $validated['usage']['raw_usage']);
      $this->assertSame($block['invalid_reason'] ?? NULL, $validated['usage']['invalid_reason'] ?? NULL);
    }
  }

  private function observedEvent(array $usage): array {
    return [
      'schema_version' => 1,
      'event_id' => 'att-1:observed:1',
      'producer_id' => 'cms-image',
      'site_id' => 'site-a',
      'event_type' => 'attempt.observed',
      'operation_id' => 'job-1',
      'authorization_id' => NULL,
      'attempt_id' => 'att-1',
      'logical_call_id' => 'image',
      'observation_revision' => 1,
      'occurred_at' => '2023-11-14T22:13:24.120Z',
      'context' => ['billing_account_id' => NULL, 'actor_user_id' => '7', 'feature' => 'text_to_image',
        'stage' => 'image', 'attempt_no' => 1, 'payer' => 'platform', 'billing_role' => 'primary',
        'chat_run_id' => NULL, 'task_id' => NULL, 'chat_id' => NULL],
      'provider' => ['account_ref' => 'xinshi', 'requested_model' => 'qwen-image',
        'resolved_model' => NULL, 'gateway_request_id' => 'req-1', 'provider_request_id' => NULL],
      'dispatch_state' => 'sent',
      'outcome' => 'succeeded',
      'error_code' => NULL,
      'usage' => $usage,
      'timing' => ['started_at' => '2023-11-14T22:13:20.000Z', 'first_token_at' => NULL,
        'finished_at' => '2023-11-14T22:13:24.100Z'],
    ];
  }

}
