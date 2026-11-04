<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_usage\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Site\Settings;

/** Previews and records missing historical supplier costs without changing usage or credits. */
final class CostBackfillService {

  public const LIMIT = 200;
  public const PREVIEW_TTL_MS = 900_000;
  public const TABLE = 'ai_usage_cost_backfill';

  public function __construct(
    private readonly Connection $database,
    private readonly PriceBookService $priceBook,
    private readonly CostRatingService $costRating,
    private readonly UsageProjectionService $projection,
    private readonly TimeInterface $time,
    private readonly AccountInterface $account,
    private readonly Settings $settings,
    private readonly ProducerVault $vault,
    private readonly LockBackendInterface $lock,
  ) {}

  /** Loads a published historical interval after checking current permissions and site scope. */
  public function version(int $versionId, bool $lock = FALSE): array {
    if (!$this->account->isAuthenticated()) {
      throw new PriceBookException('access_denied', 'authentication is required');
    }
    foreach (['administer xinshi ai pricing', 'view site ai usage', 'view ai supplier costs'] as $permission) {
      if (!$this->account->hasPermission($permission)) {
        throw new PriceBookException('access_denied', 'historical cost backfill is not permitted');
      }
    }
    $version = $this->priceBook->loadVersion($versionId, $lock);
    if ($version === NULL
      || !in_array($version['site_id'], RegisteredSites::list($this->settings, $this->vault), TRUE)) {
      throw new PriceBookException('version_not_found', 'the price book is outside this installation');
    }
    if ($version['status'] !== PriceBookService::STATUS_ACTIVE || $version['effective_to'] === NULL
      || (int) $version['effective_to'] > $this->nowMs()) {
      throw new PriceBookException('not_historical', 'select a published interval that has already ended');
    }
    return $version;
  }

  /** Produces one bounded, actor-bound preview; no costs or projections are written. */
  public function preview(int $versionId, int $afterId = 0): array {
    $version = $this->version($versionId);
    $rows = $this->candidates($version)->condition('a.id', max(0, $afterId), '>')
      ->range(0, self::LIMIT + 1)->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $more = count($rows) > self::LIMIT;
    $rows = array_slice($rows, 0, self::LIMIT);
    $entries = $skipped = [];
    $total = '0';
    foreach ($rows as $row) {
      $cost = $this->calculate($row);
      if ($cost['valuation_state'] !== CostRatingService::STATE_RATED_ESTIMATE
        || (int) $cost['price_version_id'] !== $versionId) {
        $reason = $cost['unpriced_reason'] ?? 'price_book_mismatch';
        $skipped[$reason] = ($skipped[$reason] ?? 0) + 1;
        continue;
      }
      $entries[] = ['id' => (int) $row['id'],
        'original_cost_entry_id' => (int) $row['original_cost_entry_id'],
        'attempt_id' => $row['attempt_id'], 'revision' => (int) $row['usage_revision'],
        'model' => $row['model_id'], 'occurred_at' => (int) $row['occurred_at'],
        'amount_micros' => (string) $cost['total_cost_micros'],
        'hash' => self::hash([$row, $cost])];
      $total = UsageCostSummary::addDecimal($total, (string) $cost['total_cost_micros']);
    }
    $preview = ['version_id' => $versionId, 'version_hash' => self::hash($version),
      'actor_id' => (string) $this->account->id(), 'created_at' => $this->nowMs(),
      'entries' => $entries, 'amount_micros' => $total, 'currency' => $version['currency'],
      'skipped' => $skipped, 'next_after_id' => $more ? (int) end($rows)['id'] : NULL];
    return $preview + ['hash' => self::hash($preview)];
  }

  /** Rechecks the exact preview, then appends costs, receipts and projection updates atomically. */
  public function execute(array $preview, string $reason): array {
    $hash = $preview['hash'] ?? '';
    unset($preview['hash']);
    if (!is_string($hash) || !hash_equals(self::hash($preview), $hash)
      || ($preview['actor_id'] ?? NULL) !== (string) $this->account->id()
      || !is_int($preview['version_id'] ?? NULL) || $preview['version_id'] < 1
      || !is_string($preview['version_hash'] ?? NULL)
      || !is_int($preview['created_at'] ?? NULL)
      || !is_string($preview['amount_micros'] ?? NULL)
      || !preg_match('/^(0|[1-9][0-9]*)$/', $preview['amount_micros'])
      || !is_string($preview['currency'] ?? NULL) || !is_array($preview['entries'] ?? NULL)
      || !$preview['entries'] || count($preview['entries']) > self::LIMIT) {
      throw new PriceBookException('invalid_preview', 'the cost preview is invalid');
    }
    $reason = trim($reason);
    if ($reason === '' || strlen($reason) > 255) {
      throw new PriceBookException('invalid_reason', 'a reason of 1-255 bytes is required');
    }
    $expected = [];
    foreach ($preview['entries'] as $entry) {
      if (!is_array($entry) || !is_int($entry['id'] ?? NULL) || $entry['id'] < 1
        || !is_int($entry['original_cost_entry_id'] ?? NULL) || $entry['original_cost_entry_id'] < 1
        || !is_string($entry['hash'] ?? NULL) || isset($expected[$entry['id']])) {
        throw new PriceBookException('invalid_preview', 'preview rows must have unique identifiers');
      }
      $expected[$entry['id']] = $entry;
    }
    $this->version($preview['version_id']);
    // Use the consumer lock so a rebuild or another rollup update cannot race this batch.
    if (!$this->lock->acquire(UsageProjectionService::LOCK_NAME, 300.0)) {
      throw new PriceBookException('projection_busy', 'usage processing is busy; retry this preview');
    }
    try {
      return $this->executeLocked($preview, $hash, $reason, $expected);
    }
    finally {
      $this->lock->release(UsageProjectionService::LOCK_NAME);
    }
  }

  private function executeLocked(array $preview, string $hash, string $reason, array $expected): array {
    $tx = $this->database->startTransaction();
    try {
      $version = $this->version($preview['version_id'], TRUE);
      $receipts = $this->database->select(self::TABLE, 'r')->fields('r')
        ->condition('r.site_id', $version['site_id'])->condition('r.preview_hash', $hash)
        ->execute()->fetchAll(\PDO::FETCH_ASSOC);
      if ($receipts !== []) {
        $originalIds = array_column($preview['entries'], 'original_cost_entry_id');
        $total = '0';
        foreach ($receipts as $receipt) {
          $saved = $this->database->select(CostRatingService::TABLE, 'c')->fields('c')
            ->condition('id', $receipt['cost_entry_id'])->execute()->fetchAssoc();
          if ($receipt['actor_user_id'] !== $preview['actor_id'] || $receipt['reason'] !== $reason
            || !in_array((int) $receipt['original_cost_entry_id'], $originalIds, TRUE)
            || (int) $receipt['price_version_id'] !== $preview['version_id'] || !$saved
            || $saved['source_kind'] !== CostRatingService::SOURCE_PRICE_BOOK_BACKFILL
            || $saved['valuation_state'] !== CostRatingService::STATE_RATED_ESTIMATE
            || (int) $saved['price_version_id'] !== $preview['version_id']
            || $saved['currency'] !== $preview['currency']) {
            throw new PriceBookException('invalid_preview', 'the saved receipt differs from this request');
          }
          $total = UsageCostSummary::addDecimal($total, (string) $saved['total_cost_micros']);
        }
        if (count($receipts) !== count($expected) || $total !== $preview['amount_micros']) {
          throw new PriceBookException('invalid_preview', 'the saved batch is incomplete');
        }
        return ['count' => count($receipts), 'amount_micros' => $total,
          'currency' => $preview['currency'], 'replayed' => TRUE];
      }
      if (!hash_equals(self::hash($version), $preview['version_hash'])
        || $preview['created_at'] > $this->nowMs()
        || $this->nowMs() - $preview['created_at'] > self::PREVIEW_TTL_MS) {
        throw new PriceBookException('stale_preview', 'the preview expired or its price book changed');
      }
      $rows = $this->candidates($version)->condition('a.id', array_keys($expected), 'IN')
        ->forUpdate()->execute()->fetchAll(\PDO::FETCH_ASSOC);
      if (count($rows) !== count($expected)) {
        throw new PriceBookException('stale_preview', 'the previewed calls changed; preview again');
      }
      $total = '0';
      foreach ($rows as $row) {
        $cost = $this->calculate($row);
        if ($cost['valuation_state'] !== CostRatingService::STATE_RATED_ESTIMATE
          || (int) $cost['price_version_id'] !== $preview['version_id']
          || !hash_equals($expected[(int) $row['id']]['hash'], self::hash([$row, $cost]))) {
          throw new PriceBookException('stale_preview', 'the previewed usage or valuation changed');
        }
        $total = UsageCostSummary::addDecimal($total, (string) $cost['total_cost_micros']);
      }
      if ($total !== $preview['amount_micros'] || $version['currency'] !== $preview['currency']) {
        throw new PriceBookException('stale_preview', 'the previewed cost total changed');
      }
      foreach ($rows as $row) {
        $this->costRating->rateAttempt($version['site_id'], $row['attempt_id'],
          (int) $row['usage_revision'], self::provider($row), self::usage($row),
          (int) $row['occurred_at'], CostRatingService::SOURCE_PRICE_BOOK_BACKFILL);
        $saved = $this->costRating->savedRate($version['site_id'], $row['attempt_id'],
          (int) $row['usage_revision']);
        if ($saved === NULL || $saved['source_kind'] !== CostRatingService::SOURCE_PRICE_BOOK_BACKFILL) {
          throw new PriceBookException('stale_preview', 'the current cost has another authoritative source');
        }
        $this->database->insert(self::TABLE)->fields(['site_id' => $version['site_id'],
          'price_version_id' => $preview['version_id'],
          'original_cost_entry_id' => (int) $row['original_cost_entry_id'],
          'cost_entry_id' => (int) $saved['id'], 'actor_user_id' => $preview['actor_id'],
          'reason' => $reason, 'preview_hash' => $hash, 'created_at' => $this->nowMs()])->execute();
      }
      $this->projection->refreshCosts($version['site_id'], $rows);
    }
    catch (\Throwable $e) {
      $tx->rollBack();
      throw $e;
    }
    return ['count' => count($rows), 'amount_micros' => $total,
      'currency' => $version['currency'], 'replayed' => FALSE];
  }

  private function candidates(array $version): \Drupal\Core\Database\Query\SelectInterface {
    $query = $this->database->select(UsageProjectionService::ATTEMPT_TABLE, 'a');
    $query->join(CostRatingService::TABLE, 'c',
      'c.site_id = a.site_id AND c.attempt_id = a.attempt_id '
      . 'AND c.observation_revision = a.usage_revision AND c.source_kind = :source',
      [':source' => CostRatingService::SOURCE_PRICE_BOOK]);
    $query->fields('a')->addField('c', 'id', 'original_cost_entry_id');
    $saved = $this->database->select(CostRatingService::TABLE, 'saved')->fields('saved', ['id'])
      ->where('saved.site_id = a.site_id AND saved.attempt_id = a.attempt_id '
        . 'AND saved.observation_revision = a.usage_revision')
      ->condition('saved.source_kind', [CostRatingService::SOURCE_PRICE_BOOK_BACKFILL,
        CostRatingService::SOURCE_SUPPLIER_RECONCILIATION], 'IN');
    $query->notExists($saved);
    $query->condition('a.site_id', $version['site_id'])
      ->condition('a.occurred_at', (int) $version['effective_from'], '>=')
      ->condition('a.occurred_at', (int) $version['effective_to'], '<')
      ->condition('a.cost_valuation_state', CostRatingService::STATE_UNPRICED)
      ->condition('a.cost_unpriced_reason', 'no_price_book')
      ->condition('c.valuation_state', CostRatingService::STATE_UNPRICED)
      ->condition('c.unpriced_reason', 'no_price_book')
      ->condition('a.dispatch_state', 'sent')->condition('a.usage_quality', 'reported')
      ->condition('a.payer', 'platform')->orderBy('a.id');
    if ($version['book_kind'] === PriceBookService::KIND_SUPPLIER_IMAGE) {
      $query->isNotNull('a.images_generated');
    }
    else {
      $query->isNull('a.images_generated');
    }
    $accounts = $this->priceBook->parseRates($version['rates_json'], $version['book_kind'])['accounts'];
    $matching = $query->orConditionGroup();
    foreach ($accounts as $accountRef => $account) {
      $matching->condition($query->andConditionGroup()
        ->condition('a.provider_account_ref', $accountRef)
        ->condition('a.model_id', array_keys($account['models']), 'IN'));
    }
    return $query->condition($matching);
  }

  private function calculate(array $row): array {
    return $this->costRating->computeRate($row['site_id'], self::provider($row),
      self::usage($row), (int) $row['occurred_at']);
  }

  private static function provider(array $row): array {
    return ['account_ref' => $row['provider_account_ref'],
      'requested_model' => $row['requested_model'], 'resolved_model' => $row['resolved_model']];
  }

  private static function usage(array $row): ?array {
    return $row['usage_json'] === NULL ? NULL : json_decode($row['usage_json'], TRUE, 64, JSON_THROW_ON_ERROR);
  }

  private static function hash(array $value): string {
    return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
  }

  private function nowMs(): int {
    return (int) round($this->time->getCurrentMicroTime() * 1000);
  }

}
