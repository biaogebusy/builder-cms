<?php

declare(strict_types=1);

namespace Drupal\xinshi_rewards;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\xinshi_credits\CreditContext;
use Drupal\xinshi_credits\CreditException;
use Drupal\xinshi_credits\CreditLedger;
use Drupal\xinshi_credits\CreditValue;

/** Qualifies G11/G12 rewards without owning a second balance or ledger transaction. */
final class Rewards {

  public function __construct(
    private readonly Connection $database,
    private readonly ConfigFactoryInterface $config,
    private readonly CreditContext $context,
    private readonly CreditLedger $ledger,
    private readonly TimeInterface $time,
    private readonly AccountProxyInterface $currentUser,
    private readonly EntityTypeManagerInterface $entities,
    private readonly FloodInterface $flood,
  ) {}

  /** Read current participation conditions; this does not promise or grant credits. */
  public function status(): array {
    $uid = $this->actor(['view own credits']);
    $settings = $this->config->get('xinshi_rewards.settings');
    if (!$settings->get('daily_enabled') || !$this->config->get('xinshi_credits.settings')->get('grants_enabled')) {
      return ['status' => 'paused'];
    }
    if (!$this->currentUser->hasPermission('receive daily credits')) return ['status' => 'not_eligible'];
    [$date, $next, $zone] = $this->calendar();
    $id = $this->identity([$this->context->site(), $uid, 'G11', $date]);
    $base = ['business_date' => $date, 'next_business_date_at' => gmdate('c', $next), 'business_timezone' => $zone];
    if ($claim = $this->claim($id)) {
      $grant = $this->database->select('credit_grant', 'g')->fields('g', ['granted_atoms', 'scope_json', 'expires_at'])
        ->condition('journal_id', $claim['journal_id'])->execute()->fetchAssoc();
      if (!$grant) throw new CreditException('reward_unavailable', 503);
      return $base + ['status' => 'already_granted', 'journal_id' => $claim['journal_id'],
        'amount_atoms' => (string) $grant['granted_atoms'], 'scope' => json_decode($grant['scope_json'], TRUE, 16, JSON_THROW_ON_ERROR),
        'expires_at' => gmdate('c', (int) $grant['expires_at'])];
    }
    $pool = $this->pool('G11');
    return $base + ['status' => (int) $pool['cap_atoms'] - (int) $pool['issued_atoms'] < (int) $pool['policy']['grant_limit_atoms']
      ? 'budget_closed' : 'eligible', 'amount_atoms' => $pool['policy']['grant_limit_atoms'],
      'scope' => array_keys($pool['bounds']), 'expires_at' => gmdate('c', $pool['policy']['expires_at'])];
  }

  /** Admit the first active visit of a server business date, independent of rule version. */
  public function daily(string $clientKey): array {
    $uid = $this->actor(['view own credits']);
    [$key, $hash] = $this->requestIdentity('daily', $uid, $clientKey, []);
    if ($previous = $this->previous($key, $hash)) return $previous;
    $this->throttle('daily', $uid);
    if (!$this->currentUser->hasPermission('receive daily credits')) return ['status' => 'not_eligible'];
    [$date, , $zone] = $this->calendar();
    $id = $this->identity([$this->context->site(), $uid, 'G11', $date]);
    if ($this->claim($id)) return $this->alias($key, $hash, $id, 'already_granted');
    if (!$this->config->get('xinshi_rewards.settings')->get('daily_enabled')
      || !$this->config->get('xinshi_credits.settings')->get('grants_enabled')) return ['status' => 'paused'];
    $pool = $this->pool('G11');
    $command = $this->command($uid, $uid, $pool, $id, $pool['policy']['grant_limit_atoms'],
      array_keys($pool['bounds']), $pool['policy']['expires_at'], 'Daily active-visit reward');
    try {
      $this->issue($command, $id, $key, $hash, $date, $zone);
      return $this->previous($key, $hash) ?? $this->alias($key, $hash, $id, 'already_granted');
    }
    catch (\Throwable $error) {
      // A concurrent device or version may win while this request waits for ledger locks.
      if ($previous = $this->previous($key, $hash)) return $previous;
      if ($this->claim($id)) return $this->alias($key, $hash, $id, 'already_granted');
      if ($error instanceof CreditException && $error->reason === 'reward_budget_exhausted') return ['status' => 'budget_closed'];
      throw $error;
    }
  }

  /** Validate administrator input before review and again before financial admission. */
  public function normalizeGift(array $input): array {
    CreditValue::fields($input, ['beneficiary_user_id', 'amount_atoms', 'budget_pool_id',
      'scope', 'expires_at', 'public_reason'], ['internal_note']);
    $result = ['beneficiary_user_id' => (string) CreditValue::amount($input['beneficiary_user_id'], TRUE),
      'amount_atoms' => (string) CreditValue::amount($input['amount_atoms'], TRUE),
      'budget_pool_id' => CreditValue::id($input['budget_pool_id']), 'scope' => CreditValue::scope($input['scope']),
      'expires_at' => $input['expires_at'], 'public_reason' => CreditValue::text($input['public_reason']),
      'internal_note' => CreditValue::text($input['internal_note'] ?? '', 1000, TRUE)];
    if (!is_int($result['expires_at']) || $result['expires_at'] <= 0) throw new CreditException('invalid_expiry');
    return $result;
  }

  /** Give an explicit amount within the published administrator authorization. */
  public function gift(array $input, string $clientKey): array {
    $uid = $this->actor(['view user credits', 'grant user credits']);
    $input = $this->normalizeGift($input);
    $this->target($input['beneficiary_user_id']);
    [$key, $hash] = $this->requestIdentity('gift', $uid, $clientKey, $input);
    if ($previous = $this->previous($key, $hash)) return $previous;
    $this->throttle('gift', $uid);
    if (!$this->config->get('xinshi_rewards.settings')->get('admin_gifts_enabled')) {
      throw new CreditException('admin_gifts_disabled', 503);
    }
    $pool = $this->pool('G12', $input['budget_pool_id']);
    $id = $this->identity(['gift', $key]);
    $command = $this->command($input['beneficiary_user_id'], $uid, $pool, $id, $input['amount_atoms'],
      $input['scope'], $input['expires_at'], $input['public_reason']);
    $command['internal_note'] = $input['internal_note'];
    try {
      $this->issue($command, $id, $key, $hash, '', NULL);
    }
    catch (\Throwable $error) {
      if ($previous = $this->previous($key, $hash)) return $previous;
      throw $error;
    }
    return $this->previous($key, $hash) ?? throw new CreditException('reward_unavailable', 503);
  }

  /** Read the active G12 limits for the gift review form, without cost evidence. */
  public function giftPolicy(string $target): array {
    $this->actor(['view user credits', 'grant user credits']);
    $this->target($target);
    $pool = $this->pool('G12');
    return ['budget_pool_id' => $pool['id'], 'rule_version' => $pool['rule_version'],
      'grant_limit_atoms' => $pool['policy']['grant_limit_atoms'],
      'remaining_atoms' => (string) ((int) $pool['cap_atoms'] - (int) $pool['issued_atoms']),
      'scope' => array_keys($pool['bounds']), 'expires_at' => $pool['policy']['expires_at']];
  }

  private function issue(array $command, string $id, string $key, string $hash, string $date, ?string $zone): void {
    $this->ledger->grant($command, 'reward.' . $id,
      function (string $journalId, string $grantId, int $now) use ($command, $id, $key, $hash, $date, $zone): void {
        if ($zone !== NULL) {
          // This permanent lock follows account and budget locks; no reverse acquisition exists.
          $calendar = $this->database->select('credit_reward_calendar', 'c')->fields('c')
            ->condition('id', 1)->forUpdate()->execute()->fetchAssoc();
          if (!$calendar || ($calendar['timezone'] !== '' && $calendar['timezone'] !== $zone)) {
            throw new CreditException('reward_calendar_changed', 503);
          }
          $this->database->update('credit_reward_calendar')->fields(['timezone' => $zone])
            ->expression('generation', 'generation + 1')->condition('id', 1)->execute();
        }
        $this->database->insert('credit_reward_claim')->fields(['id' => $id, 'site_id' => $this->context->site(),
          'beneficiary_user_id' => $command['beneficiary_user_id'], 'rule_id' => $command['rule_id'],
          'rule_version' => $command['rule_version'], 'business_date' => $date,
          'journal_id' => $journalId, 'created_at' => $now])->execute();
        $this->database->insert('credit_reward_request')->fields(['id' => $key, 'request_hash' => $hash,
          'claim_id' => $id, 'outcome' => 'granted'])->execute();
      });
  }

  private function command(string $target, string $actor, array $pool, string $source, string $amount, array $scope, int $expires, string $reason): array {
    return ['beneficiary_user_id' => $target, 'actor_user_id' => $actor, 'amount_atoms' => $amount,
      'source_kind' => $pool['policy']['source_kind'], 'source_ref' => $source,
      'grant_kind' => $pool['rule_id'], 'rule_id' => $pool['rule_id'], 'rule_version' => $pool['rule_version'],
      'budget_pool_id' => $pool['id'], 'scope' => $scope, 'expires_at' => $expires, 'public_reason' => $reason];
  }

  private function pool(string $rule, ?string $id = NULL): array {
    $now = $this->time->getCurrentTime();
    $query = $this->database->select('credit_reward_pool', 'p')->fields('p')
      ->condition('site_id', $this->context->site())->condition('rule_id', $rule)->isNotNull('publication_ref')
      ->condition('period_start', $now, '<=')->condition('period_end', $now, '>');
    if ($id !== NULL) $query->condition('id', $id);
    $rows = $query->range(0, 2)->execute()->fetchAll(\PDO::FETCH_ASSOC);
    if (count($rows) !== 1) throw new CreditException('rewards_not_configured', 503);
    $pool = $rows[0];
    $pool['policy'] = json_decode($pool['grant_policy_json'], TRUE, 16, JSON_THROW_ON_ERROR);
    $pool['bounds'] = json_decode($pool['bounds_json'], TRUE, 16, JSON_THROW_ON_ERROR);
    if ($pool['policy']['mode'] !== ($rule === 'G11' ? 'fixed' : 'variable')
      || $pool['policy']['source_kind'] !== ($rule === 'G11' ? 'promotion' : 'admin_gift')
      || $pool['policy']['expires_at'] <= $now) throw new CreditException('rewards_not_configured', 503);
    return $pool;
  }

  private function calendar(): array {
    $zone = $this->config->get('xinshi_rewards.settings')->get('business_timezone');
    if (!is_string($zone) || !in_array($zone, \DateTimeZone::listIdentifiers(\DateTimeZone::ALL_WITH_BC), TRUE)) {
      throw new CreditException('rewards_not_configured', 503);
    }
    $bound = $this->database->select('credit_reward_calendar', 'c')->fields('c', ['timezone'])
      ->condition('id', 1)->execute()->fetchField();
    if ($bound === FALSE || ($bound !== '' && $bound !== $zone)) throw new CreditException('reward_calendar_changed', 503);
    $date = (new \DateTimeImmutable('@' . $this->time->getCurrentTime()))->setTimezone(new \DateTimeZone($zone));
    return [$date->format('Y-m-d'), $date->modify('tomorrow')->setTime(0, 0)->getTimestamp(), $zone];
  }

  private function actor(array $permissions): string {
    if (!$this->currentUser->isAuthenticated()) throw new CreditException('permission_denied', 403);
    foreach ($permissions as $permission) {
      if (!$this->currentUser->hasPermission($permission)) throw new CreditException('permission_denied', 403);
    }
    $user = $this->entities->getStorage('user')->load($this->currentUser->id());
    if (!$user || !$user->isActive()) throw new CreditException('permission_denied', 403);
    return (string) $this->currentUser->id();
  }

  private function target(string $id): void {
    CreditValue::amount($id, TRUE);
    $user = $this->entities->getStorage('user')->load($id);
    if (!$user || !$user->isActive() || !$user->access('update', $this->currentUser)) {
      throw new CreditException('permission_denied', 403);
    }
  }

  private function requestIdentity(string $action, string $actor, string $key, array $input): array {
    return [$this->identity([$this->context->site(), $actor, $action, CreditValue::id($key)]), $this->identity($input)];
  }

  private function identity(array $parts): string {
    return hash('sha256', json_encode($parts, JSON_THROW_ON_ERROR));
  }

  private function claim(string $id): array|false {
    return $this->database->select('credit_reward_claim', 'c')->fields('c')->condition('id', $id)
      ->condition('site_id', $this->context->site())->execute()->fetchAssoc();
  }

  private function previous(string $key, string $hash): ?array {
    $request = $this->database->select('credit_reward_request', 'r')->fields('r')->condition('id', $key)->execute()->fetchAssoc();
    if (!$request) return NULL;
    if (!hash_equals($request['request_hash'], $hash)) throw new CreditException('idempotency_conflict', 409);
    $claim = $this->claim($request['claim_id']);
    if (!$claim) throw new CreditException('reward_unavailable', 503);
    $receipt = $this->database->select('credit_journal', 'j')->fields('j', ['receipt_json'])
      ->condition('id', $claim['journal_id'])->condition('site_id', $claim['site_id'])->execute()->fetchField();
    if (!$receipt) throw new CreditException('reward_unavailable', 503);
    return ['status' => $request['outcome'], 'business_date' => $claim['business_date'],
      'receipt' => json_decode($receipt, TRUE, 32, JSON_THROW_ON_ERROR)];
  }

  private function alias(string $key, string $hash, string $id, string $outcome): array {
    try {
      $this->database->insert('credit_reward_request')->fields(['id' => $key, 'request_hash' => $hash,
        'claim_id' => $id, 'outcome' => $outcome])->execute();
    }
    catch (\Throwable $error) {
      if ($previous = $this->previous($key, $hash)) return $previous;
      throw $error;
    }
    return $this->previous($key, $hash) ?? throw new CreditException('reward_unavailable', 503);
  }

  private function throttle(string $action, string $actor): void {
    $name = 'xinshi_rewards.' . $action;
    $identity = $this->context->site() . ':' . $actor;
    if (!$this->flood->isAllowed($name, 60, 60, $identity)) throw new CreditException('reward_rate_limited', 429);
    $this->flood->register($name, 60, $identity);
  }

}
