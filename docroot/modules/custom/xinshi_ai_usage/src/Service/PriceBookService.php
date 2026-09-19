<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_usage\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\IntegrityConstraintViolationException;

/**
 * Loads the active purchasing price book for a site + book kind.
 *
 * A price book version is active when its status is 'active', effective_from
 * is <= now and effective_to is either NULL or in the future. At most one
 * version per (site, kind) is active at the same time; activating a new
 * version closes the previous one atomically.
 *
 * Rates are stored as JSON with this shape for book_kind = 'supplier_chat':
 * @code
 * {
 *   "accounts": {
 *     "xinshi": {
 *       "models": {
 *         "deepseek-v4-pro": {
 *           "per_million_input": 2000000,
 *           "per_million_cache_read": 200000,
 *           "per_million_cache_write": 1000000,
 *           "per_million_output": 8000000
 *         }
 *       },
 *       "fallback_model": null
 *     }
 *   },
 *   "default_account": "xinshi"
 * }
 * @endcode
 *
 * Amounts are in micros of the version's currency (1 currency unit = 1,000,000
 * micros). Unknown models are rated as unpriced; they are never silently
 * treated as free.
 */
final class PriceBookService {

  public const TABLE = 'ai_price_book_version';
  public const KIND_SUPPLIER_CHAT = 'supplier_chat';
  public const STATUS_DRAFT = 'draft';
  public const STATUS_ACTIVE = 'active';
  public const ROUNDING_HALF_UP = 'half_up';

  public function __construct(
    private readonly Connection $database,
  ) {}

  /**
   * Returns the active version row for the given site and kind, or NULL.
   *
   * @param string $siteId
   *   The logical site id the price book belongs to.
   * @param string $kind
   *   The price book kind, e.g. self::KIND_SUPPLIER_CHAT.
   * @param int|null $asOfMs
   *   Milliseconds since epoch; defaults to current request time.
   *
   * @return array{id:int,version:string,currency:string,rates_json:string,rounding_policy:string,effective_from:int,effective_to:int|null}|null
   */
  public function loadActive(string $siteId, string $kind, ?int $asOfMs = NULL): ?array {
    $now = $asOfMs ?? (int) (microtime(TRUE) * 1000);
    $row = $this->database->select(self::TABLE, 'p')
      ->fields('p', ['id', 'version', 'currency', 'rates_json', 'rounding_policy',
        'effective_from', 'effective_to'])
      ->condition('p.site_id', $siteId)
      ->condition('p.book_kind', $kind)
      ->condition('p.status', self::STATUS_ACTIVE)
      ->condition('p.effective_from', $now, '<=')
      ->where('(p.effective_to IS NULL OR p.effective_to > :now)', [':now' => $now])
      ->orderBy('p.effective_from', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if ($row === FALSE) {
      return NULL;
    }
    // Cast integer columns so callers do not get string values from SQLite.
    return [
      'id' => (int) $row['id'],
      'version' => (string) $row['version'],
      'currency' => (string) $row['currency'],
      'rates_json' => (string) $row['rates_json'],
      'rounding_policy' => (string) $row['rounding_policy'],
      'effective_from' => (int) $row['effective_from'],
      'effective_to' => $row['effective_to'] === NULL ? NULL : (int) $row['effective_to'],
    ];
  }

  /**
   * Parses the rates JSON of a version into a structured array.
   *
   * Throws on malformed JSON or missing required top-level keys, so a bad
   * admin save cannot silently produce zero-cost ratings.
   *
   * @param string $ratesJson
   *   The stored rates_json string.
   *
   * @return array{accounts:array<string,array{models:array<string,array{per_million_input:int,per_million_cache_read:int,per_million_cache_write:int,per_million_output:int}>}>}
   *
   * @throws \Drupal\xinshi_ai_usage\Service\PriceBookException
   */
  public function parseRates(string $ratesJson): array {
    $decoded = json_decode($ratesJson, TRUE);
    if (!is_array($decoded)) {
      throw new PriceBookException('invalid_rates', 'price book rates_json is not valid JSON');
    }
    $accounts = $decoded['accounts'] ?? NULL;
    if (!is_array($accounts)) {
      throw new PriceBookException('invalid_rates', 'price book rates must contain an accounts map');
    }
    $cleanAccounts = [];
    foreach ($accounts as $accountId => $account) {
      if (!is_string($accountId) || $accountId === '' || !is_array($account)) {
        throw new PriceBookException('invalid_rates', 'each price book account must be a named object');
      }
      $models = $account['models'] ?? NULL;
      if (!is_array($models)) {
        throw new PriceBookException('invalid_rates', 'each price book account must have a models map');
      }
      $cleanModels = [];
      foreach ($models as $modelId => $rates) {
        if (!is_string($modelId) || $modelId === '' || !is_array($rates)) {
          throw new PriceBookException('invalid_rates', 'each model rate entry must be a named object');
        }
        $cleanModels[$modelId] = [
          'per_million_input' => self::asMicros($rates['per_million_input'] ?? 0),
          'per_million_cache_read' => self::asMicros($rates['per_million_cache_read'] ?? 0),
          'per_million_cache_write' => self::asMicros($rates['per_million_cache_write'] ?? 0),
          'per_million_output' => self::asMicros($rates['per_million_output'] ?? 0),
        ];
      }
      $cleanAccounts[$accountId] = ['models' => $cleanModels];
    }
    return ['accounts' => $cleanAccounts];
  }

  /**
   * Returns a model rate entry or NULL when the model or account is unknown.
   *
   * @param array $rates
   *   The full rates structure from parseRates().
   * @param string $accountRef
   *   The provider account reference, e.g. 'xinshi' or 'aliyun'.
   * @param string $modelId
   *   The requested model identifier.
   *
   * @return array{per_million_input:int,per_million_cache_read:int,per_million_cache_write:int,per_million_output:int}|null
   */
  public function findModelRate(array $rates, string $accountRef, string $modelId): ?array {
    return $rates['accounts'][$accountRef]['models'][$modelId] ?? NULL;
  }

  /**
   * Saves a new draft version; does not activate it.
   *
   * @param string $siteId
   *   The logical site id.
   * @param string $kind
   *   The price book kind.
   * @param string $version
   *   Human-readable version label; must be unique per site + kind.
   * @param string $currency
   *   ISO 4217 code.
   * @param string $ratesJson
   *   Validated rates JSON.
   * @param int $effectiveFromMs
   *   Milliseconds since epoch.
   *
   * @return int
   *   The new version id.
   *
   * @throws \Drupal\xinshi_ai_usage\Service\PriceBookException
   *   When the version label already exists.
   */
  public function createDraft(string $siteId, string $kind, string $version, string $currency,
    string $ratesJson, int $effectiveFromMs): int {
    try {
      $id = $this->database->insert(self::TABLE)->fields([
        'site_id' => $siteId,
        'book_kind' => $kind,
        'version' => $version,
        'currency' => $currency,
        'rates_json' => $ratesJson,
        'rounding_policy' => self::ROUNDING_HALF_UP,
        'status' => self::STATUS_DRAFT,
        'effective_from' => $effectiveFromMs,
        'effective_to' => NULL,
        'created_at' => (int) (microtime(TRUE) * 1000),
      ])->execute();
      return (int) $id;
    }
    catch (IntegrityConstraintViolationException $e) {
      throw new PriceBookException('version_exists',
        "price book version {$version} already exists for site {$siteId} / {$kind}", $e);
    }
  }

  /**
   * Activates the given version and closes the previous active one.
   *
   * Done in a transaction so there is never both zero and two active versions.
   *
   * @param int $versionId
   *   The draft version id to activate.
   * @param string $siteId
   *   The expected site id; used as a safety check.
   * @param string $kind
   *   The expected kind; used as a safety check.
   *
   * @throws \Drupal\xinshi_ai_usage\Service\PriceBookException
   */
  public function activate(int $versionId, string $siteId, string $kind): void {
    $tx = $this->database->startTransaction();
    try {
      $current = $this->loadActive($siteId, $kind);
      if ($current !== NULL) {
        $this->database->update(self::TABLE)
          ->fields(['effective_to' => (int) (microtime(TRUE) * 1000)])
          ->condition('id', $current['id'])
          ->execute();
      }
      $updated = $this->database->update(self::TABLE)
        ->fields(['status' => self::STATUS_ACTIVE])
        ->condition('id', $versionId)
        ->condition('site_id', $siteId)
        ->condition('book_kind', $kind)
        ->condition('status', self::STATUS_DRAFT)
        ->execute();
      if ($updated === 0) {
        throw new PriceBookException('version_not_found',
          "draft price book version {$versionId} not found for site {$siteId} / {$kind}");
      }
    }
    catch (\Throwable $e) {
      $tx->rollBack();
      if ($e instanceof PriceBookException) {
        throw $e;
      }
      throw new PriceBookException('activate_failed', 'failed to activate price book version: ' . $e->getMessage(), $e);
    }
  }

  /**
   * Casts a stored rate value to a non-negative micros integer.
   *
   * Accepts int and numeric strings; rejects negatives and non-numeric values.
   */
  private static function asMicros(mixed $value): int {
    if (is_int($value) && $value >= 0) {
      return $value;
    }
    if (is_string($value) && preg_match('/^\d+$/', $value)) {
      return (int) $value;
    }
    throw new PriceBookException('invalid_rate', 'price book rate values must be non-negative integers');
  }

}
