<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_usage\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;

/**
 * Computes purchasing cost for one observed attempt using the active price book.
 *
 * Costs are in micros of the price book currency (1 unit = 1,000,000 micros)
 * and computed with integer arithmetic so there is no floating-point drift.
 * Per-token price = per_million_rate / 1,000,000; total = tokens * price.
 *
 * Rules:
 * - Cache-read tokens are a subset of input tokens; they are never added on top.
 * - Cache-write tokens are separate from input tokens when charged.
 * - Reasoning tokens are a subset of output tokens; they are not added on top.
 * - Missing usage quality = rated as unpriced, not zero.
 * - Invalid usage = unpriced with reason "invalid_usage"; it is never summed.
 * - Unknown model / account = unpriced with reason "unknown_model".
 *
 * A cost entry is keyed by (site, attempt_id, observation_revision, source_kind).
 * Re-rating the same revision with the same source_kind upserts; a later
 * reconciliation can promote rated_estimate to reconciled by writing a new
 * source_kind row.
 */
final class CostRatingService {

  public const TABLE = 'ai_usage_cost_entry';
  public const STATE_UNPRICED = 'unpriced';
  public const STATE_RATED_ESTIMATE = 'rated_estimate';
  public const STATE_RECONCILED = 'reconciled';
  public const SOURCE_PRICE_BOOK = 'price_book';
  public const MILLION = 1_000_000;

  public function __construct(
    private readonly Connection $database,
    private readonly PriceBookService $priceBook,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Rates a single observed attempt and writes a cost entry.
   *
   * @param string $siteId
   *   The logical site id.
   * @param string $attemptId
   *   The attempt id from the usage event.
   * @param int $observationRevision
   *   The observation revision being rated.
   * @param array $provider
   *   The provider block from the event: account_ref, requested_model, etc.
   * @param array|null $usage
   *   The normalized usage block, or NULL for attempts without usage.
   * @param int $occurredAtMs
   *   Milliseconds since epoch; selects the price book active at that moment.
   * @param string $sourceKind
   *   One of the SOURCE_* constants.
   *
   * @return array{valuation_state:string,total_cost_micros:int,currency:string,unpriced_reason:string|null}
   */
  public function rateAttempt(string $siteId, string $attemptId, int $observationRevision,
    array $provider, ?array $usage, int $occurredAtMs,
    string $sourceKind = self::SOURCE_PRICE_BOOK): array {
    $result = $this->computeRate($siteId, $provider, $usage, $occurredAtMs);
    $this->database->merge(self::TABLE)
      ->keys([
        'site_id' => $siteId,
        'attempt_id' => $attemptId,
        'observation_revision' => $observationRevision,
        'source_kind' => $sourceKind,
      ])
      ->fields([
        'price_version_id' => $result['price_version_id'],
        'currency' => $result['currency'],
        'input_cost_micros' => $result['input_cost_micros'],
        'cache_read_cost_micros' => $result['cache_read_cost_micros'],
        'cache_write_cost_micros' => $result['cache_write_cost_micros'],
        'output_cost_micros' => $result['output_cost_micros'],
        'total_cost_micros' => $result['total_cost_micros'],
        'valuation_state' => $result['valuation_state'],
        'unpriced_reason' => $result['unpriced_reason'],
        'computed_at' => (int) ($this->time->getCurrentMicroTime() * 1000),
      ])
      ->execute();
    return [
      'valuation_state' => $result['valuation_state'],
      'total_cost_micros' => $result['total_cost_micros'],
      'currency' => $result['currency'],
      'unpriced_reason' => $result['unpriced_reason'],
    ];
  }

  /**
   * Pure computation: returns per-component costs without writing anything.
   *
   * @return array{valuation_state:string,price_version_id:int|null,currency:string,input_cost_micros:int,cache_read_cost_micros:int,cache_write_cost_micros:int,output_cost_micros:int,total_cost_micros:int,unpriced_reason:string|null}
   */
  public function computeRate(string $siteId, array $provider, ?array $usage, int $occurredAtMs): array {
    $zero = [
      'input_cost_micros' => 0,
      'cache_read_cost_micros' => 0,
      'cache_write_cost_micros' => 0,
      'output_cost_micros' => 0,
      'total_cost_micros' => 0,
    ];
    $version = $this->priceBook->loadActive($siteId, PriceBookService::KIND_SUPPLIER_CHAT, $occurredAtMs);
    if ($version === NULL) {
      return ['valuation_state' => self::STATE_UNPRICED, 'price_version_id' => NULL,
        'currency' => '', 'unpriced_reason' => 'no_price_book'] + $zero;
    }
    $currency = $version['currency'];
    if ($usage === NULL || ($usage['quality'] ?? '') !== 'reported') {
      $reason = match ($usage['quality'] ?? 'missing') {
        'invalid' => 'invalid_usage',
        'missing' => 'missing_usage',
        default => 'unknown_usage',
      };
      return ['valuation_state' => self::STATE_UNPRICED, 'price_version_id' => $version['id'],
        'currency' => $currency, 'unpriced_reason' => $reason] + $zero;
    }
    $accountRef = (string) ($provider['account_ref'] ?? '');
    $modelId = (string) ($provider['requested_model'] ?? '');
    try {
      $rates = $this->priceBook->parseRates($version['rates_json']);
    }
    catch (PriceBookException) {
      return ['valuation_state' => self::STATE_UNPRICED, 'price_version_id' => $version['id'],
        'currency' => $currency, 'unpriced_reason' => 'invalid_price_book'] + $zero;
    }
    $rate = $this->priceBook->findModelRate($rates, $accountRef, $modelId);
    if ($rate === NULL) {
      return ['valuation_state' => self::STATE_UNPRICED, 'price_version_id' => $version['id'],
        'currency' => $currency, 'unpriced_reason' => 'unknown_model'] + $zero;
    }

    $inputTotal = self::parseCount($usage['input_tokens_total'] ?? NULL);
    $cacheRead = self::parseCount($usage['input_tokens_cache_read'] ?? NULL);
    $cacheWrite = self::parseCount($usage['input_tokens_cache_write'] ?? NULL);
    $outputTotal = self::parseCount($usage['output_tokens_total'] ?? NULL);

    // Non-cache input = total input - cache-read input (cache-read is a subset).
    $uncachedInput = $inputTotal !== NULL && $cacheRead !== NULL ? max(0, $inputTotal - $cacheRead) : $inputTotal;

    $inputCost = $uncachedInput !== NULL
      ? $this->perTokenMicros($rate['per_million_input'], $uncachedInput, $version['rounding_policy'])
      : 0;
    $cacheReadCost = $cacheRead !== NULL
      ? $this->perTokenMicros($rate['per_million_cache_read'], $cacheRead, $version['rounding_policy'])
      : 0;
    $cacheWriteCost = $cacheWrite !== NULL
      ? $this->perTokenMicros($rate['per_million_cache_write'], $cacheWrite, $version['rounding_policy'])
      : 0;
    $outputCost = $outputTotal !== NULL
      ? $this->perTokenMicros($rate['per_million_output'], $outputTotal, $version['rounding_policy'])
      : 0;
    $total = $inputCost + $cacheReadCost + $cacheWriteCost + $outputCost;

    return [
      'valuation_state' => self::STATE_RATED_ESTIMATE,
      'price_version_id' => $version['id'],
      'currency' => $currency,
      'input_cost_micros' => $inputCost,
      'cache_read_cost_micros' => $cacheReadCost,
      'cache_write_cost_micros' => $cacheWriteCost,
      'output_cost_micros' => $outputCost,
      'total_cost_micros' => $total,
      'unpriced_reason' => NULL,
    ];
  }

  /**
   * Tokens * per-million rate / 1,000,000, rounded to integer micros.
   *
   * Uses integer arithmetic to avoid float drift.
   */
  public function perTokenMicros(int $perMillion, int $tokens, string $roundingPolicy): int {
    if ($tokens <= 0 || $perMillion <= 0) {
      return 0;
    }
    $product = intdiv($perMillion * $tokens, self::MILLION);
    $remainder = ($perMillion * $tokens) % self::MILLION;
    if ($roundingPolicy === PriceBookService::ROUNDING_HALF_UP && $remainder * 2 >= self::MILLION) {
      $product++;
    }
    return $product;
  }

  /**
   * Parses a decimal-string count (from the wire contract) to int or NULL.
   */
  private static function parseCount(mixed $value): ?int {
    if ($value === NULL) {
      return NULL;
    }
    if (is_int($value) && $value >= 0) {
      return $value;
    }
    if (is_string($value) && preg_match('/^\d+$/', $value)) {
      return (int) $value;
    }
    return NULL;
  }

}
