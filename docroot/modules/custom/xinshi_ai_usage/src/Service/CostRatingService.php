<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_usage\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\IntegrityConstraintViolationException;

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
 * Re-rating the same revision with the same source_kind is an idempotent no-op
 * only when the result is identical; different contents are a conflict. A
 * later reconciliation writes a separate source_kind row.
 */
final class CostRatingService {

  public const TABLE = 'ai_usage_cost_entry';
  public const STATE_UNPRICED = 'unpriced';
  public const STATE_RATED_ESTIMATE = 'rated_estimate';
  public const STATE_RECONCILED = 'reconciled';
  public const SOURCE_PRICE_BOOK = 'price_book';
  public const SOURCE_SUPPLIER_RECONCILIATION = 'supplier_reconciliation';
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
   * @return array{valuation_state:string,total_cost_micros:int|null,currency:string|null,unpriced_reason:string|null}
   */
  public function rateAttempt(string $siteId, string $attemptId, int $observationRevision,
    array $provider, ?array $usage, int $occurredAtMs,
    string $sourceKind = self::SOURCE_PRICE_BOOK): array {
    if (!in_array($sourceKind, [self::SOURCE_PRICE_BOOK, self::SOURCE_SUPPLIER_RECONCILIATION], TRUE)) {
      throw new PriceBookException('invalid_source_kind', 'unsupported cost entry source kind');
    }
    $result = $this->computeRate($siteId, $provider, $usage, $occurredAtMs);
    $key = [
      'site_id' => $siteId,
      'attempt_id' => $attemptId,
      'observation_revision' => $observationRevision,
      'source_kind' => $sourceKind,
    ];
    $fields = [
      ...$key,
      'price_version_id' => $result['price_version_id'],
      'currency' => $result['currency'],
      'input_cost_micros' => $result['input_cost_micros'],
      'cache_read_cost_micros' => $result['cache_read_cost_micros'],
      'cache_write_cost_micros' => $result['cache_write_cost_micros'],
      'output_cost_micros' => $result['output_cost_micros'],
      'total_cost_micros' => $result['total_cost_micros'],
      'valuation_state' => $result['valuation_state'],
      'unpriced_reason' => $result['unpriced_reason'],
      'computed_at' => (int) round($this->time->getCurrentMicroTime() * 1000),
    ];
    try {
      $this->database->insert(self::TABLE)->fields($fields)->execute();
    }
    catch (IntegrityConstraintViolationException $e) {
      // Redelivery is a no-op only when the immutable result is identical.
      $existing = $this->database->select(self::TABLE, 'c')
        ->fields('c', array_keys($fields))
        ->condition('site_id', $siteId)
        ->condition('attempt_id', $attemptId)
        ->condition('observation_revision', $observationRevision)
        ->condition('source_kind', $sourceKind)
        ->execute()
        ->fetchAssoc();
      if ($existing !== FALSE && $this->sameCostEntry($existing, $fields)) {
        return [
          'valuation_state' => $result['valuation_state'],
          'total_cost_micros' => $result['total_cost_micros'],
          'currency' => $result['currency'],
          'unpriced_reason' => $result['unpriced_reason'],
        ];
      }
      throw new PriceBookException('cost_conflict',
        'an immutable cost entry already exists with different contents', $e);
    }
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
   * @return array{valuation_state:string,price_version_id:int|null,currency:string|null,input_cost_micros:int|null,cache_read_cost_micros:int|null,cache_write_cost_micros:int|null,output_cost_micros:int|null,total_cost_micros:int|null,unpriced_reason:string|null}
   */
  public function computeRate(string $siteId, array $provider, ?array $usage, int $occurredAtMs): array {
    $unpriced = [
      'input_cost_micros' => NULL,
      'cache_read_cost_micros' => NULL,
      'cache_write_cost_micros' => NULL,
      'output_cost_micros' => NULL,
      'total_cost_micros' => NULL,
    ];
    $version = $this->priceBook->loadActive($siteId, PriceBookService::KIND_SUPPLIER_CHAT, $occurredAtMs);
    if ($version === NULL) {
      return ['valuation_state' => self::STATE_UNPRICED, 'price_version_id' => NULL,
        'currency' => NULL, 'unpriced_reason' => 'no_price_book'] + $unpriced;
    }
    $currency = $version['currency'];
    if ($usage === NULL || ($usage['quality'] ?? '') !== 'reported') {
      $reason = match ($usage['quality'] ?? 'missing') {
        'invalid' => 'invalid_usage',
        'missing' => 'missing_usage',
        default => 'unknown_usage',
      };
      return ['valuation_state' => self::STATE_UNPRICED, 'price_version_id' => $version['id'],
        'currency' => $currency, 'unpriced_reason' => $reason] + $unpriced;
    }
    $accountRef = (string) ($provider['account_ref'] ?? '');
    $modelId = (string) ($provider['resolved_model'] ?? '');
    if ($modelId === '') {
      $modelId = (string) ($provider['requested_model'] ?? '');
    }
    try {
      $rates = $this->priceBook->parseRates($version['rates_json']);
    }
    catch (PriceBookException) {
      return ['valuation_state' => self::STATE_UNPRICED, 'price_version_id' => $version['id'],
        'currency' => $currency, 'unpriced_reason' => 'invalid_price_book'] + $unpriced;
    }
    $rate = $this->priceBook->findModelRate($rates, $accountRef, $modelId);
    if ($rate === NULL) {
      return ['valuation_state' => self::STATE_UNPRICED, 'price_version_id' => $version['id'],
        'currency' => $currency, 'unpriced_reason' => 'unknown_model'] + $unpriced;
    }

    $inputTotal = self::parseCount($usage['input_tokens_total'] ?? NULL);
    $cacheRead = self::parseCount($usage['input_tokens_cache_read'] ?? NULL);
    $cacheWrite = self::parseCount($usage['input_tokens_cache_write'] ?? NULL);
    $outputTotal = self::parseCount($usage['output_tokens_total'] ?? NULL);
    $reasoning = self::parseCount($usage['output_tokens_reasoning'] ?? NULL);

    if (self::countInvalid($usage, 'input_tokens_total', $inputTotal, TRUE)
      || self::countInvalid($usage, 'output_tokens_total', $outputTotal, TRUE)
      || self::countInvalid($usage, 'input_tokens_cache_read', $cacheRead)
      || self::countInvalid($usage, 'input_tokens_cache_write', $cacheWrite)
      || self::countInvalid($usage, 'output_tokens_reasoning', $reasoning)
      || ($cacheRead !== NULL && $cacheRead > $inputTotal)
      || ($reasoning !== NULL && $reasoning > $outputTotal)) {
      return ['valuation_state' => self::STATE_UNPRICED, 'price_version_id' => $version['id'],
        'currency' => $currency, 'unpriced_reason' => 'invalid_usage'] + $unpriced;
    }

    // Non-cache input = total input - cache-read input (cache-read is a subset).
    $uncachedInput = $inputTotal - ($cacheRead ?? 0);
    try {
      $inputNumerator = $this->costNumerator($rate['per_million_input'], $uncachedInput);
      $cacheReadNumerator = $this->costNumerator($rate['per_million_cache_read'], $cacheRead ?? 0);
      $cacheWriteNumerator = $this->costNumerator($rate['per_million_cache_write'], $cacheWrite ?? 0);
      $outputNumerator = $this->costNumerator($rate['per_million_output'], $outputTotal);
      $totalNumerator = self::safeAdd($inputNumerator, $cacheReadNumerator);
      $totalNumerator = self::safeAdd($totalNumerator, $cacheWriteNumerator);
      $totalNumerator = self::safeAdd($totalNumerator, $outputNumerator);
      $inputCost = $this->roundNumerator($inputNumerator, $version['rounding_policy']);
      $cacheReadCost = $this->roundNumerator($cacheReadNumerator, $version['rounding_policy']);
      $cacheWriteCost = $this->roundNumerator($cacheWriteNumerator, $version['rounding_policy']);
      $outputCost = $this->roundNumerator($outputNumerator, $version['rounding_policy']);
      // The total is authoritative: all components are accumulated before one
      // final rounding, as required by the billing contract.
      $total = $this->roundNumerator($totalNumerator, $version['rounding_policy']);
    }
    catch (PriceBookException $e) {
      if ($e->priceCode === 'cost_overflow') {
        return ['valuation_state' => self::STATE_UNPRICED, 'price_version_id' => $version['id'],
          'currency' => $currency, 'unpriced_reason' => 'cost_overflow'] + $unpriced;
      }
      throw $e;
    }

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
    return $this->roundNumerator($this->costNumerator($perMillion, $tokens), $roundingPolicy);
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
      $max = (string) PHP_INT_MAX;
      $normalized = ltrim($value, '0') ?: '0';
      if (strlen($normalized) > strlen($max)
        || (strlen($normalized) === strlen($max) && strcmp($normalized, $max) > 0)) {
        return NULL;
      }
      return (int) $normalized;
    }
    return NULL;
  }

  private static function countInvalid(array $usage, string $key, ?int $parsed, bool $required = FALSE): bool {
    $raw = $usage[$key] ?? NULL;
    return ($required && $raw === NULL) || ($raw !== NULL && $parsed === NULL);
  }

  private static function safeAdd(int $left, int $right): int {
    if ($right > PHP_INT_MAX - $left) {
      throw new PriceBookException('cost_overflow', 'computed cost exceeds the supported integer range');
    }
    return $left + $right;
  }

  private static function costNumerator(int $perMillion, int $tokens): int {
    if ($perMillion < 0 || $tokens < 0) {
      throw new PriceBookException('cost_overflow', 'cost inputs must be non-negative');
    }
    if ($perMillion !== 0 && $tokens > intdiv(PHP_INT_MAX, $perMillion)) {
      throw new PriceBookException('cost_overflow', 'computed cost exceeds the supported integer range');
    }
    return $perMillion * $tokens;
  }

  private static function roundNumerator(int $numerator, string $roundingPolicy): int {
    $whole = intdiv($numerator, self::MILLION);
    $remainder = $numerator % self::MILLION;
    if ($roundingPolicy === PriceBookService::ROUNDING_HALF_UP
      && $remainder >= intdiv(self::MILLION, 2)) {
      if ($whole === PHP_INT_MAX) {
        throw new PriceBookException('cost_overflow', 'rounded cost exceeds the supported integer range');
      }
      $whole++;
    }
    return $whole;
  }

  private static function sameCostEntry(array $existing, array $expected): bool {
    foreach (['site_id', 'attempt_id', 'observation_revision', 'source_kind',
      'price_version_id', 'currency', 'input_cost_micros', 'cache_read_cost_micros',
      'cache_write_cost_micros', 'output_cost_micros', 'total_cost_micros',
      'valuation_state', 'unpriced_reason'] as $field) {
      $left = $existing[$field] === NULL ? NULL : (string) $existing[$field];
      $right = $expected[$field] === NULL ? NULL : (string) $expected[$field];
      if ($left !== $right) {
        return FALSE;
      }
    }
    return TRUE;
  }

}
