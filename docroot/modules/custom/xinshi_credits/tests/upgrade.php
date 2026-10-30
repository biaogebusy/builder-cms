<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/fixtures.php';
require_once '/app/docroot/modules/custom/xinshi_credits/xinshi_credits.install';

// Recreate the precise missing columns and tables of the CR2a installation on synthetic data.
$fixtureData = json_decode(file_get_contents($fixture . '/fixtures.json'), TRUE);
$upgradePool = pool('upgrade-batches');
$upgradeUser = \Drupal\user\Entity\User::create(['name' => 'upgrade-batches', 'status' => 1]); $upgradeUser->save();
for ($n = 0; $n < 101; $n++) {
  \Drupal::service('xinshi_credits.ledger')->grant(
    command($upgradePool, 'upgrade-batches', 'old-' . $n, (string) $upgradeUser->id(), '1'), 'old-upgrade-' . $n);
}
$creditsConfig = \Drupal::configFactory()->getEditable('xinshi_credits.settings');
$rejected = FALSE; $sandbox = [];
try { xinshi_credits_update_11001($sandbox); }
catch (\Drupal\Core\Utility\UpdateException) { $rejected = TRUE; }
check($rejected, 'Upgrade refuses to run while new grants are enabled');
$creditsConfig->set('grants_enabled', FALSE)->clear('spending_enabled')->save();
$receipts = $db->select('credit_journal', 'j')->fields('j', ['id', 'receipt_json'])->execute()->fetchAllKeyed();
$oldFields = [
  'credit_journal' => ['held_delta_atoms', 'held_after_atoms', 'refund_locked_delta_atoms', 'refund_locked_after_atoms', 'operation_id', 'reverses_journal_id', 'receipt_json'],
  'credit_grant' => ['consumed_atoms', 'expired_atoms'],
  'credit_budget_bucket' => ['opening_committed_micros', 'opening_reserved_micros', 'opening_actual_micros'],
];
foreach ($oldFields as $table => $fields) foreach ($fields as $field) $db->schema()->dropField($table, $field);
$db->schema()->dropIndex('credit_grant', 'site_expiry');
foreach (['credit_allocation', 'credit_reservation', 'credit_budget_posting', 'credit_grant_cost'] as $table) $db->schema()->dropTable($table);
$bucket = $db->select('credit_budget_bucket', 'b')->fields('b', ['id'])->condition('cap_micros', 1000000)->range(0, 1)->execute()->fetchField();
$db->update('credit_budget_bucket')->fields(['actual_cost_micros' => 73, 'reserved_cost_micros' => 29])->condition('id', $bucket)->execute();
$before = [];
foreach (['credit_account', 'credit_journal', 'credit_grant', 'credit_budget_bucket'] as $table) {
  $before[$table] = $db->select($table, 't')->fields('t')->execute()->fetchAll(PDO::FETCH_ASSOC);
}
$sandbox = []; $batches = 0;
do { xinshi_credits_update_11001($sandbox); $batches++; } while ($sandbox['#finished'] < 1);
check($batches > 1, 'Upgrade resumes after its bounded journal batch');
check($db->schema()->indexExists('credit_grant', 'site_expiry'), 'Upgrade adds the bounded expiry scan index');
foreach ($before as $table => $rows) {
  $fields = array_keys($rows[0]);
  check($db->select($table, 't')->fields('t', $fields)->execute()->fetchAll(PDO::FETCH_ASSOC) === $rows, 'Upgrade preserves all existing fields: ' . $table);
}
check($db->select('credit_journal', 'j')->fields('j', ['id', 'receipt_json'])->execute()->fetchAllKeyed() === $receipts, 'Upgrade recreates the exact original grant receipts');
check(\Drupal::service('xinshi_credits.ledger')->grant($fixtureData['first_grant'], 'key-1') === $fixtureData['first_receipt'], 'Original idempotency key still returns the original receipt while paused');
check(\Drupal::config('xinshi_credits.settings')->get('spending_enabled') === FALSE, 'Upgrade defaults new spending to disabled');
$costCount = $db->select('credit_grant_cost', 'c')->countQuery()->execute()->fetchField();
$movementCount = $db->select('credit_budget_posting', 'p')->countQuery()->execute()->fetchField();
$sandbox = [];
do { xinshi_credits_update_11001($sandbox); } while ($sandbox['#finished'] < 1);
check($costCount == $db->select('credit_grant_cost', 'c')->countQuery()->execute()->fetchField()
  && $movementCount == $db->select('credit_budget_posting', 'p')->countQuery()->execute()->fetchField(), 'Repeated upgrade creates no duplicate cost records');
check(\Drupal::service('xinshi_credits.audit')->inspect($fixtureData['alice'])['status'] === 'complete', 'Upgraded balances and cost openings reconstruct exactly');
check(\Drupal::service('xinshi_credits.audit')->inspect((string) $upgradeUser->id())['status'] === 'complete', 'Multi-batch upgrade reconstructs every original grant');
$creditsConfig->set('grants_enabled', TRUE)->save();
echo 'Upgrade checks: ' . $checks . PHP_EOL;
