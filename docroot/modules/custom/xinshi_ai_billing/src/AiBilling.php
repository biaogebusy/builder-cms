<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_billing;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\xinshi_credits\{CreditContext, CreditException, CreditLifecycle, CreditValue};

/** User authority precedes producer execution; only source evidence can settle. */
final class AiBilling {

  public function __construct(
    private readonly Connection $database,
    private readonly ConfigFactoryInterface $config,
    private readonly CreditContext $context,
    private readonly CreditLifecycle $ledger,
    private readonly BillingProfiles $profiles,
    private readonly TimeInterface $time,
    private readonly UuidInterface $uuid,
    private readonly AccountProxyInterface $currentUser,
    private readonly EntityTypeManagerInterface $entities,
  ) {}

  /** Create a bounded quote for the authenticated user, without reserving credits. */
  public function quote(array $input, string $key, ?array $selection = NULL): array {
    $uid = $this->user();
    CreditValue::fields($input, ['feature', 'model', 'platform', 'request_hash', 'producer_id']);
    $hash = self::hash($input['request_hash']);
    $producer = CreditValue::id($input['producer_id']);
    $requestKey = CreditValue::id($key);
    $key = hash('sha256', $requestKey);
    $json = BillingProfiles::json($input);
    $previous = $this->database->select('ai_billing_quote', 'q')->fields('q')
      ->condition('site_id', $this->context->site())->condition('user_id', $uid)->condition('request_key', $key)->execute()->fetchAssoc();
    if ($previous) {
      if ($previous['request_json'] !== $json) throw new CreditException('quote_conflict', 409);
      return $this->quoteSummary($previous);
    }
    $this->enabled();
    $profile = $selection ?? $this->profiles->select(CreditValue::id($input['feature']),
      CreditValue::text($input['model'], 128), CreditValue::id($input['platform']));
    if ($profile['feature'] !== $input['feature'] || $profile['model'] !== $input['model'] || $profile['platform'] !== $input['platform']) throw new CreditException('quote_mismatch');
    $now = $this->time->getCurrentTime();
    $quote = ['id' => $this->uuid->generate(), 'site_id' => $this->context->site(), 'user_id' => $uid,
      'producer_id' => $producer, 'request_hash' => $hash, 'version' => $profile['version'],
      'request_key' => $key, 'request_json' => $json,
      'expires_at' => $now + $profile['quote_ttl_seconds'], 'created_at' => $now];
    try { $this->database->insert('ai_billing_quote')->fields($quote)->execute(); }
    catch (\Drupal\Core\Database\IntegrityConstraintViolationException) { return $this->quote($input, $requestKey, $selection); }
    return $this->quoteSummary($quote);
  }

  private function quoteSummary(array $quote): array {
    $profile = $this->profiles->load($quote['version']);
    $amounts = BillingProfiles::amounts($profile);
    return ['quote_id' => $quote['id'], 'version' => $profile['version'], 'expires_at' => (int) $quote['expires_at'],
      'estimated_atoms' => $amounts['estimated_atoms'], 'maximum_atoms' => $amounts['maximum_atoms'],
      'estimate_basis' => $amounts['estimate_basis'], 'request_hash' => $quote['request_hash'], 'category' => $profile['category']];
  }

  /** Accept the quote; optional local task creation shares the ledger's root transaction. */
  public function authorize(array $input, ?callable $onCommit = NULL): array {
    $uid = $this->user();
    CreditValue::fields($input, ['quote_id', 'operation_id', 'request_hash']);
    $quote = $this->quoteRow(CreditValue::id($input['quote_id']));
    $operation = CreditValue::id($input['operation_id']);
    if ($quote['user_id'] !== $uid || !hash_equals($quote['request_hash'], self::hash($input['request_hash']))) {
      throw new CreditException('quote_mismatch', 403);
    }
    $profile = $this->profiles->load($quote['version']);
    $amounts = BillingProfiles::amounts($profile);
    if ($profile['category'] === 'image' && $onCommit === NULL) {
      throw new CreditException('image_task_required', 422);
    }
    $points = ($profile['accounting_mode'] ?? '') === 'points';
    $this->ledger->reserve($this->common($uid) + ['operation_id' => $operation,
      'quote_id' => $quote['id'], 'authorization_version' => $quote['version'],
      'quote_expires_at' => (int) $quote['expires_at'], 'category' => $profile['category'],
      'amount_atoms' => $amounts['maximum_atoms']] + ($points ? ['accounting_mode' => 'points'] : [
      'cost_limit_micros' => $amounts['cost_limit_micros'], 'cost_currency' => $profile['currency'], 'cost_bucket_id' => $profile['cost_bucket_id']]),
      'ai-hold:' . $quote['id'], function (array $account, string $reservation, string $journal, int $now) use ($quote, $operation, $onCommit): void {
        $this->enabled();
        $this->database->insert('ai_billing_authorization')->fields(['site_id' => $account['site_id'],
          'operation_id' => $operation, 'quote_id' => $quote['id'], 'reservation_id' => $reservation,
          'user_id' => $quote['user_id'], 'producer_id' => $quote['producer_id'], 'state' => 'open',
          'final_json' => '', 'terminal_journal_id' => '', 'created_at' => $now])->execute();
        if ($onCommit !== NULL) $onCommit();
      });
    $auth = $this->authorization($operation);
    return ['operation_id' => $operation, 'quote_id' => $auth['quote_id'], 'state' => $auth['state'],
      'profile' => ['version' => $profile['version'], 'category' => $profile['category'],
        'calls' => array_map(static fn(array $call): array => array_intersect_key($call, array_flip([
          'model', 'account_ref', 'role', 'max_calls', 'max_input_tokens', 'max_output_tokens', 'max_images',
        ])), $profile['calls'])]];
  }

  /** A signed producer can dispatch only an original, still-open user authorization. */
  public function permit(array $producer, array $input): array {
    CreditValue::fields($input, ['operation_id', 'attempt_id', 'model', 'account_ref', 'role']);
    $operation = CreditValue::id($input['operation_id']);
    $attempt = CreditValue::id($input['attempt_id']);
    $input['model'] = CreditValue::text($input['model'], 128);
    $input['account_ref'] = CreditValue::id($input['account_ref']);
    if (!in_array($input['role'], ['primary', 'auxiliary', 'repair'], TRUE)) throw new CreditException('invalid_call');
    $transaction = $this->database->startTransaction();
    try {
      $auth = $this->authorization($operation, TRUE);
      $this->producer($producer, $auth);
      if ($auth['state'] !== 'open') throw new CreditException('operation_closed', 409);
      $user = $this->entities->getStorage('user')->load($auth['user_id']);
      if (!$user || !$user->isActive() || !$user->hasPermission('use ai credits')) {
        throw new CreditException('account_unavailable', 403);
      }
      $profile = $this->profiles->load($this->quoteRow($auth['quote_id'])['version']);
      $call = $this->call($profile, $input);
      $permits = $this->permits($operation);
      $count = 0;
      foreach ($permits as $row) {
        $previous = json_decode($row['call_json'], TRUE, 16, JSON_THROW_ON_ERROR);
        if ($row['attempt_id'] === $attempt) {
          if (BillingProfiles::json($previous) !== BillingProfiles::json($input)) throw new CreditException('permit_conflict', 409);
          $transaction->commitOrRelease();
          return $call;
        }
        if ($previous['model'] === $input['model'] && $previous['account_ref'] === $input['account_ref']
          && $previous['role'] === $input['role']) $count++;
      }
      if ($count >= $call['max_calls'] || count($permits) >= 128) throw new CreditException('authorization_exceeded', 409);
      $this->database->insert('ai_billing_permit')->fields(['site_id' => $auth['site_id'],
        'operation_id' => $operation, 'attempt_id' => $attempt, 'call_json' => BillingProfiles::json($input),
        'created_at' => $this->time->getCurrentTime()])->execute();
      $transaction->commitOrRelease();
      return $call;
    }
    catch (\Throwable $error) { $transaction->rollBack(); throw $error; }
  }

  /** Close dispatch permanently, then reconcile the exact current attempt revisions. */
  public function finalize(array $producer, array $input): array {
    CreditValue::fields($input, ['operation_id', 'delivery', 'delivery_ref', 'attempts'], ['delivery_count']);
    $operation = CreditValue::id($input['operation_id']);
    if (!in_array($input['delivery'], ['delivered', 'none'], TRUE)) throw new CreditException('invalid_delivery');
    $input['delivery_ref'] = CreditValue::text($input['delivery_ref']);
    if (!is_array($input['attempts']) || !array_is_list($input['attempts']) || count($input['attempts']) > 128) {
      throw new CreditException('invalid_manifest');
    }
    $revisions = [];
    foreach ($input['attempts'] as $item) {
      if (!is_array($item)) throw new CreditException('invalid_manifest');
      CreditValue::fields($item, ['attempt_id', 'revision']);
      $id = CreditValue::id($item['attempt_id']);
      if (isset($revisions[$id]) || !is_int($item['revision']) || $item['revision'] < 0) throw new CreditException('invalid_manifest');
      $revisions[$id] = $item['revision'];
    }
    ksort($revisions, SORT_STRING);
    $final = ['delivery' => $input['delivery'], 'delivery_ref' => $input['delivery_ref'],
      'attempt_ids' => array_keys($revisions)];
    if (isset($input['delivery_count'])) {
      if (!is_int($input['delivery_count']) || $input['delivery_count'] < 0 || $input['delivery_count'] > 16
        || ($input['delivery_count'] > 0) !== ($input['delivery'] === 'delivered')) throw new CreditException('invalid_delivery');
      $final['delivery_count'] = $input['delivery_count'];
    }
    $json = BillingProfiles::json($final);
    $transaction = $this->database->startTransaction();
    try {
      $auth = $this->authorization($operation, TRUE);
      $this->producer($producer, $auth);
      $profile = $this->profiles->load($this->quoteRow($auth['quote_id'])['version']);
      if (($profile['category'] === 'image') !== isset($final['delivery_count'])) throw new CreditException('invalid_delivery');
      if ($auth['final_json'] !== '' && $auth['final_json'] !== $json) throw new CreditException('manifest_conflict', 409);
      foreach ($this->permits($operation) as $permit) {
        if (!array_key_exists($permit['attempt_id'], $revisions)) throw new CreditException('manifest_incomplete', 409);
      }
      if ($auth['state'] === 'open') {
        $this->database->update('ai_billing_authorization')->fields(['state' => 'awaiting_evidence', 'final_json' => $json])
          ->condition('site_id', $auth['site_id'])->condition('operation_id', $operation)->execute();
      }
      $transaction->commitOrRelease();
    }
    catch (\Throwable $error) { $transaction->rollBack(); throw $error; }
    if ($auth['terminal_journal_id'] !== '') return $this->summary($operation);
    $facts = $this->facts($auth, $profile, $final, $revisions);
    if (isset($facts['pending'])) return $this->summary($operation) + ['pending_reason' => $facts['pending']];
    $evidence = BillingProfiles::json($facts);
    $hash = hash('sha256', $evidence);
    $command = $this->common($auth['user_id']) + ['operation_id' => $operation,
      'evidence_ref' => 'ai-evidence:' . $hash, 'outcome' => $facts['not_started'] ? 'not_started' : 'completed'];
    $points = ($profile['accounting_mode'] ?? '') === 'points';
    if ($points) $command['accounting_mode'] = 'points';
    if (!$facts['not_started']) {
      $command['charged_atoms'] = $facts['charged_atoms'];
      if (!$points) $command += ['actual_cost_micros' => $facts['actual_cost_micros'], 'cost_status' => 'final', 'cost_currency' => $profile['currency']];
    }
    $commit = function (array $account, string $reservation, string $journal, int $now) use ($operation, $profile, $final, $revisions, $evidence, $hash): void {
      $current = $this->authorization($operation, TRUE);
      if ($current['state'] !== 'awaiting_evidence'
        || BillingProfiles::json($this->facts($current, $profile, $final, $revisions, TRUE)) !== $evidence) {
        throw new CreditException('evidence_changed', 409);
      }
      $this->database->insert('ai_billing_evidence')->fields(['site_id' => $account['site_id'],
        'operation_id' => $operation, 'evidence_hash' => $hash, 'evidence_json' => $evidence, 'created_at' => $now])->execute();
      $this->database->update('ai_billing_authorization')->fields(['state' => 'closed', 'terminal_journal_id' => $journal])
        ->condition('site_id', $account['site_id'])->condition('operation_id', $operation)->execute();
    };
    if ($facts['not_started']) $this->ledger->release($command, 'ai-final:' . $auth['quote_id'], $commit);
    else $this->ledger->settle($command, 'ai-final:' . $auth['quote_id'], $commit);
    return $this->summary($operation);
  }

  /** Personal reads never expose supplier costs, internal evidence or another user's task. */
  public function own(string $operation): array {
    $uid = $this->user();
    $auth = $this->authorization(CreditValue::id($operation));
    if ($auth['user_id'] !== $uid) throw new CreditException('operation_unavailable', 404);
    return $this->summary($operation);
  }

  private function facts(array $auth, array $profile, array $final, array $revisions, bool $lock = FALSE): array {
    $query = $this->database->select('ai_provider_attempt', 'a')->fields('a')
      ->condition('site_id', $auth['site_id'])->condition('operation_id', $auth['operation_id'])->orderBy('attempt_id')->range(0, 129);
    if ($lock) $query->forUpdate();
    $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
    if (array_column($rows, 'attempt_id') !== array_keys($revisions)) return ['pending' => 'usage_incomplete'];
    $permits = array_column($this->permits($auth['operation_id']), 'call_json', 'attempt_id');
    $points = ($profile['accounting_mode'] ?? '') === 'points';
    $charged = 0; $cost = 0; $started = FALSE; $evidence = [];
    foreach ($rows as $row) {
      $id = $row['attempt_id'];
      if ($row['producer_id'] !== $auth['producer_id'] || $row['actor_user_id'] !== $auth['user_id']
        || (int) $row['usage_revision'] !== $revisions[$id]) return ['pending' => 'usage_mismatch'];
      if ($row['state'] === 'not_sent' && $row['dispatch_state'] === 'not_sent') {
        $evidence[] = ['attempt_id' => $id, 'revision' => $revisions[$id], 'outcome' => 'not_sent'];
        continue;
      }
      if (!in_array($row['state'], ['succeeded', 'failed'], TRUE) || $row['dispatch_state'] !== 'sent'
        || !isset($permits[$id])) return ['pending' => 'execution_unresolved'];
      $started = TRUE;
      $permit = json_decode($permits[$id], TRUE, 16, JSON_THROW_ON_ERROR);
      if ($row['payer'] !== 'platform' || $row['requested_model'] !== $permit['model']
        || $row['provider_account_ref'] !== $permit['account_ref'] || $row['billing_role'] !== $permit['role']) {
        return ['pending' => 'call_mismatch'];
      }
      $call = $this->call($profile, $permit);
      $costRow = NULL;
      if (!$points) {
        $costQuery = $this->database->select('ai_usage_cost_entry', 'c')->fields('c')
          ->condition('site_id', $auth['site_id'])->condition('attempt_id', $id)
          ->condition('observation_revision', $revisions[$id])->condition('source_kind', 'supplier_reconciliation')
          ->condition('valuation_state', 'reconciled');
        if ($lock) $costQuery->forUpdate();
        $costRow = $costQuery->execute()->fetchAssoc();
        if (!$costRow || $costRow['currency'] !== $profile['currency'] || $costRow['total_cost_micros'] === NULL) {
          return ['pending' => 'cost_unresolved'];
        }
        $cost = CreditValue::add($cost, CreditValue::amount((string) $costRow['total_cost_micros']));
      }
      $delivered = [];
      if ($profile['category'] === 'image') {
        $deliveries = $this->database->select('ai_usage_delivery', 'd')->fields('d')
          ->condition('site_id', $auth['site_id'])->condition('operation_id', $auth['operation_id'])
          ->condition('attempt_id', $id)->condition('artifact_kind', 'image_asset')
          ->condition('state', 'committed')->orderBy('output_index')->range(0, $call['max_images'] + 1);
        if ($lock) $deliveries->forUpdate();
        $delivered = $deliveries->execute()->fetchAll(\PDO::FETCH_ASSOC);
        if (count($delivered) > $call['max_images'] || ($delivered && $final['delivery'] !== 'delivered')) {
          return ['pending' => 'delivery_mismatch'];
        }
        if (count($delivered) !== $final['delivery_count']) return ['pending' => 'delivery_incomplete'];
      }
      if ($final['delivery'] === 'delivered' && $row['state'] === 'succeeded' && $permit['role'] === 'primary') {
        if ($profile['category'] === 'image') {
          if (!$delivered) return ['pending' => 'delivery_unresolved'];
          $charged = CreditValue::add($charged, $points ? CreditValue::multiply((int) $profile['charge_atoms'], count($delivered))
            : BillingProfiles::charge($call, 0, 0, count($delivered)));
        }
        elseif ($points) $charged = (int) $profile['charge_atoms'];
        elseif ($row['usage_quality'] !== 'reported' || $row['input_tokens_total'] === NULL || $row['output_tokens_total'] === NULL) {
          return ['pending' => 'usage_unresolved'];
        }
        else $charged = CreditValue::add($charged, BillingProfiles::charge($call,
          CreditValue::amount((string) $row['input_tokens_total']), CreditValue::amount((string) $row['output_tokens_total']),
          0));
      }
      $evidence[] = ['attempt_id' => $id, 'revision' => $revisions[$id], 'usage_event_id' => $row['last_event_id'],
        'cost_entry_id' => $costRow['id'] ?? NULL, 'cost_micros' => $costRow ? (string) $costRow['total_cost_micros'] : NULL,
        'deliveries' => $delivered];
    }
    if (!$started && $final['delivery'] === 'delivered') return ['pending' => 'delivery_unresolved'];
    $maximum = (int) BillingProfiles::amounts($profile)['maximum_atoms'];
    return ['attempts' => $evidence, 'delivery' => $final, 'not_started' => !$started,
      'charged_atoms' => (string) min($maximum, $charged), 'actual_cost_micros' => $points ? NULL : (string) $cost];
  }

  private function summary(string $operation): array {
    $auth = $this->authorization($operation);
    $row = $this->database->select('credit_reservation', 'r')->fields('r')
      ->condition('id', $auth['reservation_id'])->execute()->fetchAssoc();
    $receipt = $auth['terminal_journal_id'] === '' ? NULL : $this->database->select('credit_journal', 'j')
      ->fields('j', ['receipt_json'])->condition('id', $auth['terminal_journal_id'])->execute()->fetchField();
    $receipt = $receipt ? json_decode($receipt, TRUE, 32, JSON_THROW_ON_ERROR) : [];
    return ['operation_id' => $operation, 'quote_id' => $auth['quote_id'],
      'status' => $auth['state'] === 'closed' ? $row['state'] : $auth['state'],
      'authorized_atoms' => (string) $row['authorized_atoms'],
      'charged_atoms' => $receipt['charged_atoms'] ?? NULL, 'released_atoms' => $receipt['released_atoms'] ?? NULL,
      'expired_atoms' => $receipt['expired_atoms'] ?? NULL, 'journal_id' => $receipt['journal_id'] ?? NULL];
  }

  private function authorization(string $operation, bool $lock = FALSE): array {
    $query = $this->database->select('ai_billing_authorization', 'a')->fields('a')
      ->condition('site_id', $this->context->site())->condition('operation_id', $operation);
    if ($lock) $query->forUpdate();
    $row = $query->execute()->fetchAssoc();
    if (!$row) throw new CreditException('operation_unavailable', 404);
    return $row;
  }

  private function permits(string $operation): array {
    return $this->database->select('ai_billing_permit', 'p')->fields('p')
      ->condition('site_id', $this->context->site())->condition('operation_id', $operation)
      ->orderBy('attempt_id')->range(0, 129)->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  private function quoteRow(string $id): array {
    $row = $this->database->select('ai_billing_quote', 'q')->fields('q')
      ->condition('id', $id)->condition('site_id', $this->context->site())->execute()->fetchAssoc();
    if (!$row) throw new CreditException('quote_unavailable', 404);
    return $row;
  }

  private function call(array $profile, array $input): array {
    foreach ($profile['calls'] as $call) {
      if ($call['model'] === $input['model'] && $call['account_ref'] === $input['account_ref']
        && $call['role'] === $input['role']) return $call;
    }
    throw new CreditException('model_not_authorized', 409);
  }

  private function producer(array $producer, array $auth): void {
    if (($producer['site_id'] ?? NULL) !== $auth['site_id'] || ($producer['producer_id'] ?? NULL) !== $auth['producer_id']) {
      throw new CreditException('producer_denied', 403);
    }
  }

  private function user(): string {
    $uid = (string) $this->currentUser->id();
    if (!$this->currentUser->isAuthenticated() || !$this->currentUser->hasPermission('use ai credits')
      || !$this->entities->getStorage('user')->load($uid)?->isActive()) throw new CreditException('permission_denied', 403);
    return $uid;
  }

  private function enabled(): void {
    if ($this->config->get('xinshi_ai_billing.settings')->get('enabled') !== TRUE) throw new CreditException('billing_disabled', 503);
  }

  private function common(string $uid): array {
    return ['beneficiary_user_id' => $uid, 'actor_user_id' => $uid, 'public_reason' => 'AI service'];
  }

  private static function hash(mixed $value): string {
    if (!is_string($value) || !preg_match('/^[a-f0-9]{64}$/D', $value)) throw new CreditException('invalid_request_hash');
    return $value;
  }

}
