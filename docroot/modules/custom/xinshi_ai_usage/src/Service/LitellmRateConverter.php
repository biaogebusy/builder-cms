<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_usage\Service;

/**
 * Converts LiteLLM model_prices catalog entries into chat or image price book rates.
 *
 * LiteLLM publishes per-token USD costs. A chat price book stores micros of
 * its currency per million tokens, so per_million_* micros = per-token USD *
 * 10^12, rounded half up. That keeps CostRatingService's integer arithmetic
 * exact while following PriceBookService's half-up rounding policy.
 *
 * Tiered vendor pricing that LiteLLM publishes as separate fields (priority,
 * batches, cache discounts on images, per-second audio) is not imported.
 * Image prices retain separate text/image input rates when the catalog
 * supplies them. Mixed output prices require a separate usage breakdown.
 */
final class LitellmRateConverter {

  private function __construct() {}

  /**
   * Builds standard rates for the requested catalog models and book kind.
   *
   * Chat models retain the existing cache-rate defaults. Image models use
   * standard prices. One unsupported manual model id aborts the prefill so
   * the admin fixes the list before saving an incomplete book.
   *
   * @param array<string, array> $catalog
   *   Decoded LiteLLM catalog: model id => entry.
   * @param list<string> $modelIds
   *   Requested LiteLLM model ids.
   * @param string $kind
   *   The selected price book kind.
   *
   * @return array<string, array<string,int>>
   *   Model id => rates. Keys stay the LiteLLM ids; the admin renames them to
   *   the account's real model ids before saving.
   *
   * @throws \Drupal\xinshi_ai_usage\Service\PriceBookException
   *   When a model id is unknown, of the wrong kind, missing supported costs, or
   *   when no usable id was requested.
   */
  public static function modelRates(array $catalog, array $modelIds, string $kind = PriceBookService::KIND_SUPPLIER_CHAT): array {
    $unknown = $wrongKind = $incomplete = [];
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
      if (!self::matchesKind($entry, $kind)) {
        $wrongKind[] = $modelId;
        continue;
      }
      if (!self::isUsableEntry($entry, $kind)) {
        $incomplete[] = $modelId;
        continue;
      }
      $rates[$modelId] = self::modelRate($entry, $kind);
    }
    $problems = [];
    if ($unknown !== []) {
      $problems[] = 'not in the catalog: ' . implode(', ', $unknown);
    }
    if ($wrongKind !== []) {
      $problems[] = 'not models for ' . $kind . ': ' . implode(', ', $wrongKind);
    }
    if ($incomplete !== []) {
      $problems[] = 'no supported standard costs: ' . implode(', ', $incomplete);
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
   * Finds model ids whose name contains the query, within one book kind.
   *
   * Only entries with prices supported by the book kind are suggested.
   * Exact matches and prefix matches sort first; the remaining matches
   * follow in case-insensitive id order.
   *
   * @param array<string, array> $catalog
   *   Decoded LiteLLM catalog: model id => entry.
   * @param string $query
   *   Case-insensitive substring of the model id.
   * @param int $limit
   *   Maximum number of ids to return.
   * @param string $kind
   *   The selected price book kind.
   *
   * @return list<string>
   *   Matching model ids.
   */
  public static function searchModels(array $catalog, string $query, int $limit = 10, string $kind = PriceBookService::KIND_SUPPLIER_CHAT): array {
    $needle = mb_strtolower(trim($query));
    if ($needle === '') {
      return [];
    }
    $matches = [];
    foreach ($catalog as $modelId => $entry) {
      if (!is_string($modelId) || !self::isUsableEntry($entry, $kind)) {
        continue;
      }
      $haystack = mb_strtolower($modelId);
      if (!str_contains($haystack, $needle)) {
        continue;
      }
      if ($kind === PriceBookService::KIND_SUPPLIER_IMAGE) {
        try {
          self::modelRate($entry, $kind);
        }
        catch (PriceBookException) {
          continue;
        }
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
   * Builds rates for reported backend models, keyed by backend id.
   *
   * Backend ids often drop the provider prefix that LiteLLM keeps, so the
   * match tries the exact id first and then the id without its provider path
   * or the last path segment. Underscores and case do not matter. When
   * several entries share a segment, the shortest key wins: provider-specific
   * duplicates such as dated Azure or Bedrock aliases are longer.
   *
   * A reported model of another kind or with unsupported prices is skipped. A
   * model with no catalog entry is returned as unmatched so the caller can
   * ask the admin to add it manually.
   *
   * @param array<string, array> $catalog
   *   Decoded LiteLLM catalog: model id => entry.
   * @param list<string> $modelIds
   *   Model ids the site reported.
   * @param string $kind
   *   The selected price book kind.
   *
   * @return array{rates:array<string,array>,unmatched:list<string>,skipped:list<string>}
   *   Rates keyed by backend id, unmatched ids, and unsupported ids.
   */
  public static function backendRates(array $catalog, array $modelIds, string $kind = PriceBookService::KIND_SUPPLIER_CHAT): array {
    $rates = [];
    $unmatched = [];
    $skipped = [];
    if ($modelIds === []) {
      return ['rates' => $rates, 'unmatched' => $unmatched, 'skipped' => $skipped];
    }
    $kindIndex = self::catalogIndex($catalog, $kind);
    $anyIndex = self::catalogIndex($catalog, NULL);
    foreach ($modelIds as $modelId) {
      $modelId = trim((string) $modelId);
      if ($modelId === '') {
        continue;
      }
      // A known image model with unsupported prices must not fall back to
      // another provider's simpler price for the same model name.
      $knownKey = isset($catalog[$modelId]) ? $modelId
        : ($anyIndex['normalized'][self::normalizeModelId($modelId)] ?? NULL);
      if ($kind === PriceBookService::KIND_SUPPLIER_IMAGE && $knownKey !== NULL
        && !self::isUsableEntry($catalog[$knownKey], $kind)) {
        $skipped[] = $modelId;
        continue;
      }
      $match = self::matchWithIndex($catalog, $kindIndex, $modelId);
      if ($match === NULL) {
        if (self::matchWithIndex($catalog, $anyIndex, $modelId) !== NULL) {
          $skipped[] = $modelId;
        }
        else {
          $unmatched[] = $modelId;
        }
        continue;
      }
      $rates[$modelId] = self::modelRate($catalog[$match], $kind);
    }
    return ['rates' => $rates, 'unmatched' => $unmatched, 'skipped' => $skipped];
  }

  /**
   * Whether the exact catalog key can be prefilled into the selected book kind.
   */
  public static function isUsableModel(array $catalog, string $modelId, string $kind = PriceBookService::KIND_SUPPLIER_CHAT): bool {
    return self::isUsableEntry($catalog[trim($modelId)] ?? NULL, $kind);
  }

  /**
   * One-pass lookup structures for matching ids against the catalog.
   *
   * @return array{kind:?string,normalized:array<string,string>,segments:array<string,list<array{provider:string,length:int,key:string}>>}
   *   Normalized-key map (the first catalog key wins) and, per normalized
   *   last path segment, the candidate keys with their provider name and
   *   length. Matching many ids against a multi-thousand-entry catalog scans
   *   it once per kind instead of once per id.
   */
  private static function catalogIndex(array $catalog, ?string $kind): array {
    $normalized = [];
    $segments = [];
    foreach ($catalog as $key => $entry) {
      if (!is_string($key) || !self::isUsableEntry($entry, $kind)) {
        continue;
      }
      $normalizedKey = self::normalizeModelId($key);
      $normalized[$normalizedKey] ??= $key;
      $separator = strpos($key, '/');
      $provider = self::normalizeModelId($separator === FALSE ? $key : substr($key, 0, $separator));
      // Image variants with extra path segments can encode size/quality.
      // Only an explicit id can select them; never guess a variant from a model name.
      if ($kind !== PriceBookService::KIND_SUPPLIER_IMAGE || substr_count($key, '/') <= 1) {
        $segments[self::modelSegment($key)][] = [$provider, strlen($key), $key];
      }
    }
    return ['kind' => $kind, 'normalized' => $normalized, 'segments' => $segments];
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
    if (self::isUsableEntry($catalog[$modelId] ?? NULL, $index['kind'])) {
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
      'per_million_input' => self::costToMicros($entry['input_cost_per_token'], 12),
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
  private static function isUsableEntry(mixed $entry, ?string $kind): bool {
    if (!is_array($entry)) {
      return FALSE;
    }
    if ($kind === NULL) {
      return TRUE;
    }
    if (!self::matchesKind($entry, $kind)) {
      return FALSE;
    }
    if ($kind === PriceBookService::KIND_SUPPLIER_CHAT) {
      return isset($entry['input_cost_per_token'], $entry['output_cost_per_token']);
    }
    // A fixed image price and token prices can describe alternative billing
    // units. Do not combine them or flatten mixed text/image output prices.
    if (isset($entry['output_cost_per_image'])) {
      return !isset($entry['input_cost_per_token'])
        && !isset($entry['output_cost_per_token'])
        && !isset($entry['output_cost_per_image_token']);
    }
    return isset($entry['input_cost_per_token'], $entry['input_cost_per_image_token'],
      $entry['output_cost_per_image_token']) && !isset($entry['output_cost_per_token']);
  }

  private static function matchesKind(array $entry, string $kind): bool {
    return match ($kind) {
      PriceBookService::KIND_SUPPLIER_CHAT => ($entry['mode'] ?? NULL) === 'chat',
      PriceBookService::KIND_SUPPLIER_IMAGE => in_array($entry['mode'] ?? NULL,
        ['image_generation', 'image_edit'], TRUE),
      default => FALSE,
    };
  }

  /** Standard image prices; cache and batch discounts are not imported. */
  private static function modelRate(array $entry, string $kind): array {
    if ($kind === PriceBookService::KIND_SUPPLIER_CHAT) {
      return self::chatRate($entry);
    }
    if (isset($entry['output_cost_per_image'])) {
      return ['per_image' => self::costToMicros($entry['output_cost_per_image'], 6)];
    }
    return [
      'per_image' => 0,
      'per_million_input' => self::costToMicros($entry['input_cost_per_token'], 12),
      'per_million_input_image' => self::costToMicros($entry['input_cost_per_image_token'], 12),
      'per_million_output' => self::costToMicros($entry['output_cost_per_image_token'], 12),
    ];
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
    return self::costToMicros($cost, 12);
  }

  /** Scales a USD rate with decimal half-up rounding and checked integer bounds. */
  private static function costToMicros(mixed $cost, int $scale): int {
    if (is_int($cost)) {
      if ($cost < 0) {
        throw new PriceBookException('invalid_rate', 'cost must not be negative');
      }
      $digits = $cost === 0 ? '0' : (string) $cost . str_repeat('0', $scale);
    }
    elseif (is_float($cost)) {
      if (!is_finite($cost) || $cost < 0) {
        throw new PriceBookException('invalid_rate', 'cost must be a non-negative finite number');
      }
      $scaled = $cost * (10 ** $scale);
      if ($scaled >= (float) PHP_INT_MAX) {
        throw new PriceBookException('rate_overflow', 'cost exceeds the supported integer range');
      }
      return (int) round($scaled);
    }
    elseif (is_string($cost) && preg_match('/^\d+(?:\.\d+)?$/', $cost) === 1) {
      [$intPart, $fracPart] = array_pad(explode('.', $cost, 2), 2, '');
      $keep = str_pad(substr($fracPart, 0, $scale), $scale, '0');
      $digits = ltrim($intPart . $keep, '0') ?: '0';
      // Round half up at the first dropped fractional digit.
      $dropped = substr($fracPart, $scale);
      if ($dropped !== '' && $dropped[0] >= '5') {
        if (strlen($digits) > strlen((string) PHP_INT_MAX)
          || (strlen($digits) === strlen((string) PHP_INT_MAX) && strcmp($digits, (string) PHP_INT_MAX) >= 0)) {
          throw new PriceBookException('rate_overflow', 'cost exceeds the supported integer range');
        }
        $digits = (string) ((int) $digits + 1);
      }
    }
    else {
      throw new PriceBookException('invalid_rate', 'cost must be a non-negative number');
    }
    $max = (string) PHP_INT_MAX;
    if (strlen($digits) > strlen($max)
      || (strlen($digits) === strlen($max) && strcmp($digits, $max) > 0)) {
      throw new PriceBookException('rate_overflow', 'cost exceeds the supported integer range');
    }
    return (int) $digits;
  }

}
