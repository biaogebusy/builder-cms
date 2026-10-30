<?php

declare(strict_types=1);

namespace Drupal\xinshi_credits;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/** The sole promotional grant writer; no controller accepts grant commands yet. */
final class CreditLedger {

  public function __construct(
    private readonly Connection $database,
    private readonly CreditContext $context,
    private readonly TimeInterface $time,
    private readonly UuidInterface $uuid,
    private readonly EntityTypeManagerInterface $entities,
    private readonly CreditBudgets $budgets,
  ) {}

  /**
   * Commit a qualified promotion from a trusted server-side business adapter.
   *
   * The adapter must authorize the action, derive the actor and business key,
   * and validate reward eligibility before calling. Never pass a request body
   * directly. This service owns provenance, dedup, exact amounts and budgets.
   */
  public function grant(array $input, string $businessKey): array {
    if ($this->database->inTransaction()) {
      throw new \LogicException('The ledger must own the root transaction.');
    }
    CreditValue::fields($input, ['beneficiary_user_id', 'actor_user_id', 'amount_atoms',
      'source_kind', 'source_ref', 'grant_kind', 'rule_id', 'rule_version',
      'budget_pool_id', 'scope', 'expires_at', 'public_reason'], ['internal_note']);
    $command = [];
    foreach (['beneficiary_user_id', 'actor_user_id'] as $field) {
      $command[$field] = (string) CreditValue::amount($input[$field], TRUE);
    }
    $atoms = CreditValue::amount($input['amount_atoms'], TRUE);
    $command['amount_atoms'] = (string) $atoms;
    foreach (['source_kind', 'grant_kind', 'rule_id', 'rule_version', 'budget_pool_id'] as $field) {
      $command[$field] = CreditValue::id($input[$field]);
    }
    if (!in_array($command['source_kind'], ['promotion', 'admin_gift', 'compensation'], TRUE)) {
      throw new CreditException('unsupported_grant_source');
    }
    $command['source_ref'] = CreditValue::text($input['source_ref']);
    $command['scope'] = CreditValue::scope($input['scope']);
    if (!is_int($input['expires_at']) || $input['expires_at'] <= 0) throw new CreditException('invalid_expiry');
    $command['expires_at'] = $input['expires_at'];
    $command['public_reason'] = CreditValue::text($input['public_reason']);
    $command['internal_note'] = CreditValue::text($input['internal_note'] ?? '', 1000, TRUE);
    $site = $this->context->site();
    $key = hash('sha256', CreditValue::id($businessKey));
    $hash = hash('sha256', json_encode($command, JSON_THROW_ON_ERROR));
    $origin = hash('sha256', json_encode([$command['beneficiary_user_id'], $command['source_kind'],
      $command['source_ref'], $command['grant_kind']], JSON_THROW_ON_ERROR));
    if ($existing = $this->previous($site, $key, $origin, $hash)) return $existing;
    $this->context->assertGrantsEnabled();
    $transaction = $this->database->startTransaction();
    try {
      $now = $this->time->getCurrentTime();
      if ($command['expires_at'] <= $now) throw new CreditException('invalid_expiry');
      foreach (array_unique([$command['beneficiary_user_id'], $command['actor_user_id']]) as $userId) {
        $user = $this->entities->getStorage('user')->load($userId);
        if (!$user || !$user->isActive()) throw new CreditException('account_unavailable', 403);
      }
      $commitments = $this->budgets->reserveGrant($command['budget_pool_id'],
        $command['rule_id'], $command['rule_version'], $command['scope'], $atoms, $command['expires_at']);
      $account = $this->database->select('credit_account', 'a')->fields('a')
        ->condition('site_id', $site)->condition('owner_id', $command['beneficiary_user_id'])
        ->forUpdate()->execute()->fetchAssoc();
      if (!$account) {
        $account = ['id' => $this->uuid->generate(), 'site_id' => $site,
          'owner_id' => $command['beneficiary_user_id'], 'status' => 'active',
          'available_atoms' => 0, 'held_atoms' => 0, 'refund_locked_atoms' => 0,
          'balance_version' => 0, 'updated_at' => $now];
        $this->database->insert('credit_account')->fields($account)->execute();
      }
      if ($account['status'] !== 'active') throw new CreditException('account_unavailable', 409);
      $balance = CreditValue::add(CreditValue::amount((string) $account['available_atoms']), $atoms);
      $version = CreditValue::add(CreditValue::amount((string) $account['balance_version']), 1);
      // A version predicate also protects drivers whose FOR UPDATE is a no-op.
      $updated = $this->database->update('credit_account')->fields(['available_atoms' => $balance,
        'balance_version' => $version, 'updated_at' => $now])
        ->condition('id', $account['id'])->condition('balance_version', $account['balance_version'])->execute();
      if ($updated !== 1) throw new CreditException('ledger_busy', 503);
      $journalId = $this->uuid->generate();
      $grantId = $this->uuid->generate();
      $journal = ['id' => $journalId, 'site_id' => $site, 'account_id' => $account['id'],
        'business_key' => $key, 'request_hash' => $hash, 'origin_key' => $origin,
        'transaction_kind' => 'grant', 'grant_id' => $grantId, 'actor_user_id' => $command['actor_user_id'],
        'available_delta_atoms' => $atoms, 'available_after_atoms' => $balance, 'balance_version' => $version,
        'public_reason' => $command['public_reason'], 'internal_note' => $command['internal_note'], 'posted_at' => $now];
      $this->database->insert('credit_journal')->fields($journal)->execute();
      $this->database->insert('credit_grant')->fields([
        'id' => $grantId, 'site_id' => $site, 'account_id' => $account['id'], 'journal_id' => $journalId,
        'origin_key' => $origin, 'source_kind' => $command['source_kind'], 'source_ref' => $command['source_ref'],
        'grant_kind' => $command['grant_kind'], 'rule_id' => $command['rule_id'], 'rule_version' => $command['rule_version'],
        'budget_pool_id' => $command['budget_pool_id'], 'scope_json' => json_encode($command['scope'], JSON_THROW_ON_ERROR),
        'cost_commitments_json' => json_encode($commitments, JSON_THROW_ON_ERROR),
        'granted_atoms' => $atoms, 'available_atoms' => $atoms, 'valid_from' => $now, 'expires_at' => $command['expires_at'],
      ])->execute();
      $postings = $this->database->insert('credit_posting')->fields(['journal_id', 'grant_id', 'ledger_account_ref', 'unit', 'delta_atoms']);
      $postings->values([$journalId, $grantId, 'customer:' . $account['id'] . ':available', 'credit_atoms', $atoms]);
      $postings->values([$journalId, $grantId, 'system:issued', 'credit_atoms', -$atoms]);
      $postings->execute();
      $this->database->insert('credit_notification_outbox')->fields([
        'event_id' => $this->uuid->generate(), 'journal_id' => $journalId,
        'recipient_user_id' => $command['beneficiary_user_id'], 'template_id' => 'credits.granted',
        'state' => 'pending', 'created_at' => $now, 'next_retry_at' => $now,
      ])->execute();
      $transaction->commitOrRelease();
      return $this->receipt($journal);
    }
    catch (\Throwable $error) {
      if ($this->database->inTransaction()) $transaction->rollBack();
      // A concurrent identical commit can win any unique key or exhaust the pool.
      // Read again outside the rolled-back snapshot before reporting its failure.
      if ($existing = $this->previous($site, $key, $origin, $hash)) return $existing;
      throw $error;
    }
  }

  private function previous(string $site, string $key, string $origin, string $hash): ?array {
    $query = $this->database->select('credit_journal', 'j')->fields('j')->condition('site_id', $site);
    $query->condition($query->orConditionGroup()->condition('business_key', $key)->condition('origin_key', $origin));
    $rows = $query->range(0, 2)->execute()->fetchAll(\PDO::FETCH_ASSOC);
    if (!$rows) return NULL;
    if (count($rows) !== 1 || !hash_equals($rows[0]['request_hash'], $hash)
      || !hash_equals($rows[0]['business_key'], $key)) {
      throw new CreditException('idempotency_conflict', 409);
    }
    return $this->receipt($rows[0]);
  }

  private function receipt(array $row): array {
    return ['journal_id' => $row['id'], 'grant_id' => $row['grant_id'], 'account_id' => $row['account_id'],
      'amount_atoms' => (string) $row['available_delta_atoms'],
      'available_after_atoms' => (string) $row['available_after_atoms'],
      'balance_version' => (string) $row['balance_version'], 'posted_at' => gmdate('c', (int) $row['posted_at'])];
  }

}
