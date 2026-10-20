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
      $rates[$modelId] = self::chatRate($entry);
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
   * Finds chat model ids whose name contains the query.
   *
   * Only entries with token costs and mode "chat" can be prefilled, so other
   * catalog entries are not suggested. Exact matches and prefix matches sort
   * first; the remaining matches follow in case-insensitive id order.
   *
   * @param array<string, array> $catalog
   *   Decoded LiteLLM catalog: model id => entry.
   * @param string $query
   *   Case-insensitive substring of the model id.
   * @param int $limit
   *   Maximum number of ids to return.
   *
   * @return list<string>
   *   Matching model ids.
   */
  public static function searchChatModels(array $catalog, string $query, int $limit = 10): array {
    $needle = mb_strtolower(trim($query));
    if ($needle === '') {
      return [];
    }
    $matches = [];
    foreach ($catalog as $modelId => $entry) {
      if (!is_string($modelId)
        || !is_array($entry)
        || ($entry['mode'] ?? NULL) !== 'chat'
        || !isset($entry['input_cost_per_token'], $entry['output_cost_per_token'])) {
        continue;
      }
      $haystack = mb_strtolower($modelId);
      if (!str_contains($haystack, $needle)) {
        continue;
      }
      // Rank: exact match 0, prefix match 1, substring match 2.
      $rank = $haystack === $needle ? 0 : (str_starts_with($haystack, $needle) ? 1 : 2);
      $matches[] = [$rank, $haystack, $modelId];
    }
    usort($matches, static fn(array $a, array $b): int => [$a[0], $a[1]] <=> [$b[0], $b[1]]);
    return array_map(static fn(array $match): string => $match[2],
      array_slice($matches, 0, max(1, $limit)));
  }

  /**
   * Builds chat rates for reported backend models, keyed by backend id.
   *
   * Backend ids often drop the provider prefix that LiteLLM keeps, so the
   * match tries the exact id first and then the id without its provider path
   * or the last path segment. Underscores and case do not matter. When
   * several entries share a segment, the shortest key wins: provider-specific
   * duplicates such as dated Azure or Bedrock aliases are longer.
   *
   * A reported model that matches a non-chat catalog entry (for example an
   * image model) is skipped silently: it does not belong in a chat book. A
   * model with no catalog entry is returned as unmatched so the caller can
   * ask the admin to add it manually.
   *
   * @param array<string, array> $catalog
   *   Decoded LiteLLM catalog: model id => entry.
   * @param list<string> $modelIds
   *   Model ids the site reported.
   *
   * @return array{rates:array<string,array>,unmatched:list<string>,skipped:list<string>}
   *   Rates keyed by backend id, ids with no catalog entry, and non-chat ids.
   */
  public static function backendChatRates(array $catalog, array $modelIds): array {
    $rates = [];
    $unmatched = [];
    $skipped = [];
    if ($modelIds === []) {
      return ['rates' => $rates, 'unmatched' => $unmatched, 'skipped' => $skipped];
    }
    $chatIndex = self::catalogIndex($catalog, TRUE);
    $anyIndex = self::catalogIndex($catalog, FALSE);
    foreach ($modelIds as $modelId) {
      $modelId = trim((string) $modelId);
      if ($modelId === '') {
        continue;
      }
      $match = self::matchWithIndex($catalog, $chatIndex, $modelId);
      if ($match === NULL) {
        if (self::matchWithIndex($catalog, $anyIndex, $modelId) !== NULL) {
          $skipped[] = $modelId;
        }
        else {
          $unmatched[] = $modelId;
        }
        continue;
      }
      $rates[$modelId] = self::chatRate($catalog[$match]);
    }
    return ['rates' => $rates, 'unmatched' => $unmatched, 'skipped' => $skipped];
  }

  /**
   * Whether the exact catalog key can be prefilled into a chat book.
   */
  public static function isUsableChatModel(array $catalog, string $modelId): bool {
    return self::isUsableEntry($catalog[trim($modelId)] ?? NULL, TRUE);
  }

  /**
   * One-pass lookup structures for matching ids against the catalog.
   *
   * @return array{chatOnly:bool,normalized:array<string,string>,segments:array<string,list<array{provider:string,length:int,key:string}>>}
   *   Normalized-key map (the first catalog key wins) and, per normalized
   *   last path segment, the candidate keys with their provider name and
   *   length. Matching many ids against a multi-thousand-entry catalog scans
   *   it once per kind instead of once per id.
   */
  private static function catalogIndex(array $catalog, bool $chatOnly): array {
    $normalized = [];
    $segments = [];
    foreach ($catalog as $key => $entry) {
      if (!is_string($key) || !self::isUsableEntry($entry, $chatOnly)) {
        continue;
      }
      $normalizedKey = self::normalizeModelId($key);
      $normalized[$normalizedKey] ??= $key;
      $separator = strpos($key, '/');
      $provider = self::normalizeModelId($separator === FALSE ? $key : substr($key, 0, $separator));
      $segments[self::modelSegment($key)][] = [$provider, strlen($key), $key];
    }
    return ['chatOnly' => $chatOnly, 'normalized' => $normalized, 'segments' => $segments];
  }

  /**
   * Matches one model id using a prebuilt index.
   *
   * Exact key first, then normalized equality, then segment candidates ranked
   * by provider-name match, key length and key.
   */
  private static function matchWithIndex(array $catalog, array $index, string $modelId): ?string {
    $modelId = trim($modelId);
    if ($modelId === '') {
      return NULL;
    }
    if (self::isUsableEntry($catalog[$modelId] ?? NULL, $index['chatOnly'])) {
      return $modelId;
    }
    $normalized = $index['normalized'][self::normalizeModelId($modelId)] ?? NULL;
    if ($normalized !== NULL) {
      return $normalized;
    }
    $segment = self::modelSegment($modelId);
    $candidates = $index['segments'][$segment] ?? [];
    if ($candidates === []) {
      return NULL;
    }
    $ranked = [];
    foreach ($candidates as [$provider, $length, $key]) {
      // Prefer the provider entry named after the model family:
      // deepseek/deepseek-chat beats azure_ai/deepseek-chat. Then prefer
      // the shortest key, which drops dated and regional aliases.
      $providerMatches = $provider !== '' && str_contains($segment, $provider);
      $ranked[] = [$providerMatches ? 0 : 1, $length, $key];
    }
    usort($ranked, static fn(array $a, array $b): int => $a <=> $b);
    return $ranked[0][2];
  }

  /**
   * Chat rates of one usable catalog entry.
   *
   * @return array{per_million_input:int,per_million_cache_read:int,per_million_cache_write:int,per_million_output:int}
   */
  private static function chatRate(array $entry): array {
    return [
      'per_million_input' => self::perTokenCostToMicros($entry['input_cost_per_token']),
      'per_million_cache_read' => isset($entry['cache_read_input_token_cost'])
        ? self::perTokenCostToMicros($entry['cache_read_input_token_cost']) : 0,
      'per_million_cache_write' => isset($entry['cache_creation_input_token_cost'])
        ? self::perTokenCostToMicros($entry['cache_creation_input_token_cost']) : 0,
      'per_million_output' => self::perTokenCostToMicros($entry['output_cost_per_token']),
    ];
  }

  /**
   * Whether a catalog entry can supply rates for the requested kind.
   */
  private static function isUsableEntry(mixed $entry, bool $chatOnly): bool {
    if (!is_array($entry)) {
      return FALSE;
    }
    // A non-chat match only classifies the id (for example as an image
    // model), so it does not need token costs.
    return !$chatOnly
      || (($entry['mode'] ?? NULL) === 'chat'
        && isset($entry['input_cost_per_token'], $entry['output_cost_per_token']));
  }

  /**
   * Normalizes an id for case- and underscore-insensitive comparison.
   */
  private static function normalizeModelId(string $modelId): string {
    return strtolower(str_replace('_', '-', trim($modelId)));
  }

  /**
   * Returns the last path segment of an id, normalized.
   */
  private static function modelSegment(string $modelId): string {
    $position = strrpos($modelId, '/');
    return self::normalizeModelId($position === FALSE ? $modelId : substr($modelId, $position + 1));
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
