<?php

declare(strict_types=1);

namespace Drupal\xinshi_credits;

use Drupal\Core\Database\Connection;

/** Read-only reconstruction for authorized operations tooling, never automatic repair. */
final class CreditAudit {

  public function __construct(private readonly Connection $database, private readonly CreditContext $context) {}

  /** Inspect a bounded complete account history and its shared budget buckets. */
  public function inspect(string $userId, int $limit = 2000): array {
    CreditValue::amount($userId, TRUE);
    if ($limit < 1 || $limit > 10000) throw new CreditException('invalid_limit');
    if ($this->database->inTransaction()) throw new \LogicException('The audit must own its snapshot.');
    $transaction = $this->database->startTransaction();
    try {
      $account = $this->database->select('credit_account', 'a')->fields('a')->condition('site_id', $this->context->site())
        ->condition('owner_id', $userId)->forUpdate()->execute()->fetchAssoc();
      if (!$account) return ['status' => 'not_initialized'];
      // Establish the read snapshot only after locking every shared budget it will inspect.
      $budgetIds = $this->database->select('credit_budget_posting', 'p')->fields('p', ['bucket_id']);
      $budgetIds->join('credit_journal', 'j', 'j.id = p.journal_id');
      $ids = $budgetIds->condition('j.account_id', $account['id'])->distinct()->forUpdate()
        ->range(0, 1025)->execute()->fetchCol();
      if (count($ids) > 1024) return ['status' => 'limit_exceeded'];
      sort($ids, SORT_STRING); $buckets = [];
      foreach ($ids as $id) $buckets[$id] = $this->database->select('credit_budget_bucket', 'b')->fields('b')
        ->condition('id', $id)->forUpdate()->execute()->fetchAssoc();
      $journals = $this->database->select('credit_journal', 'j')->fields('j')->condition('account_id', $account['id'])
        ->orderBy('sequence')->range(0, $limit + 1)->execute()->fetchAll(\PDO::FETCH_ASSOC);
      if (count($journals) > $limit) return ['status' => 'limit_exceeded'];
      $grants = $this->database->select('credit_grant', 'g')->fields('g')->condition('account_id', $account['id'])
        ->range(0, $limit + 1)->execute()->fetchAllAssoc('id', \PDO::FETCH_ASSOC);
      if (count($grants) > $limit) return ['status' => 'limit_exceeded'];
      $postings = $this->database->select('credit_posting', 'p');
      $postings->join('credit_journal', 'j', 'j.id = p.journal_id');
      $postings->fields('p')->condition('j.account_id', $account['id'])->orderBy('p.id')->range(0, 100001);
      $rows = $postings->execute()->fetchAll(\PDO::FETCH_ASSOC);
      if (count($rows) > 100000) return ['status' => 'limit_exceeded'];
      $byJournal = []; foreach ($rows as $row) $byJournal[$row['journal_id']][] = $row;
      $issues = []; $balances = ['available' => 0, 'held' => 0, 'refund_locked' => 0]; $byGrant = []; $version = 0;
      $lastGrantTime = []; $operations = [];
      foreach ($journals as $journal) {
        $kind = $journal['transaction_kind']; $op = $journal['operation_id'];
        if ($op !== '' && $kind !== 'return') $operations[$op][$kind . '_journal_id'] = $journal['id'];
        $sum = 0; $deltas = ['available' => 0, 'held' => 0, 'refund_locked' => 0];
        foreach ($byJournal[$journal['id']] ?? [] as $row) {
          $delta = $this->signed($row['delta_atoms']); $sum = $this->add($sum, $delta);
          $ref = $row['ledger_account_ref']; $field = NULL;
          foreach (array_keys($balances) as $part) if ($ref === 'customer:' . $account['id'] . ':' . $part) {
            $deltas[$part] = $this->add($deltas[$part], $delta); $field = $part . '_atoms';
          }
          if ($ref === 'system:consumed') $field = 'consumed_atoms';
          if ($ref === 'system:expired') $field = 'expired_atoms';
          if ($ref === 'system:issued') { $field = 'granted_atoms'; $delta = -$delta; }
          if ($field) $byGrant[$row['grant_id']][$field] = $this->add($byGrant[$row['grant_id']][$field] ?? 0, $delta);
          else $issues[] = 'unknown_posting_account';
          $lastGrantTime[$row['grant_id']] = (int) $journal['posted_at'];
          $allocationPart = match (TRUE) {
            $kind === 'hold' && $field === 'held_atoms' => 'held_atoms',
            $kind === 'consume' && $field === 'consumed_atoms' => 'charged_atoms',
            $kind === 'return' && $field === 'consumed_atoms' => 'returned_atoms',
            default => NULL,
          };
          if ($allocationPart) {
            $old = $operations[$op]['allocations'][$row['grant_id']][$allocationPart] ?? 0;
            $operations[$op]['allocations'][$row['grant_id']][$allocationPart] = $this->add($old, $kind === 'return' ? -$delta : $delta);
          }
        }
        if ($sum !== 0 || !isset($byJournal[$journal['id']])) $issues[] = 'unbalanced_journal';
        foreach ($balances as $part => $balance) {
          $balances[$part] = CreditValue::shift($balance, $deltas[$part]);
          if ($deltas[$part] !== $this->signed($journal[$part . '_delta_atoms'])
            || $balances[$part] !== (int) $journal[$part . '_after_atoms']) $issues[] = 'journal_projection_mismatch';
        }
        if ((int) $journal['balance_version'] !== ++$version) $issues[] = 'journal_version_mismatch';
      }
      foreach ($balances as $part => $value) if ($value !== (int) $account[$part . '_atoms']) $issues[] = 'account_projection_mismatch';
      if ($version !== (int) $account['balance_version']) $issues[] = 'account_version_mismatch';
      if (array_diff(array_keys($byGrant), array_keys($grants))) $issues[] = 'grant_missing';
      foreach ($grants as $grant) foreach (['available_atoms', 'held_atoms', 'refund_locked_atoms', 'consumed_atoms', 'expired_atoms', 'granted_atoms'] as $field) {
        if ((int) $grant[$field] !== ($byGrant[$grant['id']][$field] ?? 0)) $issues[] = 'grant_projection_mismatch';
      }
      $costQuery = $this->database->select('credit_grant_cost', 'c');
      $costQuery->join('credit_grant', 'g', 'g.id = c.grant_id');
      $costs = $costQuery->fields('c')->condition('g.account_id', $account['id'])->range(0, 100001)->execute()->fetchAll(\PDO::FETCH_ASSOC);
      if (count($costs) > 100000) return ['status' => 'limit_exceeded'];
      $byCost = []; foreach ($costs as $cost) $byCost[$cost['grant_id']][$cost['bucket_id']] = $cost;
      foreach ($grants as $grant) {
        $snapshots = json_decode($grant['cost_commitments_json'], TRUE, 32, JSON_THROW_ON_ERROR);
        if (count($snapshots) !== count($byCost[$grant['id']] ?? [])) $issues[] = 'grant_cost_projection_mismatch';
        foreach ($snapshots as $snapshot) {
          $cost = $byCost[$grant['id']][$snapshot['bucket_id']] ?? NULL;
          if (!$cost) { $issues[] = 'grant_cost_projection_mismatch'; continue; }
          $rate = CreditValue::amount((string) $cost['micros_per_point'], TRUE);
          if (CreditValue::cost((int) $grant['granted_atoms'], $rate) !== (int) $snapshot['committed_micros']) {
            $issues[] = 'grant_cost_bound_mismatch';
          }
          // A due but unprocessed expiry is lag, not corruption of the last committed projection.
          $valid = (int) $grant['expires_at'] > ($lastGrantTime[$grant['id']] ?? 0);
          $exposed = CreditValue::add((int) $grant['available_atoms'],
            CreditValue::add((int) $grant['held_atoms'], (int) $grant['consumed_atoms']));
          $committed = $valid ? CreditValue::cost((int) $grant['available_atoms'], $rate) : 0;
          $reserved = $valid ? CreditValue::cost($exposed, $rate) - $committed : 0;
          if ($committed !== (int) $cost['committed_micros'] || $reserved !== (int) $cost['reserved_micros']) {
            $issues[] = 'grant_cost_projection_mismatch';
          }
        }
      }
      $actualByJournal = []; $movementCount = 0;
      foreach ($ids as $id) {
        $bucket = $buckets[$id];
        $movements = $this->database->select('credit_budget_posting', 'p')->fields('p')->condition('bucket_id', $id)
          ->orderBy('id')->range(0, 100001 - $movementCount)->execute()->fetchAll(\PDO::FETCH_ASSOC);
        $movementCount += count($movements);
        if ($movementCount > 100000) return ['status' => 'limit_exceeded'];
        foreach ($movements as $movement) $actualByJournal[$movement['journal_id']] = $this->add(
          $actualByJournal[$movement['journal_id']] ?? 0, $this->signed($movement['actual_delta_micros']));
        $total = 0;
        foreach (['committed', 'reserved', 'actual'] as $part) {
          $value = CreditValue::amount((string) $bucket['opening_' . $part . '_micros']);
          foreach ($movements as $movement) $value = CreditValue::shift($value, $this->signed($movement[$part . '_delta_micros']));
          if ($value !== (int) $bucket[$part . '_cost_micros']) $issues[] = 'budget_projection_mismatch';
          $total = CreditValue::add($total, $value);
        }
        if ($total > (int) $bucket['cap_micros']) $issues[] = 'budget_overrun';
      }
      $reservations = $this->database->select('credit_reservation', 'r')->fields('r')
        ->condition('account_id', $account['id'])->range(0, $limit + 1)->execute()->fetchAll(\PDO::FETCH_ASSOC);
      if (count($reservations) > $limit) return ['status' => 'limit_exceeded'];
      $allocationQuery = $this->database->select('credit_allocation', 'a');
      $allocationQuery->join('credit_reservation', 'r', 'r.id = a.reservation_id');
      $allocations = $allocationQuery->fields('a')->condition('r.account_id', $account['id'])
        ->orderBy('a.position')->range(0, 100001)->execute()->fetchAll(\PDO::FETCH_ASSOC);
      if (count($allocations) > 100000) return ['status' => 'limit_exceeded'];
      $byReservation = []; foreach ($allocations as $row) $byReservation[$row['reservation_id']][] = $row;
      if (count($reservations) !== count($operations)) $issues[] = 'reservation_projection_mismatch';
      foreach ($reservations as $reservation) {
        $op = $operations[$reservation['operation_id']] ?? [];
        $expectedState = isset($op['consume_journal_id']) ? 'settled' : (isset($op['release_journal_id']) ? 'released' : 'held');
        if ($reservation['state'] !== $expectedState || $reservation['hold_journal_id'] !== ($op['hold_journal_id'] ?? '')
          || $reservation['terminal_journal_id'] !== ($op['consume_journal_id'] ?? $op['release_journal_id'] ?? '')
          || (int) $reservation['actual_cost_micros'] !== ($actualByJournal[$reservation['terminal_journal_id']] ?? 0)) {
          $issues[] = 'reservation_projection_mismatch';
        }
        $rows = $byReservation[$reservation['id']] ?? [];
        if (array_column($rows, 'grant_id') !== array_keys($op['allocations'] ?? [])) $issues[] = 'allocation_projection_mismatch';
        $totals = ['held_atoms' => 0, 'charged_atoms' => 0, 'returned_atoms' => 0];
        foreach ($rows as $position => $allocation) {
          if ((int) $allocation['position'] !== $position) $issues[] = 'allocation_projection_mismatch';
          foreach ($totals as $part => $value) {
            $expected = $op['allocations'][$allocation['grant_id']][$part] ?? 0;
            if ((int) $allocation[$part] !== $expected) $issues[] = 'allocation_projection_mismatch';
            $totals[$part] = CreditValue::add($value, $expected);
          }
        }
        foreach (['held_atoms' => 'authorized_atoms', 'charged_atoms' => 'charged_atoms', 'returned_atoms' => 'returned_atoms'] as $part => $field) {
          if ($totals[$part] !== (int) $reservation[$field]) $issues[] = 'reservation_projection_mismatch';
        }
      }
      return ['status' => $issues ? 'mismatch' : 'complete', 'issues' => array_values(array_unique($issues)),
        'balance_version' => (string) $version, 'journal_count' => count($journals), 'budget_count' => count($ids)];
    }
    finally { $transaction->rollBack(); }
  }

  private function signed(mixed $value): int {
    $text = (string) $value;
    return str_starts_with($text, '-') ? -CreditValue::amount(substr($text, 1), TRUE) : CreditValue::amount($text);
  }

  private function add(int $left, int $right): int {
    $sum = $left + $right;
    if (!is_int($sum)) throw new CreditException('amount_overflow');
    return $sum;
  }

}
