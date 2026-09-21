<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_usage\Contract;

/**
 * Normalizes an OpenAI-compatible Images API response into the usage contract.
 *
 * Counterpart of the Node `normalizeChatUsage()` for `images/generations` and
 * `images/edits` (UB2.4). The supplier-defined billing unit of an image call is
 * the number of images the upstream returned (`data[]`), so a response with a
 * countable `data` list is `reported` even when the model publishes no token
 * usage (qwen-image, z-image). GPT image models add a `usage` block whose
 * `input_tokens` / `output_tokens` / `total_tokens` and
 * `input_tokens_details.text_tokens` / `image_tokens` are whitelisted. Values
 * that contradict their inclusion relations are `invalid` and never summed.
 *
 * Changing the whitelist or the inclusion rules requires a new VERSION.
 */
final class ImageUsageNormalizer {

  public const VERSION = 'images-openai-v1';

  private const TOKEN_KEYS = ['total_tokens', 'input_tokens', 'output_tokens'];
  private const DETAIL_KEYS = ['text_tokens', 'image_tokens'];

  /**
   * Returns the contract usage block for a raw provider response.
   *
   * @param mixed $raw
   *   The decoded response array as the provider returned it, before any
   *   image was decoded or dropped.
   *
   * @return array
   *   A usage block accepted by UsageEventValidator; counts are decimal strings.
   */
  public static function normalize(mixed $raw): array {
    if (!is_array($raw)) {
      return self::block('missing', NULL, NULL);
    }
    $snapshot = [];
    $malformed = [];
    $data = $raw['data'] ?? NULL;
    if ($data !== NULL) {
      if (is_array($data) && ($data === [] || array_is_list($data))) {
        $snapshot['data.count'] = count($data);
      }
      else {
        $malformed[] = 'data';
      }
    }
    $usage = $raw['usage'] ?? NULL;
    if ($usage !== NULL) {
      if (!is_array($usage)) {
        $malformed[] = 'usage';
      }
      else {
        foreach (self::TOKEN_KEYS as $key) {
          self::take($snapshot, $malformed, $key, $usage[$key] ?? NULL);
        }
        $details = $usage['input_tokens_details'] ?? NULL;
        if ($details !== NULL) {
          if (!is_array($details)) {
            $malformed[] = 'input_tokens_details';
          }
          else {
            foreach (self::DETAIL_KEYS as $key) {
              self::take($snapshot, $malformed, "input_tokens_details.$key", $details[$key] ?? NULL);
            }
          }
        }
      }
    }
    $rawUsage = $snapshot === [] ? NULL : $snapshot;
    if ($rawUsage === NULL && $malformed === []) {
      return self::block('missing', NULL, NULL);
    }
    $hash = $rawUsage === NULL ? NULL : hash('sha256', UsageEventValidator::canonicalJson($rawUsage));
    if ($malformed !== []) {
      return self::block('invalid', $rawUsage, $hash, 'malformed:' . implode(',', $malformed));
    }
    if (!isset($snapshot['data.count'])) {
      return self::block('invalid', $rawUsage, $hash, 'missing_data');
    }
    $input = $snapshot['input_tokens'] ?? NULL;
    $output = $snapshot['output_tokens'] ?? NULL;
    $total = $snapshot['total_tokens'] ?? NULL;
    if ($total !== NULL && $input !== NULL && $output !== NULL && $total < $input + $output) {
      return self::block('invalid', $rawUsage, $hash, 'total_below_parts');
    }
    $text = $snapshot['input_tokens_details.text_tokens'] ?? NULL;
    $image = $snapshot['input_tokens_details.image_tokens'] ?? NULL;
    if ($input !== NULL && $text !== NULL && $image !== NULL && $text + $image !== $input) {
      return self::block('invalid', $rawUsage, $hash, 'input_split_mismatch');
    }
    return [
      'normalizer_version' => self::VERSION,
      'quality' => 'reported',
      'input_tokens_total' => self::count($input),
      'input_tokens_cache_read' => NULL,
      'input_tokens_cache_write' => NULL,
      'output_tokens_total' => self::count($output),
      'output_tokens_reasoning' => NULL,
      'provider_total_tokens' => self::count($total),
      'images_generated' => self::count($snapshot['data.count']),
      'raw_usage' => $rawUsage,
      'raw_usage_hash' => $hash,
    ];
  }

  private static function take(array &$snapshot, array &$malformed, string $key, mixed $value): void {
    if ($value === NULL) {
      return;
    }
    if (is_int($value) && $value >= 0) {
      $snapshot[$key] = $value;
      return;
    }
    $malformed[] = $key;
  }

  private static function count(?int $value): ?string {
    return $value === NULL ? NULL : (string) $value;
  }

  private static function block(string $quality, ?array $rawUsage, ?string $hash, ?string $reason = NULL): array {
    $block = [
      'normalizer_version' => self::VERSION,
      'quality' => $quality,
      'input_tokens_total' => NULL,
      'input_tokens_cache_read' => NULL,
      'input_tokens_cache_write' => NULL,
      'output_tokens_total' => NULL,
      'output_tokens_reasoning' => NULL,
      'provider_total_tokens' => NULL,
      'images_generated' => NULL,
      'raw_usage' => $rawUsage,
      'raw_usage_hash' => $hash,
    ];
    if ($reason !== NULL) {
      $block['invalid_reason'] = $reason;
    }
    return $block;
  }

}
