<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/fixtures.php';
require_once '/app/docroot/modules/custom/xinshi_credits/xinshi_credits.install';

$upgradeSettings = \Drupal::configFactory()->getEditable('xinshi_credits.settings');
$upgradeSettings->set('grants_enabled', TRUE)->save();
try { xinshi_credits_update_11004(); throw new RuntimeException('Expected paused upgrade'); }
catch (\Drupal\Core\Utility\UpdateException) { check(TRUE, 'Points schema upgrade requires paused issuance and spending'); }
$upgradeSettings->set('grants_enabled', FALSE)->set('spending_enabled', FALSE)->save();
$before = snapshot();
foreach (['credit_reward_pool', 'credit_grant', 'credit_reservation'] as $table) {
  check(!$db->select($table, 't')->condition('accounting_mode', 'budgeted', '<>')->countQuery()->execute()->fetchField(), 'Upgrade fixture contains original budgeted records: ' . $table);
  $db->schema()->dropField($table, 'accounting_mode');
}
$db->schema()->dropField('credit_reward_pool', 'scope_json');
foreach (['cost_bucket_id', 'cost_currency', 'cost_limit_micros', 'actual_cost_micros'] as $field) {
  $definition = xinshi_credits_schema()['credit_reservation']['fields'][$field];
  $definition['not null'] = TRUE;
  $db->schema()->changeField('credit_reservation', $field, $field, $definition);
}
xinshi_credits_update_11004();
xinshi_credits_update_11004();
$after = snapshot();
foreach ($before as $table => $rows) {
  foreach ($rows as &$row) ksort($row); unset($row);
  foreach ($after[$table] as &$row) ksort($row); unset($row);
  check($after[$table] === $rows, 'Repeated points upgrade preserves original rows and receipts: ' . $table);
}
$upgradeSettings->set('grants_enabled', TRUE)->set('spending_enabled', TRUE)->save();
echo 'Points upgrade checks: ' . $checks . PHP_EOL;
