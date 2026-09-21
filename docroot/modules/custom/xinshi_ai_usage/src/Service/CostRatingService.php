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
 * - Image attempts (normalizer `images-*`) are rated from the supplier_image
 *   book: per_image * images_generated, plus token rates when the model bills
 *   tokens on top. A model that reports tokens without token rates in the book
 *   is unpriced ("token_rate_missing"), never rated by images alone.
 * - Calls made with the customer's own key are never platform cost: unpriced
 *   with reason "customer_key" regardless of any book.
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
  /** Normalizer versions of image attempts; see ImageUsageNormalizer::VERSION. */
  public const IMAGE_NORMALIZER_PREFIX = 'images-';
  /** The account_ref of calls made with the user's own key; see the payer rules. */
  public const CUSTOMER_KEY_ACCOUNT = 'customer_key';
  private const COST_COLUMNS = ['input_cost_micros', 'cache_read_cost_micros', 'cache_write_cost_micros',
    'output_cost_micros', 'image_cost_micros', 'total_cost_micros'];

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
      'image_cost_micros' => $result['image_cost_micros'],
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
   * @return array{valuation_state:string,price_version_id:int|null,currency:string|null,input_cost_micros:int|null,cache_read_cost_micros:int|null,cache_write_cost_micros:int|null,output_cost_micros:int|null,image_cost_micros:int|null,total_cost_micros:int|null,unpriced_reason:string|null}
   */
  public function computeRate(string $siteId, array $provider, ?array $usage, int $occurredAtMs): array {
    $unpriced = array_fill_keys(self::COST_COLUMNS, NULL);
    $accountRef = (string) ($provider['account_ref'] ?? '');
    // The user's own key paid for this call; whatever the books say, it is not
    // a platform purchase and reports show it as customer-paid.
    if ($accountRef === self::CUSTOMER_KEY_ACCOUNT) {
      return ['valuation_state' => self::STATE_UNPRICED, 'price_version_id' => NULL,
        'currency' => NULL, 'unpriced_reason' => 'customer_key'] + $unpriced;
    }
    $isImage = str_starts_with((string) ($usage['normalizer_version'] ?? ''), self::IMAGE_NORMALIZER_PREFIX);
    $kind = $isImage ? PriceBookService::KIND_SUPPLIER_IMAGE : PriceBookService::KIND_SUPPLIER_CHAT;
    $version = $this->priceBook->loadActive($siteId, $kind, $occurredAtMs);
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
    $modelId = (string) ($provider['resolved_model'] ?? '');
    if ($modelId === '') {
      $modelId = (string) ($provider['requested_model'] ?? '');
    }
    try {
      $rates = $this->priceBook->parseRates($version['rates_json'], $kind);
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
    $base = ['price_version_id' => $version['id'], 'currency' => $currency];
    try {
      $costs = $isImage
        ? $this->imageCosts($rate, $usage, $version['rounding_policy'])
        : $this->chatCosts($rate, $usage, $version['rounding_policy']);
    }
    catch (PriceBookException $e) {
      if (in_array($e->priceCode, ['cost_overflow', 'invalid_usage', 'token_rate_missing'], TRUE)) {
        return ['valuation_state' => self::STATE_UNPRICED, 'unpriced_reason' => $e->priceCode]
          + $base + $unpriced;
      }
      throw $e;
    }
    // Array union keeps the left operand: the computed components must come
    // before the NULL placeholders of the columns this kind does not use.
    return ['valuation_state' => self::STATE_RATED_ESTIMATE, 'unpriced_reason' => NULL] + $base
      + $costs + $unpriced;
  }

  /**
   * Token-based cost of a chat attempt; components rounded separately, the total once.
   *
   * @return array<string,int>
   *
   * @throws \Drupal\xinshi_ai_usage\Service\PriceBookException
   *   `invalid_usage` for counts that cannot be parsed or contradict their
   *   inclusion relations, `cost_overflow` beyond the integer range.
   */
  private function chatCosts(array $rate, array $usage, string $roundingPolicy): array {
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
      throw new PriceBookException('invalid_usage', 'token counts are missing or contradict each other');
    }

    // Non-cache input = total input - cache-read input (cache-read is a subset).
    $uncachedInput = $inputTotal - ($cacheRead ?? 0);
    $inputNumerator = self::costNumerator($rate['per_million_input'], $uncachedInput);
    $cacheReadNumerator = self::costNumerator($rate['per_million_cache_read'], $cacheRead ?? 0);
    $cacheWriteNumerator = self::costNumerator($rate['per_million_cache_write'], $cacheWrite ?? 0);
    $outputNumerator = self::costNumerator($rate['per_million_output'], $outputTotal);
    $totalNumerator = self::safeAdd($inputNumerator, $cacheReadNumerator);
    $totalNumerator = self::safeAdd($totalNumerator, $cacheWriteNumerator);
    $totalNumerator = self::safeAdd($totalNumerator, $outputNumerator);
    return [
      'input_cost_micros' => self::roundNumerator($inputNumerator, $roundingPolicy),
      'cache_read_cost_micros' => self::roundNumerator($cacheReadNumerator, $roundingPolicy),
      'cache_write_cost_micros' => self::roundNumerator($cacheWriteNumerator, $roundingPolicy),
      'output_cost_micros' => self::roundNumerator($outputNumerator, $roundingPolicy),
      // The total is authoritative: all components are accumulated before one
      // final rounding, as required by the billing contract.
      'total_cost_micros' => self::roundNumerator($totalNumerator, $roundingPolicy),
    ];
  }

  /**
   * Per-image cost of an image attempt, plus token cost for models that bill tokens too.
   *
   * @return array<string,int>
   *
   * @throws \Drupal\xinshi_ai_usage\Service\PriceBookException
   *   `invalid_usage` without a usable image count, `token_rate_missing` when
   *   the supplier reported tokens the book has no rate for, `cost_overflow`.
   */
  private function imageCosts(array $rate, array $usage, string $roundingPolicy): array {
    $images = self::parseCount($usage['images_generated'] ?? NULL);
    if ($images === NULL) {
      throw new PriceBookException('invalid_usage', 'image attempt without a usable images_generated count');
    }
    $inputTotal = self::parseCount($usage['input_tokens_total'] ?? NULL);
    $outputTotal = self::parseCount($usage['output_tokens_total'] ?? NULL);
    if (self::countInvalid($usage, 'input_tokens_total', $inputTotal)
      || self::countInvalid($usage, 'output_tokens_total', $outputTotal)) {
      throw new PriceBookException('invalid_usage', 'token counts of the image attempt cannot be parsed');
    }
    // Tokens the supplier reported are billable; a book that carries no token
    // rate for this model cannot price them, and pricing the images alone
    // would understate the cost.
    if (($inputTotal !== NULL && $rate['per_million_input'] === NULL)
      || ($outputTotal !== NULL && $rate['per_million_output'] === NULL)) {
      throw new PriceBookException('token_rate_missing', 'the image book has no token rate for a model that reports tokens');
    }
    // Images are whole units: per_image is already micros per image.
    if ($rate['per_image'] !== 0 && $images > intdiv(PHP_INT_MAX, $rate['per_image'])) {
      throw new PriceBookException('cost_overflow', 'computed cost exceeds the supported integer range');
    }
    $imageCost = $rate['per_image'] * $images;
    $inputNumerator = $inputTotal === NULL ? 0 : self::costNumerator((int) $rate['per_million_input'], $inputTotal);
    $outputNumerator = $outputTotal === NULL ? 0 : self::costNumerator((int) $rate['per_million_output'], $outputTotal);
    $tokenNumerator = self::safeAdd($inputNumerator, $outputNumerator);
    $totalNumerator = self::safeAdd(self::costNumerator(self::MILLION, $imageCost), $tokenNumerator);
    return [
      'input_cost_micros' => self::roundNumerator($inputNumerator, $roundingPolicy),
      'output_cost_micros' => self::roundNumerator($outputNumerator, $roundingPolicy),
      'image_cost_micros' => $imageCost,
      'total_cost_micros' => self::roundNumerator($totalNumerator, $roundingPolicy),
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
      'price_version_id', 'currency', ...self::COST_COLUMNS,
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
