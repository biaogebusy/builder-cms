<?php

declare(strict_types=1);

namespace Drupal\xinshi_credits;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;

/** Owns single-account transactions and immutable, balanced journal commits. */
final class CreditStore {

  public function __construct(
    private readonly Connection $database,
    private readonly CreditContext $context,
    private readonly TimeInterface $time,
    private readonly UuidInterface $uuid,
  ) {}

  /**
   * Execute one normalized trusted command; the callback runs under the account lock.
   *
   * The callback returns kind, grant_id, operation_id, deltas, postings and details.
   * No callback may acquire another account or perform external side effects.
   */
  public function run(array $command, string $businessKey, string $origin, callable $execute, bool $create = FALSE): array {
    if ($this->database->inTransaction()) throw new \LogicException('The ledger must own the root transaction.');
    $site = $this->context->site();
    $key = hash('sha256', CreditValue::id($businessKey));
    $hash = hash('sha256', json_encode($command, JSON_THROW_ON_ERROR));
    if ($receipt = $this->previous($site, $key, $origin, $hash)) return $receipt;
    $transaction = $this->database->startTransaction();
    try {
      $now = $this->time->getCurrentTime();
      $account = $this->database->select('credit_account', 'a')->fields('a')
        ->condition('site_id', $site)->condition('owner_id', $command['beneficiary_user_id'])
        ->forUpdate()->execute()->fetchAssoc();
      if (!$account && $create) {
        $account = ['id' => $this->uuid->generate(), 'site_id' => $site, 'owner_id' => $command['beneficiary_user_id'],
          'status' => 'active', 'available_atoms' => 0, 'held_atoms' => 0, 'refund_locked_atoms' => 0,
          'balance_version' => 0, 'updated_at' => $now];
        $this->database->insert('credit_account')->fields($account)->execute();
      }
      if (!$account) throw new CreditException('account_unavailable', 409);
      $journalId = $this->uuid->generate();
      $change = $execute($account, $journalId, $now);
      $fields = [];
      foreach (['available', 'held', 'refund_locked'] as $part) {
        $fields[$part . '_atoms'] = CreditValue::shift((int) $account[$part . '_atoms'], $change['deltas'][$part] ?? 0);
      }
      $fields['balance_version'] = CreditValue::add((int) $account['balance_version'], 1);
      $fields['updated_at'] = $now;
      $updated = $this->database->update('credit_account')->fields($fields)->condition('id', $account['id'])
        ->condition('balance_version', $account['balance_version'])->execute();
      if ($updated !== 1) throw new CreditException('ledger_busy', 503);
      $receipt = ['journal_id' => $journalId, 'grant_id' => $change['grant_id'], 'account_id' => $account['id'],
        'amount_atoms' => (string) ($change['deltas']['available'] ?? 0),
        'available_after_atoms' => (string) $fields['available_atoms'],
        'balance_version' => (string) $fields['balance_version'], 'posted_at' => gmdate('c', $now)];
      $receipt += $change['details'] ?? [];
      if ($change['kind'] !== 'grant') {
        unset($receipt['amount_atoms']);
        $receipt['transaction_kind'] = $change['kind'];
        $receipt['available_delta_atoms'] = (string) ($change['deltas']['available'] ?? 0);
        $receipt['held_delta_atoms'] = (string) ($change['deltas']['held'] ?? 0);
        $receipt['held_after_atoms'] = (string) $fields['held_atoms'];
      }
      $row = ['id' => $journalId, 'site_id' => $site, 'account_id' => $account['id'],
        'business_key' => $key, 'request_hash' => $hash, 'origin_key' => $origin,
        'transaction_kind' => $change['kind'], 'grant_id' => $change['grant_id'],
        'operation_id' => $change['operation_id'] ?? '', 'reverses_journal_id' => $change['reverses_journal_id'] ?? '',
        'actor_user_id' => $command['actor_user_id'], 'public_reason' => $command['public_reason'],
        'internal_note' => $command['internal_note'] ?? '', 'posted_at' => $now,
        'balance_version' => $fields['balance_version'], 'receipt_json' => json_encode($receipt, JSON_THROW_ON_ERROR)];
      foreach (['available', 'held', 'refund_locked'] as $part) {
        $row[$part . '_delta_atoms'] = $change['deltas'][$part] ?? 0;
        $row[$part . '_after_atoms'] = $fields[$part . '_atoms'];
      }
      $this->database->insert('credit_journal')->fields($row)->execute();
      $sums = ['total' => 0, 'available' => 0, 'held' => 0, 'refund_locked' => 0];
      foreach ($change['postings'] as [$grantId, $ref, $delta]) {
        // Validate through bounded integer operations; a transient float is never accepted.
        $next = $sums['total'] + $delta;
        if (!is_int($next)) throw new CreditException('amount_overflow');
        $sums['total'] = $next;
        foreach (['available', 'held', 'refund_locked'] as $part) {
          if ($ref === 'customer:' . $account['id'] . ':' . $part) $sums[$part] += $delta;
        }
        $this->database->insert('credit_posting')->fields(['journal_id' => $journalId, 'grant_id' => $grantId,
          'ledger_account_ref' => $ref, 'unit' => 'credit_atoms', 'delta_atoms' => $delta])->execute();
      }
      if ($sums['total'] !== 0) throw new CreditException('unbalanced_journal', 409);
      foreach (['available', 'held', 'refund_locked'] as $part) {
        if ($sums[$part] !== ($change['deltas'][$part] ?? 0)) throw new CreditException('unbalanced_journal', 409);
      }
      $transaction->commitOrRelease();
      return $receipt;
    }
    catch (\Throwable $error) {
      if ($this->database->inTransaction()) $transaction->rollBack();
      if ($receipt = $this->previous($site, $key, $origin, $hash)) return $receipt;
      throw $error;
    }
  }

  private function previous(string $site, string $key, string $origin, string $hash): ?array {
    $query = $this->database->select('credit_journal', 'j')->fields('j')->condition('site_id', $site);
    $query->condition($query->orConditionGroup()->condition('business_key', $key)->condition('origin_key', $origin));
    $rows = $query->range(0, 2)->execute()->fetchAll(\PDO::FETCH_ASSOC);
    if (!$rows) return NULL;
    if (count($rows) !== 1 || !hash_equals($rows[0]['request_hash'], $hash) || !hash_equals($rows[0]['business_key'], $key)) {
      throw new CreditException('idempotency_conflict', 409);
    }
    return json_decode($rows[0]['receipt_json'], TRUE, 32, JSON_THROW_ON_ERROR);
  }

}
