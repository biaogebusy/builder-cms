<?php

declare(strict_types=1);

namespace Drupal\xinshi_credits;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;

/** Publishes immutable budgets and issuance constraints, never user eligibility. */
final class CreditPublication {

  public function __construct(
    private readonly Connection $database,
    private readonly CreditContext $context,
    private readonly TimeInterface $time,
    private readonly UuidInterface $uuid,
    private readonly AccountProxyInterface $currentUser,
    private readonly EntityTypeManagerInterface $entities,
  ) {}

  /** Validate publication shape without saving or guessing missing business inputs. */
  public function normalize(string $type, array $input): array {
    if (!in_array($type, ['budget', 'pool'], TRUE)) throw new CreditException('invalid_publication');
    $required = ['publication_ref', 'period_start', 'period_end', 'source_ref'];
    $required = [...$required, ...($type === 'budget' ? ['category', 'currency', 'cap_micros', 'execution_headroom_micros']
      : ['rule_id', 'rule_version', 'cap_atoms', 'mode', 'source_kind', 'grant_limit_atoms', 'expires_at', 'bounds'])];
    CreditValue::fields($input, $required);
    $value = ['publication_ref' => CreditValue::id($input['publication_ref']), 'source_ref' => CreditValue::text($input['source_ref'])];
    foreach (['period_start', 'period_end', ...($type === 'pool' ? ['expires_at'] : [])] as $field) {
      if (!is_int($input[$field]) || $input[$field] <= 0) throw new CreditException('invalid_period');
      $value[$field] = $input[$field];
    }
    if ($value['period_start'] >= $value['period_end']) throw new CreditException('invalid_period');
    if ($type === 'budget') {
      $value['category'] = CreditValue::id($input['category']);
      if (!is_string($input['currency']) || !preg_match('/^[A-Z]{3}$/D', $input['currency'])) throw new CreditException('invalid_currency');
      $value['currency'] = $input['currency'];
      $value['cap_micros'] = (string) CreditValue::amount($input['cap_micros'], TRUE);
      $value['execution_headroom_micros'] = (string) CreditValue::amount($input['execution_headroom_micros']);
      if ((int) $value['execution_headroom_micros'] > (int) $value['cap_micros']) throw new CreditException('invalid_budget_bound');
      return $value;
    }
    foreach (['rule_id', 'rule_version'] as $field) $value[$field] = CreditValue::id($input[$field]);
    foreach (['cap_atoms', 'grant_limit_atoms'] as $field) $value[$field] = (string) CreditValue::amount($input[$field], TRUE);
    if (!in_array($input['mode'], ['fixed', 'variable'], TRUE)
      || !in_array($input['source_kind'], ['promotion', 'admin_gift', 'compensation'], TRUE)) throw new CreditException('invalid_grant_policy');
    $value['mode'] = $input['mode']; $value['source_kind'] = $input['source_kind'];
    if ((int) $value['grant_limit_atoms'] > (int) $value['cap_atoms']
      || $value['expires_at'] <= $value['period_start'] || $value['expires_at'] > $value['period_end']
      || ($value['mode'] === 'fixed' && (int) $value['cap_atoms'] % (int) $value['grant_limit_atoms'] !== 0)) {
      throw new CreditException('invalid_grant_policy');
    }
    if (!is_array($input['bounds']) || !array_is_list($input['bounds']) || !$input['bounds'] || count($input['bounds']) > 32) {
      throw new CreditException('invalid_budget_bound');
    }
    $bounds = [];
    foreach ($input['bounds'] as $bound) {
      if (!is_array($bound)) throw new CreditException('invalid_budget_bound');
      CreditValue::fields($bound, ['bucket_id', 'micros_per_point', 'source_ref']);
      $id = CreditValue::id($bound['bucket_id']);
      if (isset($bounds[$id])) throw new CreditException('invalid_budget_bound');
      $bounds[$id] = ['bucket_id' => $id, 'micros_per_point' => (string) CreditValue::amount($bound['micros_per_point'], TRUE),
        'source_ref' => CreditValue::text($bound['source_ref'])];
    }
    ksort($bounds, SORT_STRING); $value['bounds'] = array_values($bounds);
    return $value;
  }

  /** Commit one publication; a stable reference replays only identical parameters. */
  public function publish(string $type, array $input): array {
    $this->authorize();
    $value = $this->normalize($type, $input);
    if ($this->database->inTransaction()) throw new \LogicException('Publication must own its transaction.');
    $site = $this->context->site(); $table = $type === 'budget' ? 'credit_budget_bucket' : 'credit_reward_pool';
    $hash = hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
    $transaction = $this->database->startTransaction();
    try {
      // This permanent row serializes empty-range checks without a lease timeout.
      $locked = $this->database->update('credit_publication_guard')->expression('generation', 'generation + 1')
        ->condition('id', 1)->condition('generation', PHP_INT_MAX, '<')->execute();
      if ($locked !== 1) throw new CreditException('publication_unavailable', 503);
      $old = $this->database->select($table, 'p')->fields('p')->condition('site_id', $site)
        ->condition('publication_ref', $value['publication_ref'])->execute()->fetchAssoc();
      if ($old) {
        if (!hash_equals($old['publication_hash'], $hash)) throw new CreditException('idempotency_conflict', 409);
        $transaction->rollBack();
        return ['id' => $old['id'], 'publication_ref' => $old['publication_ref']];
      }
      if (min($value['period_end'], $value['expires_at'] ?? PHP_INT_MAX) <= $this->time->getCurrentTime()) {
        throw new CreditException('invalid_period');
      }
      $overlap = $this->database->select($table, 'p')->condition('site_id', $site)
        ->condition('period_start', $value['period_end'], '<')->condition('period_end', $value['period_start'], '>')
        ->condition($type === 'budget' ? 'category' : 'rule_id', $value[$type === 'budget' ? 'category' : 'rule_id'])
        ->fields('p', ['id'])->range(0, 1)->execute()->fetchField();
      if ($overlap) throw new CreditException('publication_period_overlap', 409);
      $row = ['id' => $this->uuid->generate(), 'site_id' => $site, 'publication_ref' => $value['publication_ref'],
        'publication_hash' => $hash, 'period_start' => $value['period_start'], 'period_end' => $value['period_end'],
        'source_ref' => $value['source_ref'], 'created_by' => (string) $this->currentUser->id(), 'created_at' => $this->time->getCurrentTime()];
      if ($type === 'budget') {
        $row += array_intersect_key($value, array_flip(['category', 'currency', 'cap_micros', 'execution_headroom_micros']));
      }
      else {
        $bounds = []; $evidence = [];
        foreach ($value['bounds'] as $bound) {
          $bucket = $this->database->select('credit_budget_bucket', 'b')->fields('b')->condition('id', $bound['bucket_id'])
            ->condition('site_id', $site)->forUpdate()->execute()->fetchAssoc();
          if (!$bucket || $bucket['publication_hash'] === NULL || (int) $bucket['period_start'] > $value['period_start']
            || (int) $bucket['period_end'] < $value['period_end'] || isset($bounds[$bucket['category']])) {
            throw new CreditException('cost_budget_unavailable', 409);
          }
          $rate = (int) $bound['micros_per_point'];
          // Variable grants may split down to one atom; reserve their worst rounding cost.
          $cost = $value['mode'] === 'fixed'
            ? CreditValue::multiply(intdiv((int) $value['cap_atoms'], (int) $value['grant_limit_atoms']), CreditValue::cost((int) $value['grant_limit_atoms'], $rate))
            : CreditValue::multiply((int) $value['cap_atoms'], CreditValue::cost(1, $rate));
          $planned = $this->sum('credit_pool_allocation', 'planned_cost_micros', 'bucket_id', $bucket['id']);
          // Locking reads see reservations committed while this publisher waited for the bucket.
          $execution = $this->database->select('credit_reservation', 'r')->condition('cost_bucket_id', $bucket['id'])->condition('state', 'held')->forUpdate();
          $execution->addExpression('SUM(cost_limit_micros)');
          $executionCost = CreditValue::add((int) $bucket['actual_cost_micros'], CreditValue::amount((string) ($execution->execute()->fetchField() ?? '0')));
          $required = CreditValue::add(CreditValue::add($planned, $cost), max((int) $bucket['execution_headroom_micros'], $executionCost));
          $occupied = CreditValue::add((int) $bucket['committed_cost_micros'],
            CreditValue::add((int) $bucket['reserved_cost_micros'], (int) $bucket['actual_cost_micros']));
          if (max($required, $occupied) > (int) $bucket['cap_micros']) throw new CreditException('reward_budget_exhausted', 409);
          $this->database->insert('credit_pool_allocation')->fields(['pool_id' => $row['id'], 'bucket_id' => $bucket['id'], 'planned_cost_micros' => $cost])->execute();
          $bounds[$bucket['category']] = ['bucket_id' => $bucket['id'], 'micros_per_point' => $bound['micros_per_point']];
          $evidence[$bucket['category']] = $bound['source_ref'];
        }
        ksort($bounds, SORT_STRING); ksort($evidence, SORT_STRING);
        $row += array_intersect_key($value, array_flip(['rule_id', 'rule_version', 'cap_atoms']));
        $row['bounds_json'] = json_encode($bounds, JSON_THROW_ON_ERROR);
        $row['cost_evidence_json'] = json_encode($evidence, JSON_THROW_ON_ERROR);
        $row['grant_policy_json'] = json_encode(array_intersect_key($value,
          array_flip(['mode', 'source_kind', 'grant_limit_atoms', 'expires_at'])), JSON_THROW_ON_ERROR);
      }
      $this->database->insert($table)->fields($row)->execute();
      $transaction->commitOrRelease();
      return ['id' => $row['id'], 'publication_ref' => $row['publication_ref']];
    }
    catch (\Throwable $error) { $transaction->rollBack(); throw $error; }
  }

  /** Return an explicitly bounded administrative catalog, never public cost data. */
  public function catalog(string $type): array {
    $this->authorize();
    if (!in_array($type, ['budget', 'pool'], TRUE)) throw new CreditException('invalid_publication');
    $rows = $this->database->select($type === 'budget' ? 'credit_budget_bucket' : 'credit_reward_pool', 'p')->fields('p')
      ->condition('site_id', $this->context->site())->isNotNull('publication_ref')->orderBy('created_at', 'DESC')->orderBy('id')
      ->range(0, 101)->execute()->fetchAll(\PDO::FETCH_ASSOC);
    if ($type === 'budget' && $rows) {
      $query = $this->database->select('credit_pool_allocation', 'a')->fields('a', ['bucket_id'])
        ->condition('bucket_id', array_column($rows, 'id'), 'IN')->groupBy('bucket_id');
      $query->addExpression('SUM(planned_cost_micros)', 'planned_cost_micros');
      $planned = $query->execute()->fetchAllKeyed();
      foreach ($rows as &$row) $row['planned_cost_micros'] = (string) CreditValue::amount((string) ($planned[$row['id']] ?? '0'));
      unset($row);
    }
    return ['rows' => array_slice($rows, 0, 100), 'has_more' => count($rows) > 100];
  }

  private function authorize(): void {
    if (!$this->currentUser->isAuthenticated() || !$this->currentUser->hasPermission('administer credit budgets')) {
      throw new CreditException('permission_denied', 403);
    }
    $user = $this->entities->getStorage('user')->load($this->currentUser->id());
    if (!$user || !$user->isActive()) throw new CreditException('permission_denied', 403);
  }

  private function sum(string $table, string $field, string $key, string $id): int {
    $query = $this->database->select($table, 'p')->condition($key, $id);
    $query->addExpression('SUM(' . $field . ')');
    return CreditValue::amount((string) ($query->execute()->fetchField() ?? '0'));
  }

}
