<?php

declare(strict_types=1);

namespace Drupal\xinshi_credits;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;

/** Reserves configured promotional obligations within the ledger transaction. */
final class CreditBudgets {

  public function __construct(
    private readonly Connection $database,
    private readonly CreditContext $context,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Consume an immutable rule allocation and every eligible category budget.
   *
   * Budget publication and qualification belong to the owning business adapter.
   * No default pool, cost bound, or reward is created by this module.
   */
  public function reserveGrant(string $poolId, string $rule, string $version, array $scope, int $atoms, int $expiresAt, string $grantId, string $journalId): array {
    if (!$this->database->inTransaction()) throw new \LogicException('A ledger transaction is required.');
    $site = $this->context->site();
    $now = $this->time->getCurrentTime();
    $pool = $this->database->select('credit_reward_pool', 'p')->fields('p')
      ->condition('id', $poolId)->condition('site_id', $site)->execute()->fetchAssoc();
    if (!$pool || $pool['rule_id'] !== $rule || $pool['rule_version'] !== $version
      || (int) $pool['period_start'] > $now || (int) $pool['period_end'] <= $now) {
      throw new CreditException('reward_pool_unavailable', 409);
    }
    // Cross-period commitments require the later carryover lifecycle.
    if ($expiresAt > (int) $pool['period_end']) throw new CreditException('invalid_expiry');
    CreditValue::text($pool['source_ref']);
    $bounds = json_decode($pool['bounds_json'], TRUE, 16, JSON_THROW_ON_ERROR);
    if (!is_array($bounds) || !$bounds || count($bounds) > 32 || array_diff($scope, array_keys($bounds))) {
      throw new CreditException('invalid_budget_scope');
    }
    $ordered = [];
    foreach ($scope as $category) {
      $bound = $bounds[$category];
      CreditValue::id($category);
      if (!is_array($bound)) throw new CreditException('invalid_budget_bound');
      CreditValue::fields($bound, ['bucket_id', 'micros_per_point']);
      $id = CreditValue::id($bound['bucket_id']);
      if (isset($ordered[$id])) throw new CreditException('invalid_budget_bound');
      $rate = CreditValue::amount($bound['micros_per_point'], TRUE);
      $ordered[$id] = ['category' => $category, 'rate' => $rate, 'cost' => CreditValue::cost($atoms, $rate)];
    }
    // Identical lock order avoids cross-category deadlocks between reward rules.
    ksort($ordered, SORT_STRING);
    $this->lock(array_keys($ordered));
    $commitments = [];
    foreach ($ordered as $id => $bound) {
      $bucket = $this->database->select('credit_budget_bucket', 'b')->fields('b')
        ->condition('id', $id)->condition('site_id', $site)->execute()->fetchAssoc();
      if (!$bucket || $bucket['category'] !== $bound['category']
        || (int) $bucket['period_start'] > (int) $pool['period_start']
        || (int) $bucket['period_end'] < (int) $pool['period_end']) {
        throw new CreditException('cost_budget_unavailable', 409);
      }
      CreditValue::text($bucket['source_ref']);
      $this->move($id, $journalId, $bound['cost'], 0, 0);
      $this->database->insert('credit_grant_cost')->fields(['grant_id' => $grantId, 'bucket_id' => $id,
        'micros_per_point' => $bound['rate'], 'committed_micros' => $bound['cost']])->execute();
      $commitments[] = ['bucket_id' => $id, 'category' => $bound['category'],
        'currency' => $bucket['currency'], 'committed_micros' => (string) $bound['cost']];
    }
    CreditValue::amount((string) $pool['cap_atoms']);
    CreditValue::amount((string) $pool['issued_atoms']);
    $changed = $this->database->update('credit_reward_pool')
      ->expression('issued_atoms', 'issued_atoms + :increment', [':increment' => $atoms])
      ->expression('version', 'version + 1')->condition('id', $poolId)->condition('site_id', $site)
      ->condition('version', PHP_INT_MAX, '<')
      ->where('cap_atoms - issued_atoms >= :required', [':required' => $atoms])->execute();
    if ($changed !== 1) throw new CreditException('reward_budget_exhausted', 409);
    return $commitments;
  }

  /** Lock the complete bucket set before applying any movement. */
  public function lock(array $ids): void {
    if (!$this->database->inTransaction()) throw new \LogicException('A ledger transaction is required.');
    $ids = array_unique($ids); sort($ids, SORT_STRING);
    foreach ($ids as $id) {
      $bucket = $this->database->select('credit_budget_bucket', 'b')->fields('b')
        ->condition('id', $id)->condition('site_id', $this->context->site())->forUpdate()->execute()->fetchAssoc();
      if (!$bucket) throw new CreditException('cost_budget_unavailable', 409);
      if ($bucket['opening_committed_micros'] === NULL) {
        $this->database->update('credit_budget_bucket')->fields([
          'opening_committed_micros' => $bucket['committed_cost_micros'],
          'opening_reserved_micros' => $bucket['reserved_cost_micros'],
          'opening_actual_micros' => $bucket['actual_cost_micros'],
        ])->condition('id', $id)->execute();
      }
    }
  }

  /** Record budget movements; an already incurred cost is a fact, not new admission. */
  public function move(string $id, string $journalId, int $committed, int $reserved, int $actual, bool $costFact = FALSE): void {
    if (!$this->database->inTransaction()) throw new \LogicException('A ledger transaction is required.');
    $bucket = $this->database->select('credit_budget_bucket', 'b')->fields('b')
      ->condition('id', $id)->condition('site_id', $this->context->site())->forUpdate()->execute()->fetchAssoc();
    if (!$bucket || $bucket['opening_committed_micros'] === NULL) throw new CreditException('cost_budget_unavailable', 409);
    $fields = []; $total = 0;
    foreach (['committed' => $committed, 'reserved' => $reserved, 'actual' => $actual] as $part => $delta) {
      $fields[$part . '_cost_micros'] = CreditValue::shift(CreditValue::amount((string) $bucket[$part . '_cost_micros']), $delta);
      $total = CreditValue::add($total, $fields[$part . '_cost_micros']);
    }
    // Risk-reducing cleanup remains possible after a supplier cost overrun.
    $increase = $committed + $reserved + $actual;
    if (!is_int($increase)) throw new CreditException('amount_overflow');
    if (!$costFact && $increase > 0 && $total > CreditValue::amount((string) $bucket['cap_micros'])) {
      throw new CreditException('reward_budget_exhausted', 409);
    }
    $fields['version'] = CreditValue::add((int) $bucket['version'], 1);
    $this->database->update('credit_budget_bucket')->fields($fields)->condition('id', $id)->execute();
    $this->database->insert('credit_budget_posting')->fields(['journal_id' => $journalId, 'bucket_id' => $id,
      'committed_delta_micros' => $committed, 'reserved_delta_micros' => $reserved, 'actual_delta_micros' => $actual])->execute();
  }

  /** Move a grant's frozen cost exposure with its available, held and returnable points. */
  public function refreshGrant(array $grant, string $journalId, int $now): void {
    $rows = $this->database->select('credit_grant_cost', 'c')->fields('c')->condition('grant_id', $grant['id'])
      ->orderBy('bucket_id')->execute()->fetchAll(\PDO::FETCH_ASSOC);
    if (!$rows) throw new CreditException('grant_cost_unavailable', 409);
    foreach ($rows as $cost) {
      $available = (int) $grant['available_atoms'];
      $exposed = CreditValue::add($available, CreditValue::add((int) $grant['held_atoms'], (int) $grant['consumed_atoms']));
      $valid = (int) $grant['expires_at'] > $now;
      $committed = $valid ? CreditValue::cost($available, (int) $cost['micros_per_point']) : 0;
      // Partition one rounded total so splitting a batch cannot create rounding liabilities.
      $reserved = $valid ? CreditValue::cost($exposed, (int) $cost['micros_per_point']) - $committed : 0;
      $this->move($cost['bucket_id'], $journalId, $committed - (int) $cost['committed_micros'],
        $reserved - (int) $cost['reserved_micros'], 0);
      $this->database->update('credit_grant_cost')->fields(['committed_micros' => $committed, 'reserved_micros' => $reserved])
        ->condition('grant_id', $grant['id'])->condition('bucket_id', $cost['bucket_id'])->execute();
    }
  }

}
