<?php

declare(strict_types=1);

namespace Drupal\xinshi_credits;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/** The sole promotional grant writer, called by trusted business adapters. */
final class CreditLedger {

  public function __construct(
    private readonly Connection $database,
    private readonly CreditContext $context,
    private readonly UuidInterface $uuid,
    private readonly EntityTypeManagerInterface $entities,
    private readonly CreditBudgets $budgets,
    private readonly CreditStore $store,
  ) {}

  /**
   * Commit a qualified promotion from a trusted server-side business adapter.
   *
   * The adapter must authorize the action, derive the actor and business key,
   * and validate reward eligibility before calling. Never pass a request body
   * directly. This service owns provenance, dedup, exact amounts and budgets.
   * The optional callback saves qualified business records in this transaction.
   * It must use this connection, acquire no account locks and do no external I/O.
   * It runs only for a new grant; any exception rolls back the whole grant.
   */
  public function grant(array $input, string $businessKey, ?callable $record = NULL): array {
    CreditValue::fields($input, ['beneficiary_user_id', 'actor_user_id', 'amount_atoms',
      'source_kind', 'source_ref', 'grant_kind', 'rule_id', 'rule_version',
      'budget_pool_id', 'scope', 'public_reason'], ['internal_note', 'expires_at']);
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
    $command['expires_at'] = CreditValue::expiry($input['expires_at'] ?? NULL);
    $command['public_reason'] = CreditValue::text($input['public_reason']);
    $command['internal_note'] = CreditValue::text($input['internal_note'] ?? '', 1000, TRUE);
    $origin = hash('sha256', json_encode([$command['beneficiary_user_id'], $command['source_kind'],
      $command['source_ref'], $command['grant_kind']], JSON_THROW_ON_ERROR));
    return $this->store->run($command, $businessKey, $origin, function (array $account, string $journalId, int $now) use ($command, $atoms, $origin, $record): array {
      $this->context->assertGrantsEnabled();
      if ($command['expires_at'] !== NULL && $command['expires_at'] <= $now) throw new CreditException('invalid_expiry');
      foreach (array_unique([$command['beneficiary_user_id'], $command['actor_user_id']]) as $userId) {
        $user = $this->entities->getStorage('user')->load($userId);
        if (!$user || !$user->isActive()) throw new CreditException('account_unavailable', 403);
      }
      if ($account['status'] !== 'active') throw new CreditException('account_unavailable', 409);
      $site = $account['site_id'];
      $grantId = $this->uuid->generate();
      $funding = $this->budgets->reserveGrant($command['budget_pool_id'], $command['rule_id'],
        $command['rule_version'], $command['scope'], $atoms, $command['expires_at'], $grantId, $journalId,
        $command['source_kind']);
      $this->database->insert('credit_grant')->fields([
        'id' => $grantId, 'site_id' => $site, 'account_id' => $account['id'], 'journal_id' => $journalId,
        'origin_key' => $origin, 'source_kind' => $command['source_kind'], 'source_ref' => $command['source_ref'],
        'grant_kind' => $command['grant_kind'], 'rule_id' => $command['rule_id'], 'rule_version' => $command['rule_version'],
        'budget_pool_id' => $command['budget_pool_id'], 'scope_json' => json_encode($command['scope'], JSON_THROW_ON_ERROR),
        'accounting_mode' => $funding['accounting_mode'],
        'cost_commitments_json' => json_encode($funding['commitments'], JSON_THROW_ON_ERROR),
        'granted_atoms' => $atoms, 'available_atoms' => $atoms, 'valid_from' => $now, 'expires_at' => $command['expires_at'],
      ])->execute();
      $this->database->insert('credit_notification_outbox')->fields([
        'event_id' => $this->uuid->generate(), 'journal_id' => $journalId,
        'recipient_user_id' => $command['beneficiary_user_id'], 'template_id' => 'credits.granted',
        'state' => 'pending', 'created_at' => $now, 'next_retry_at' => $now,
      ])->execute();
      if ($record !== NULL) $record($journalId, $grantId, $now);
      return ['kind' => 'grant', 'grant_id' => $grantId, 'deltas' => ['available' => $atoms],
        'postings' => [[$grantId, 'customer:' . $account['id'] . ':available', $atoms],
          [$grantId, 'system:issued', -$atoms]]];
    }, TRUE);
  }

}
