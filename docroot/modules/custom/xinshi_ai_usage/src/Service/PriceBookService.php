<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_usage\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\IntegrityConstraintViolationException;
use Drupal\Component\Datetime\TimeInterface;

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
 *
 * book_kind = 'supplier_image' (UB2 image price book) uses the same accounts
 * / models nesting with per-image rates; token rates are optional and only
 * needed for models that bill tokens on top (GPT image models):
 * @code
 * {
 *   "accounts": {
 *     "xinshi": {
 *       "models": {
 *         "qwen-image-plus": { "per_image": 200000 },
 *         "gpt-image-2": { "per_image": 0, "per_million_input": 5000000, "per_million_output": 40000000 }
 *       }
 *     }
 *   }
 * }
 * @endcode
 */
final class PriceBookService {

  public const TABLE = 'ai_price_book_version';
  public const KIND_SUPPLIER_CHAT = 'supplier_chat';
  public const KIND_SUPPLIER_IMAGE = 'supplier_image';
  public const KINDS = [self::KIND_SUPPLIER_CHAT, self::KIND_SUPPLIER_IMAGE];
  public const STATUS_DRAFT = 'draft';
  public const STATUS_ACTIVE = 'active';
  public const ROUNDING_HALF_UP = 'half_up';
  private const CHAT_RATE_KEYS = ['per_million_input', 'per_million_cache_read', 'per_million_cache_write',
    'per_million_output'];
  private const IMAGE_TOKEN_RATE_KEYS = ['per_million_input', 'per_million_output'];

  public function __construct(
    private readonly Connection $database,
    private readonly TimeInterface $time,
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
    $now = $asOfMs ?? $this->nowMs();
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
   * Recent versions of a site and kind, newest first, for the admin listing.
   *
   * @return list<array{id:int,version:string,currency:string,status:string,source_ref:string|null,effective_from:int,effective_to:int|null,created_at:int,created_by:string|null}>
   */
  public function listVersions(string $siteId, string $kind, int $limit = 20): array {
    $rows = $this->database->select(self::TABLE, 'p')
      ->fields('p', ['id', 'version', 'currency', 'status', 'source_ref', 'effective_from',
        'effective_to', 'created_at', 'created_by'])
      ->condition('p.site_id', $siteId)
      ->condition('p.book_kind', $kind)
      ->orderBy('p.created_at', 'DESC')
      ->orderBy('p.id', 'DESC')
      ->range(0, max(1, $limit))
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
    return array_map(static fn(array $row): array => [
      'id' => (int) $row['id'],
      'version' => (string) $row['version'],
      'currency' => (string) $row['currency'],
      'status' => (string) $row['status'],
      'source_ref' => $row['source_ref'] === NULL ? NULL : (string) $row['source_ref'],
      'effective_from' => (int) $row['effective_from'],
      'effective_to' => $row['effective_to'] === NULL ? NULL : (int) $row['effective_to'],
      'created_at' => (int) $row['created_at'],
      'created_by' => $row['created_by'] === NULL ? NULL : (string) $row['created_by'],
    ], $rows);
  }

  /**
   * Parses the rates JSON of a version into a structured array.
   *
   * Throws on malformed JSON or missing required keys, so a bad admin save
   * cannot silently produce zero-cost ratings. Chat models need all four
   * per-million rates; image models need `per_image` and may carry
   * `per_million_input` / `per_million_output` (NULL when absent, meaning the
   * model is not expected to report tokens).
   *
   * @param string $ratesJson
   *   The stored rates_json string.
   * @param string $kind
   *   The price book kind the rates belong to.
   *
   * @return array{accounts:array<string,array{models:array<string,array<string,int|null>>}>}
   *
   * @throws \Drupal\xinshi_ai_usage\Service\PriceBookException
   */
  public function parseRates(string $ratesJson, string $kind = self::KIND_SUPPLIER_CHAT): array {
    if (!in_array($kind, self::KINDS, TRUE)) {
      throw new PriceBookException('invalid_kind', "unsupported price book kind {$kind}");
    }
    $decoded = json_decode($ratesJson, TRUE);
    if (!is_array($decoded)) {
      throw new PriceBookException('invalid_rates', 'price book rates_json is not valid JSON');
    }
    $accounts = $decoded['accounts'] ?? NULL;
    if (!is_array($accounts)) {
      throw new PriceBookException('invalid_rates', 'price book rates must contain an accounts map');
    }
    $cleanAccounts = [];
    if ($accounts === []) {
      throw new PriceBookException('invalid_rates', 'price book must contain at least one account');
    }
    foreach ($accounts as $accountId => $account) {
      if (!is_string($accountId) || $accountId === '' || !is_array($account)) {
        throw new PriceBookException('invalid_rates', 'each price book account must be a named object');
      }
      $models = $account['models'] ?? NULL;
      if (!is_array($models) || $models === []) {
        throw new PriceBookException('invalid_rates', 'each price book account must have a models map');
      }
      $cleanModels = [];
      foreach ($models as $modelId => $rates) {
        if (!is_string($modelId) || $modelId === '' || !is_array($rates)) {
          throw new PriceBookException('invalid_rates', 'each model rate entry must be a named object');
        }
        $cleanModels[$modelId] = $kind === self::KIND_SUPPLIER_IMAGE
          ? self::imageModelRate($modelId, $rates)
          : self::chatModelRate($modelId, $rates);
      }
      $cleanAccounts[$accountId] = ['models' => $cleanModels];
    }
    return ['accounts' => $cleanAccounts];
  }

  /**
   * @return array{per_million_input:int,per_million_cache_read:int,per_million_cache_write:int,per_million_output:int}
   */
  private static function chatModelRate(string $modelId, array $rates): array {
    $clean = [];
    foreach (self::CHAT_RATE_KEYS as $rateKey) {
      if (!array_key_exists($rateKey, $rates)) {
        throw new PriceBookException('invalid_rates', "model {$modelId} is missing {$rateKey}");
      }
      $clean[$rateKey] = self::asMicros($rates[$rateKey]);
    }
    return $clean;
  }

  /**
   * @return array{per_image:int,per_million_input:int|null,per_million_output:int|null}
   */
  private static function imageModelRate(string $modelId, array $rates): array {
    if (!array_key_exists('per_image', $rates)) {
      throw new PriceBookException('invalid_rates', "model {$modelId} is missing per_image");
    }
    $clean = ['per_image' => self::asMicros($rates['per_image'])];
    foreach (self::IMAGE_TOKEN_RATE_KEYS as $rateKey) {
      $clean[$rateKey] = array_key_exists($rateKey, $rates) && $rates[$rateKey] !== NULL
        ? self::asMicros($rates[$rateKey]) : NULL;
    }
    return $clean;
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
   * @return array<string,int|null>|null
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
   *   Milliseconds since epoch; activation never moves this earlier.
   * @param string|null $sourceRef
   *   Optional supplier quote, invoice or contract reference for audit.
   * @param string|null $createdBy
   *   Optional identifier of the admin who created the draft.
   *
   * @return int
   *   The new version id.
   *
   * @throws \Drupal\xinshi_ai_usage\Service\PriceBookException
   *   When the version label already exists.
   */
  public function createDraft(string $siteId, string $kind, string $version, string $currency,
    string $ratesJson, int $effectiveFromMs, ?string $sourceRef = NULL,
    ?string $createdBy = NULL): int {
    $version = trim($version);
    $currency = strtoupper(trim($currency));
    $sourceRef = $sourceRef === NULL ? NULL : trim($sourceRef);
    if ($sourceRef === '') {
      $sourceRef = NULL;
    }
    if ($sourceRef !== NULL && strlen($sourceRef) > 255) {
      throw new PriceBookException('invalid_source_ref', 'source_ref must be at most 255 characters');
    }
    if ($version === '' || strlen($version) > 64) {
      throw new PriceBookException('invalid_version', 'price book version must be 1-64 characters');
    }
    if (!preg_match('/^[A-Z]{3}$/', $currency)) {
      throw new PriceBookException('invalid_currency', 'currency must be a three-letter ISO 4217 code');
    }
    if ($effectiveFromMs < 0) {
      throw new PriceBookException('invalid_effective_from', 'effective_from must be non-negative');
    }
    // Validate at the service boundary as well as in the form. Other callers
    // must not be able to create a draft that can later be activated blindly.
    $this->parseRates($ratesJson, $kind);
    try {
      $id = $this->database->insert(self::TABLE)->fields([
        'site_id' => $siteId,
        'book_kind' => $kind,
        'version' => $version,
        'currency' => $currency,
        'rates_json' => $ratesJson,
        'rounding_policy' => self::ROUNDING_HALF_UP,
        'status' => self::STATUS_DRAFT,
        'source_ref' => $sourceRef,
        'effective_from' => $effectiveFromMs,
        'effective_to' => NULL,
        'created_at' => $this->nowMs(),
        'created_by' => $createdBy === NULL || $createdBy === '' ? NULL : substr($createdBy, 0, 128),
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
      $target = $this->database->select(self::TABLE, 'p')
        ->fields('p', ['id', 'effective_from', 'effective_to', 'rates_json', 'currency'])
        ->condition('p.id', $versionId)
        ->condition('p.site_id', $siteId)
        ->condition('p.book_kind', $kind)
        ->condition('p.status', self::STATUS_DRAFT)
        ->forUpdate()
        ->execute()
        ->fetchAssoc();
      if ($target === FALSE) {
        throw new PriceBookException('version_not_found',
          "draft price book version {$versionId} not found for site {$siteId} / {$kind}");
      }

      $this->parseRates((string) $target['rates_json'], $kind);
      $now = $this->nowMs();
      // A draft created in the past must not be applied retroactively. A
      // future effective date remains scheduled, while the previous active
      // interval ends exactly at that date.
      $effectiveFrom = max((int) $target['effective_from'], $now);
      $active = $this->database->select(self::TABLE, 'p')
        ->fields('p', ['id', 'effective_from', 'effective_to'])
        ->condition('p.site_id', $siteId)
        ->condition('p.book_kind', $kind)
        ->condition('p.status', self::STATUS_ACTIVE)
        ->condition('p.id', $versionId, '<>')
        ->forUpdate()
        ->execute();
      foreach ($active as $row) {
        $rowFrom = (int) $row->effective_from;
        $rowTo = $row->effective_to === NULL ? NULL : (int) $row->effective_to;
        if ($rowFrom >= $effectiveFrom) {
          throw new PriceBookException('overlapping_version',
            'an active price book is already scheduled at or after the requested effective time');
        }
        if ($rowTo === NULL || $rowTo > $effectiveFrom) {
          $this->database->update(self::TABLE)
            ->fields(['effective_to' => $effectiveFrom])
            ->condition('id', (int) $row->id)
            ->execute();
        }
      }

      $this->database->update(self::TABLE)
        ->fields(['status' => self::STATUS_ACTIVE, 'effective_from' => $effectiveFrom,
          'effective_to' => NULL])
        ->condition('id', $versionId)
        ->condition('site_id', $siteId)
        ->condition('book_kind', $kind)
        ->condition('status', self::STATUS_DRAFT)
        ->execute();
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
      $max = (string) PHP_INT_MAX;
      $normalized = ltrim($value, '0') ?: '0';
      if (strlen($normalized) > strlen($max)
        || (strlen($normalized) === strlen($max) && strcmp($normalized, $max) > 0)) {
        throw new PriceBookException('invalid_rate', 'price book rate exceeds the supported integer range');
      }
      return (int) $normalized;
    }
    throw new PriceBookException('invalid_rate', 'price book rate values must be non-negative integers');
  }

  private function nowMs(): int {
    return (int) round($this->time->getCurrentMicroTime() * 1000);
  }

}
