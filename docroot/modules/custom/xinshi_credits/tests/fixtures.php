<?php

declare(strict_types=1);

/** Deterministic lifecycle time without editing persisted grants. */
final class FixtureClock implements \Drupal\Component\Datetime\TimeInterface {
  public function __construct(public int $now) {}
  public function getRequestTime(): int { return $this->now; }
  public function getRequestMicroTime(): float { return (float) $this->now; }
  public function getCurrentTime(): int { return $this->now; }
  public function getCurrentMicroTime(): float { return (float) $this->now; }
}

function pool(string $name, string $cap = '1000000', string $costCap = '1000000'): string {
  $db = \Drupal::database(); $uuid = \Drupal::service('uuid'); $now = time();
  $bounds = [];
  foreach (['chat', 'image'] as $category) {
    $id = $uuid->generate();
    $db->insert('credit_budget_bucket')->fields(['id' => $id, 'site_id' => 'fixture',
      'category' => $category, 'currency' => 'CNY', 'period_start' => $now - 60,
      'period_end' => $now + 86400, 'cap_micros' => $costCap,
      'source_ref' => 'synthetic-cost-evidence', 'created_by' => '1', 'created_at' => $now])->execute();
    $bounds[$category] = ['bucket_id' => $id, 'micros_per_point' => '5000'];
  }
  $id = $uuid->generate();
  $db->insert('credit_reward_pool')->fields(['id' => $id, 'site_id' => 'fixture',
    'rule_id' => $name, 'rule_version' => 'v1', 'period_start' => $now - 60,
    'period_end' => $now + 86400, 'cap_atoms' => $cap, 'bounds_json' => json_encode($bounds),
    'source_ref' => 'synthetic-rule-evidence', 'created_by' => '1', 'created_at' => $now])->execute();
  return $id;
}
function command(string $pool, string $rule, string $ref, string $user, string $atoms = '1000'): array {
  return ['beneficiary_user_id' => $user, 'actor_user_id' => '1', 'amount_atoms' => $atoms,
    'source_kind' => 'admin_gift', 'source_ref' => $ref, 'grant_kind' => 'G12',
    'rule_id' => $rule, 'rule_version' => 'v1', 'budget_pool_id' => $pool,
    'scope' => ['chat', 'image'], 'expires_at' => time() + 3600,
    'public_reason' => 'Synthetic grant', 'internal_note' => 'PRIVATE AUDIT NOTE'];
}
function snapshot(): array {
  $tables = ['credit_account', 'credit_budget_bucket', 'credit_reward_pool', 'credit_grant',
    'credit_journal', 'credit_posting', 'credit_notification_outbox', 'credit_grant_cost',
    'credit_budget_posting', 'credit_reservation', 'credit_allocation', 'credit_publication_guard', 'credit_pool_allocation'];
  $state = [];
  foreach ($tables as $table) $state[$table] = \Drupal::database()->select($table, 't')->fields('t')
    ->execute()->fetchAll(PDO::FETCH_ASSOC);
  return $state;
}
