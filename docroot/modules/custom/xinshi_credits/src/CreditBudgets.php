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
  public function reserveGrant(string $poolId, string $rule, string $version, array $scope, int $atoms, int $expiresAt): array {
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
      $ordered[$id] = ['category' => $category, 'cost' => CreditValue::cost($atoms,
        CreditValue::amount($bound['micros_per_point'], TRUE))];
    }
    // Identical lock order avoids cross-category deadlocks between reward rules.
    ksort($ordered, SORT_STRING);
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
      foreach (['cap_micros', 'actual_cost_micros', 'reserved_cost_micros', 'committed_cost_micros'] as $field) {
        CreditValue::amount((string) $bucket[$field]);
      }
      $occupied = CreditValue::add((int) $bucket['actual_cost_micros'], (int) $bucket['reserved_cost_micros']);
      $occupied = CreditValue::add($occupied, (int) $bucket['committed_cost_micros']);
      if ($occupied > (int) $bucket['cap_micros']) throw new CreditException('reward_budget_exhausted', 409);
      $changed = $this->database->update('credit_budget_bucket')
        ->expression('committed_cost_micros', 'committed_cost_micros + :increment', [':increment' => $bound['cost']])
        ->expression('version', 'version + 1')->condition('id', $id)->condition('site_id', $site)
        ->condition('version', PHP_INT_MAX, '<')
        ->where('cap_micros - actual_cost_micros - reserved_cost_micros - committed_cost_micros >= :required', [':required' => $bound['cost']])
        ->execute();
      if ($changed !== 1) throw new CreditException('reward_budget_exhausted', 409);
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

}
