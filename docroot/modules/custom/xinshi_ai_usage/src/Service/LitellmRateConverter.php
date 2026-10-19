<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_usage\Service;

/**
 * Converts LiteLLM model_prices catalog entries into chat price book rates.
 *
 * LiteLLM publishes per-token USD costs. A chat price book stores micros of
 * its currency per million tokens, so per_million_* micros = per-token USD *
 * 10^12, rounded half up. That keeps CostRatingService's integer arithmetic
 * exact while following PriceBookService's half-up rounding policy.
 *
 * Tiered vendor pricing that LiteLLM publishes as separate fields (priority,
 * batches, per-second audio, per-image) is intentionally ignored: the price
 * book schema has one flat rate per model and unused dimensions are not
 * implemented ahead of time.
 */
final class LitellmRateConverter {

  /** USD per token -> micros per million tokens: multiply by 10^12. */
  private const SCALE = 12;

  private function __construct() {}

  /**
   * Builds the chat rates of the requested catalog models.
   *
   * Models without cache price fields get 0 for those fields: the vendor
   * charges nothing extra for cache traffic on them. Nothing is returned
   * partially: one bad model id aborts the whole prefill so the admin fixes
   * the list instead of saving a silently incomplete book.
   *
   * @param array<string, array> $catalog
   *   Decoded LiteLLM catalog: model id => entry.
   * @param list<string> $modelIds
   *   Requested LiteLLM model ids.
   *
   * @return array<string, array{per_million_input:int,per_million_cache_read:int,per_million_cache_write:int,per_million_output:int}>
   *   Model id => rates. Keys stay the LiteLLM ids; the admin renames them to
   *   the account's real model ids before saving.
   *
   * @throws \Drupal\xinshi_ai_usage\Service\PriceBookException
   *   When a model id is unknown, not a chat model, missing token costs, or
   *   when no usable id was requested.
   */
  public static function chatModelRates(array $catalog, array $modelIds): array {
    $unknown = $notChat = $incomplete = [];
    $rates = [];
    foreach ($modelIds as $modelId) {
      $modelId = trim((string) $modelId);
      if ($modelId === '') {
        continue;
      }
      if (!isset($catalog[$modelId]) || !is_array($catalog[$modelId])) {
        $unknown[] = $modelId;
        continue;
      }
      $entry = $catalog[$modelId];
      if (($entry['mode'] ?? NULL) !== 'chat') {
        $notChat[] = $modelId;
        continue;
      }
      if (!isset($entry['input_cost_per_token'], $entry['output_cost_per_token'])) {
        $incomplete[] = $modelId;
        continue;
      }
      $rates[$modelId] = [
        'per_million_input' => self::perTokenCostToMicros($entry['input_cost_per_token']),
        'per_million_cache_read' => isset($entry['cache_read_input_token_cost'])
          ? self::perTokenCostToMicros($entry['cache_read_input_token_cost']) : 0,
        'per_million_cache_write' => isset($entry['cache_creation_input_token_cost'])
          ? self::perTokenCostToMicros($entry['cache_creation_input_token_cost']) : 0,
        'per_million_output' => self::perTokenCostToMicros($entry['output_cost_per_token']),
      ];
    }
    $problems = [];
    if ($unknown !== []) {
      $problems[] = 'not in the catalog: ' . implode(', ', $unknown);
    }
    if ($notChat !== []) {
      $problems[] = 'not chat models: ' . implode(', ', $notChat);
    }
    if ($incomplete !== []) {
      $problems[] = 'no per-token costs: ' . implode(', ', $incomplete);
    }
    if ($problems !== []) {
      throw new PriceBookException('catalog_mismatch', implode('; ', $problems));
    }
    if ($rates === []) {
      throw new PriceBookException('no_models', 'no model id was requested');
    }
    return $rates;
  }

  /**
   * Converts a per-token USD cost into micros per million tokens.
   *
   * Accepts the numeric JSON shapes LiteLLM uses (ints and floats) plus
   * numeric strings for robustness. Strings are scaled in exact decimal
   * arithmetic; floats are rounded half up, where the rounding error of the
   * multiplication stays far below half a micro for realistic prices.
   *
   * @param float|int|string $cost
   *   Per-token cost in USD.
   *
   * @return int
   *   Micros (1e-6 USD) per million tokens.
   *
   * @throws \Drupal\xinshi_ai_usage\Service\PriceBookException
   *   When the value is negative, non-numeric or overflows the integer range.
   */
  public static function perTokenCostToMicros(float|int|string $cost): int {
    if (is_int($cost)) {
      if ($cost < 0) {
        throw new PriceBookException('invalid_rate', 'per-token cost must not be negative');
      }
      $digits = (string) $cost;
    }
    elseif (is_float($cost)) {
      if (!is_finite($cost) || $cost < 0) {
        throw new PriceBookException('invalid_rate', 'per-token cost must be a non-negative finite number');
      }
      $scaled = $cost * (10 ** self::SCALE);
      if ($scaled > (float) PHP_INT_MAX) {
        throw new PriceBookException('rate_overflow', 'per-token cost exceeds the supported integer range');
      }
      return (int) round($scaled);
    }
    elseif (is_string($cost) && preg_match('/^\d+(?:\.\d+)?$/', $cost) === 1) {
      [$intPart, $fracPart] = array_pad(explode('.', $cost, 2), 2, '');
      $keep = str_pad(substr($fracPart, 0, self::SCALE), self::SCALE, '0');
      $digits = ltrim($intPart . $keep, '0') ?: '0';
      // Round half up at the first dropped fractional digit.
      $dropped = substr($fracPart, self::SCALE);
      if ($dropped !== '' && $dropped[0] >= '5') {
        $digits = (string) ((int) $digits + 1);
      }
    }
    else {
      throw new PriceBookException('invalid_rate', 'per-token cost must be a non-negative number');
    }
    $max = (string) PHP_INT_MAX;
    if (strlen($digits) > strlen($max)
      || (strlen($digits) === strlen($max) && strcmp($digits, $max) > 0)) {
      throw new PriceBookException('rate_overflow', 'per-token cost exceeds the supported integer range');
    }
    return (int) $digits;
  }

}
