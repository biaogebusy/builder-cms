<?php

declare(strict_types=1);

namespace Drupal\xinshi_credits;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/** Trusted business adapters own authorization and evidence; this service owns accounting. */
final class CreditLifecycle {

  public function __construct(
    private readonly Connection $database,
    private readonly CreditContext $context,
    private readonly CreditStore $store,
    private readonly CreditBudgets $budgets,
    private readonly UuidInterface $uuid,
    private readonly EntityTypeManagerInterface $entities,
  ) {}

  /**
   * Reserve a fixed quote; an optional trusted adapter commit shares the transaction.
   *
   * The callback runs only for a new reservation. It must not perform external I/O
   * or acquire another account. A failure rolls back the entire ledger operation.
   */
  public function reserve(array $input, string $key, ?callable $onCommit = NULL): array {
    $command = $this->command($input, 'hold', ['operation_id', 'quote_id', 'authorization_version',
      'quote_expires_at', 'category', 'amount_atoms', 'cost_bucket_id', 'cost_limit_micros', 'cost_currency']);
    foreach (['operation_id', 'quote_id', 'authorization_version', 'category', 'cost_bucket_id'] as $field) {
      $command[$field] = CreditValue::id($input[$field]);
    }
    $amount = CreditValue::amount($input['amount_atoms'], TRUE);
    $command['amount_atoms'] = (string) $amount;
    $command['cost_limit_micros'] = (string) CreditValue::amount($input['cost_limit_micros']);
    $command['cost_currency'] = $this->currency($input['cost_currency']);
    if (!is_int($input['quote_expires_at'])) throw new CreditException('invalid_quote');
    $command['quote_expires_at'] = $input['quote_expires_at'];
    return $this->store->run($command, $key, $this->origin(['hold', $command['operation_id']]),
      function (array $account, string $journalId, int $now) use ($command, $amount, $onCommit): array {
        $this->context->assertSpendingEnabled();
        if ($command['quote_expires_at'] <= $now) throw new CreditException('quote_expired', 409);
        if ($account['status'] !== 'active') throw new CreditException('account_unavailable', 409);
        foreach (array_unique([$command['beneficiary_user_id'], $command['actor_user_id']]) as $uid) {
          $user = $this->entities->getStorage('user')->load($uid);
          if (!$user || !$user->isActive()) throw new CreditException('account_unavailable', 403);
        }
        $candidates = $this->database->select('credit_grant', 'g')->fields('g')->condition('account_id', $account['id'])
          ->condition('available_atoms', 0, '>')->condition('valid_from', $now, '<=')->condition('expires_at', $now, '>')
          ->orderBy('expires_at')->orderBy('valid_from')->orderBy('id')->range(0, 129)->execute()->fetchAll(\PDO::FETCH_ASSOC);
        $grants = []; $allocations = []; $remaining = $amount;
        foreach (array_slice($candidates, 0, 128) as $grant) {
          if (!in_array($command['category'], json_decode($grant['scope_json'], TRUE, 32, JSON_THROW_ON_ERROR), TRUE)) continue;
          $take = min($remaining, (int) $grant['available_atoms']);
          $grants[] = $grant; $allocations[] = $take; $remaining -= $take;
          if ($remaining === 0) break;
        }
        if ($remaining) throw new CreditException(count($candidates) > 128 ? 'allocation_limit_exceeded' : 'insufficient_credits', 402);
        $this->lockCosts($grants, $command['cost_bucket_id']);
        $bucket = $this->database->select('credit_budget_bucket', 'b')->fields('b')
          ->condition('id', $command['cost_bucket_id'])->forUpdate()->execute()->fetchAssoc();
        if ($bucket['currency'] !== $command['cost_currency']) throw new CreditException('cost_currency_mismatch', 409);
        if ($bucket['category'] !== $command['category'] || (int) $bucket['period_start'] > $now
          || (int) $bucket['period_end'] <= $now) throw new CreditException('cost_budget_unavailable', 409);
        CreditValue::text($bucket['source_ref']);
        $exposure = CreditValue::add((int) $bucket['committed_cost_micros'],
          CreditValue::add((int) $bucket['reserved_cost_micros'], (int) $bucket['actual_cost_micros']));
        if ($exposure > (int) $bucket['cap_micros']) throw new CreditException('reward_budget_exhausted', 409);
        $this->budgets->move($bucket['id'], $journalId, 0, (int) $command['cost_limit_micros'], 0);
        $id = $this->uuid->generate(); $postings = [];
        foreach ($grants as $position => $grant) {
          $take = $allocations[$position];
          $this->changeGrant($grant, ['available_atoms' => -$take, 'held_atoms' => $take], $journalId, $now);
          $postings[] = [$grant['id'], 'customer:' . $account['id'] . ':available', -$take];
          $postings[] = [$grant['id'], 'customer:' . $account['id'] . ':held', $take];
          $this->database->insert('credit_allocation')->fields(['reservation_id' => $id, 'grant_id' => $grant['id'],
            'position' => $position, 'held_atoms' => $take])->execute();
        }
        $this->database->insert('credit_reservation')->fields(['id' => $id, 'site_id' => $account['site_id'],
          'account_id' => $account['id'], 'operation_id' => $command['operation_id'], 'quote_id' => $command['quote_id'],
          'authorization_version' => $command['authorization_version'], 'category' => $command['category'],
          'quote_expires_at' => $command['quote_expires_at'],
          'cost_currency' => $command['cost_currency'],
          'authorized_atoms' => $amount, 'state' => 'held', 'hold_journal_id' => $journalId,
          'cost_bucket_id' => $bucket['id'], 'cost_limit_micros' => $command['cost_limit_micros'],
          'evidence_ref' => '', 'created_at' => $now])->execute();
        if ($onCommit) $onCommit($account, $id, $journalId, $now);
        return ['kind' => 'hold', 'grant_id' => '', 'operation_id' => $command['operation_id'],
          'deltas' => ['available' => -$amount, 'held' => $amount], 'postings' => $postings,
          'details' => ['reservation_id' => $id, 'authorized_atoms' => (string) $amount]];
      });
  }

  /** Release only when the adapter has established that execution did not start. */
  public function release(array $input, string $key, ?callable $onCommit = NULL): array {
    return $this->close($input, $key, FALSE, $onCommit);
  }

  /** Settle a verified terminal operation; unknown outcomes must retain their holds. */
  public function settle(array $input, string $key, ?callable $onCommit = NULL): array {
    return $this->close($input, $key, TRUE, $onCommit);
  }

  private function close(array $input, string $key, bool $settle, ?callable $onCommit): array {
    $required = ['operation_id', 'outcome', 'evidence_ref'];
    if ($settle) $required = [...$required, 'charged_atoms', 'actual_cost_micros', 'cost_status', 'cost_currency'];
    $command = $this->command($input, $settle ? 'consume' : 'release', $required);
    $command['operation_id'] = CreditValue::id($input['operation_id']);
    $command['evidence_ref'] = CreditValue::text($input['evidence_ref']);
    if ($input['outcome'] !== ($settle ? 'completed' : 'not_started')) throw new CreditException('execution_unresolved', 409);
    $command['outcome'] = $input['outcome'];
    if ($settle && $input['cost_status'] !== 'final') throw new CreditException('cost_unresolved', 409);
    $command['charged_atoms'] = (string) CreditValue::amount($settle ? $input['charged_atoms'] : '0');
    $command['actual_cost_micros'] = (string) CreditValue::amount($settle ? $input['actual_cost_micros'] : '0');
    if ($settle) $command['cost_currency'] = $this->currency($input['cost_currency']);
    return $this->store->run($command, $key, $this->origin(['terminal', $command['operation_id']]),
      function (array $account, string $journalId, int $now) use ($command, $settle, $onCommit): array {
        [$reservation, $allocations, $grants] = $this->operation($account, $command['operation_id']);
        if ($reservation['state'] !== 'held') throw new CreditException('operation_already_closed', 409);
        if ($settle && $command['cost_currency'] !== $reservation['cost_currency']) throw new CreditException('cost_currency_mismatch', 409);
        $remaining = (int) $command['charged_atoms'];
        if ($remaining > (int) $reservation['authorized_atoms']) throw new CreditException('authorization_exceeded', 409);
        $this->lockCosts($grants, $reservation['cost_bucket_id']);
        $postings = []; $released = 0; $expired = 0;
        foreach ($allocations as $allocation) {
          $grant = $grants[$allocation['grant_id']]; $held = (int) $allocation['held_atoms'];
          $charged = min($remaining, $held); $remaining -= $charged;
          $refund = $held - $charged;
          $back = (int) $grant['expires_at'] > $now ? $refund : 0;
          $expired += $refund - $back; $released += $back;
          $this->changeGrant($grant, ['held_atoms' => -$held, 'available_atoms' => $back,
            'consumed_atoms' => $charged, 'expired_atoms' => $refund - $back], $journalId, $now);
          $postings[] = [$grant['id'], 'customer:' . $account['id'] . ':held', -$held];
          if ($back) $postings[] = [$grant['id'], 'customer:' . $account['id'] . ':available', $back];
          if ($charged) $postings[] = [$grant['id'], 'system:consumed', $charged];
          if ($refund - $back) $postings[] = [$grant['id'], 'system:expired', $refund - $back];
          $this->database->update('credit_allocation')->fields(['charged_atoms' => $charged])
            ->condition('reservation_id', $reservation['id'])->condition('grant_id', $grant['id'])->execute();
        }
        $this->budgets->move($reservation['cost_bucket_id'], $journalId, 0, -(int) $reservation['cost_limit_micros'],
          (int) $command['actual_cost_micros'], TRUE);
        $this->database->update('credit_reservation')->fields(['state' => $settle ? 'settled' : 'released',
          'charged_atoms' => $command['charged_atoms'], 'actual_cost_micros' => $command['actual_cost_micros'],
          'terminal_journal_id' => $journalId, 'evidence_ref' => $command['evidence_ref']])
          ->condition('id', $reservation['id'])->execute();
        if ($onCommit) $onCommit($account, $reservation['id'], $journalId, $now);
        return ['kind' => $settle ? 'consume' : 'release', 'grant_id' => '', 'operation_id' => $command['operation_id'],
          'deltas' => ['available' => $released, 'held' => -(int) $reservation['authorized_atoms']], 'postings' => $postings,
          'details' => ['reservation_id' => $reservation['id'], 'charged_atoms' => $command['charged_atoms'],
            'released_atoms' => (string) $released, 'expired_atoms' => (string) $expired,
            'cost_limit_exceeded' => (int) $command['actual_cost_micros'] > (int) $reservation['cost_limit_micros']]];
      });
  }

  /** Return explicit original allocations from a separately authorized correction. */
  public function returnCredits(array $input, string $key): array {
    $command = $this->command($input, 'return', ['operation_id', 'return_ref', 'returns']);
    $command['operation_id'] = CreditValue::id($input['operation_id']);
    $command['return_ref'] = CreditValue::id($input['return_ref']);
    if (!is_array($input['returns']) || !array_is_list($input['returns']) || !$input['returns'] || count($input['returns']) > 128) {
      throw new CreditException('invalid_returns');
    }
    $returns = [];
    foreach ($input['returns'] as $row) {
      if (!is_array($row)) throw new CreditException('invalid_returns');
      CreditValue::fields($row, ['grant_id', 'amount_atoms']);
      $id = CreditValue::id($row['grant_id']);
      if (isset($returns[$id])) throw new CreditException('invalid_returns');
      $returns[$id] = (string) CreditValue::amount($row['amount_atoms'], TRUE);
    }
    ksort($returns, SORT_STRING); $command['returns'] = $returns;
    return $this->store->run($command, $key, $this->origin(['return', $command['operation_id'], $command['return_ref']]),
      function (array $account, string $journalId, int $now) use ($command): array {
        [$reservation, $allocations, $grants] = $this->operation($account, $command['operation_id']);
        if ($reservation['state'] !== 'settled') throw new CreditException('operation_not_settled', 409);
        $byGrant = array_column($allocations, NULL, 'grant_id');
        if (array_diff(array_keys($command['returns']), array_keys($grants))) throw new CreditException('invalid_returns');
        $this->lockCosts($grants);
        $total = 0; $available = 0; $expired = 0; $postings = [];
        foreach ($command['returns'] as $id => $value) {
          $amount = (int) $value; $allocation = $byGrant[$id]; $grant = $grants[$id];
          if ($amount > (int) $allocation['charged_atoms'] - (int) $allocation['returned_atoms']) {
            throw new CreditException('return_exceeded', 409);
          }
          $back = (int) $grant['expires_at'] > $now ? $amount : 0;
          $total = CreditValue::add($total, $amount); $available = CreditValue::add($available, $back);
          $expired = CreditValue::add($expired, $amount - $back);
          $this->changeGrant($grant, ['consumed_atoms' => -$amount, 'available_atoms' => $back,
            'expired_atoms' => $amount - $back], $journalId, $now);
          $this->database->update('credit_allocation')->fields(['returned_atoms' => (int) $allocation['returned_atoms'] + $amount])
            ->condition('reservation_id', $reservation['id'])->condition('grant_id', $id)->execute();
          $postings[] = [$id, 'system:consumed', -$amount];
          $postings[] = [$id, $back ? 'customer:' . $account['id'] . ':available' : 'system:expired', $amount];
        }
        $this->database->update('credit_reservation')->fields(['returned_atoms' => (int) $reservation['returned_atoms'] + $total])
          ->condition('id', $reservation['id'])->execute();
        return ['kind' => 'return', 'grant_id' => '', 'operation_id' => $command['operation_id'],
          'reverses_journal_id' => $reservation['terminal_journal_id'], 'deltas' => ['available' => $available],
          'postings' => $postings, 'details' => ['reservation_id' => $reservation['id'],
            'returned_atoms' => (string) $total, 'expired_atoms' => (string) $expired]];
      });
  }

  /** Expire available funds and return exposure without releasing unresolved execution. */
  public function expire(array $input, string $key): array {
    $command = $this->command($input, 'expire', ['grant_id'], TRUE);
    $command['grant_id'] = CreditValue::id($input['grant_id']);
    return $this->store->run($command, $key, $this->origin(['expire', $command['grant_id']]),
      function (array $account, string $journalId, int $now) use ($command): array {
        $grant = $this->database->select('credit_grant', 'g')->fields('g')->condition('id', $command['grant_id'])
          ->condition('account_id', $account['id'])->execute()->fetchAssoc();
        if (!$grant || (int) $grant['expires_at'] > $now) throw new CreditException('grant_not_expired', 409);
        $this->lockCosts([$grant]);
        $expired = (int) $grant['available_atoms'];
        $this->changeGrant($grant, ['available_atoms' => -$expired, 'expired_atoms' => $expired], $journalId, $now);
        return ['kind' => 'expire', 'grant_id' => $grant['id'], 'deltas' => ['available' => -$expired],
          'postings' => [[$grant['id'], 'customer:' . $account['id'] . ':available', -$expired],
            [$grant['id'], 'system:expired', $expired]], 'details' => ['expired_atoms' => (string) $expired]];
      });
  }

  private function command(array $input, string $action, array $required, bool $system = FALSE): array {
    CreditValue::fields($input, ['beneficiary_user_id', 'actor_user_id', 'public_reason', ...$required], ['internal_note']);
    return ['action' => $action, 'beneficiary_user_id' => (string) CreditValue::amount($input['beneficiary_user_id'], TRUE),
      'actor_user_id' => (string) CreditValue::amount($input['actor_user_id'], !$system),
      'public_reason' => CreditValue::text($input['public_reason']),
      'internal_note' => CreditValue::text($input['internal_note'] ?? '', 1000, TRUE)];
  }

  private function origin(array $parts): string {
    return hash('sha256', json_encode($parts, JSON_THROW_ON_ERROR));
  }

  private function currency(mixed $value): string {
    if (!is_string($value) || !preg_match('/^[A-Z]{3}$/D', $value)) throw new CreditException('invalid_currency');
    return $value;
  }

  private function operation(array $account, string $operation): array {
    $reservation = $this->database->select('credit_reservation', 'r')->fields('r')->condition('site_id', $account['site_id'])
      ->condition('account_id', $account['id'])->condition('operation_id', $operation)->execute()->fetchAssoc();
    if (!$reservation) throw new CreditException('operation_unavailable', 404);
    $allocations = $this->database->select('credit_allocation', 'a')->fields('a')->condition('reservation_id', $reservation['id'])
      ->orderBy('position')->range(0, 129)->execute()->fetchAll(\PDO::FETCH_ASSOC);
    if (!$allocations || count($allocations) > 128) throw new CreditException('invalid_allocations', 409);
    $grants = $this->database->select('credit_grant', 'g')->fields('g')->condition('account_id', $account['id'])
      ->condition('id', array_column($allocations, 'grant_id'), 'IN')->execute()->fetchAllAssoc('id', \PDO::FETCH_ASSOC);
    if (count($grants) !== count($allocations)) throw new CreditException('invalid_allocations', 409);
    return [$reservation, $allocations, $grants];
  }

  private function lockCosts(array $grants, ?string $extra = NULL): void {
    $ids = $this->database->select('credit_grant_cost', 'c')->fields('c', ['bucket_id'])
      ->condition('grant_id', array_column($grants, 'id'), 'IN')->execute()->fetchCol();
    if ($extra !== NULL) $ids[] = $extra;
    $this->budgets->lock($ids);
  }

  private function changeGrant(array $grant, array $deltas, string $journalId, int $now): void {
    $fields = [];
    foreach ($deltas as $field => $delta) $fields[$field] = CreditValue::shift((int) $grant[$field], $delta);
    $this->database->update('credit_grant')->fields($fields)->condition('id', $grant['id'])->execute();
    $this->budgets->refreshGrant(array_replace($grant, $fields), $journalId, $now);
  }

}
