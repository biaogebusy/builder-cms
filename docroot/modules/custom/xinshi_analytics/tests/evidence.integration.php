<?php

/**
 * @file
 * Runs inside the isolated integration entry after its source fixtures exist.
 */

use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\User;
use Drupal\xinshi_analytics\Entity\AnalyticsDataset;

if (!isset($counts, $reader, $role) || getenv('XINSHI_ANALYTICS_ISOLATED_TEST') !== '1') {
  throw new RuntimeException('Use integration.php through the disposable runner.');
}
$beforeChecks = $GLOBALS['checks'];
NodeType::create(['type' => 'evidence_record', 'name' => 'Evidence record'])->save();
$dataset = AnalyticsDataset::create(definition('evidence_activity', 'node', 'evidence_record'));
$dataset->save();
$role->grantPermission($dataset->queryPermission())->save();
$first = node('evidence_record', $reader->id());
$second = node('evidence_record', $reader->id());
$third = node('evidence_record', $reader->id());
$outsider = User::create(['name' => 'evidence-other', 'status' => 1, 'roles' => ['analytics_reader']]);
$outsider->save();
node('evidence_record', $outsider->id());
node('evidence_record', $outsider->id());
node_access_rebuild();
$evidence = \Drupal::service('xinshi_analytics.evidence');
$input = query('evidence_activity');
asAccount($reader);
$saved = $evidence->capture($input);
check($saved['version'] === 2 && preg_match('/^[a-f0-9]{64}$/D', $saved['receipt']) === 1,
  'Evidence has a bounded opaque receipt without record identifiers');
check($saved['result']['rows'] === rows($input), 'Evidence uses the ordinary count semantics');
check($evidence->verify($saved) === ['valid' => TRUE], 'Unchanged evidence verifies');
$reordered = $saved;
$reordered['result'] = array_reverse($reordered['result'], TRUE);
$reordered['result']['query'] = array_reverse($input, TRUE);
check($evidence->verify($reordered) === ['valid' => TRUE], 'JSON key order does not change evidence identity');
foreach (['count', 'time', 'receipt', 'query'] as $tamper) {
  $changed = $saved;
  match ($tamper) {
    'count' => $changed['result']['rows'][0]['count']++,
    'time' => $changed['result']['startedAt'] = '2020-01-01T00:00:00Z',
    'receipt' => $changed['receipt'] = str_repeat('0', 64),
    'query' => $changed['result']['query']['scope']['from'] = '2026-01-02T00:00:00Z',
  };
  denied(fn() => $evidence->verify($changed), 'evidence_unavailable', 'Receipt binds original ' . $tamper);
}
asAccount($outsider);
denied(fn() => $evidence->verify($saved), 'evidence_unavailable', 'A receipt cannot transfer data to another account');
asAccount($reader);

// Access hooks can change without an entity save. Equal totals cannot prove equal scope.
\Drupal::state()->set('analytics_test.denied_entity', ['node', $first->id()]);
asAccount($reader);
$limited = $evidence->capture($input);
\Drupal::state()->set('analytics_test.denied_entity', ['node', $second->id()]);
asAccount($reader);
check($limited['result']['rows'] === rows($input), 'The changed access fixture has the same count');
denied(fn() => $evidence->verify($limited), 'evidence_unavailable', 'Equal totals with different visible records reject old evidence');
\Drupal::state()->delete('analytics_test.denied_entity');
asAccount($reader);

$saved = $evidence->capture($input);
$first->setTitle('Edited without affecting the count')->save();
asAccount($reader);
check(rows($input) === $saved['result']['rows'], 'An entity edit can leave all aggregate rows unchanged');
denied(fn() => $evidence->verify($saved), 'evidence_unavailable', 'Source saves invalidate unchanged totals');
$first->setTitle('Synthetic record')->save();
asAccount($reader);
denied(fn() => $evidence->verify($saved), 'evidence_unavailable', 'Restoring original source data does not revive old evidence');
$fresh = $evidence->capture($input);
check($evidence->verify($fresh) === ['valid' => TRUE], 'Reanalysis creates a valid new receipt');
node('article', $reader->id());
asAccount($reader);
check($evidence->verify($fresh) === ['valid' => TRUE], 'Saving a different bundle does not invalidate the source');

\Drupal::state()->set('analytics_test.invalidate_during_scan', TRUE);
asAccount($reader);
denied(fn() => $evidence->capture($input), 'query_failed', 'No evidence is issued across a policy change during scanning');
asAccount($reader);
denied(fn() => $evidence->verify($fresh), 'evidence_unavailable', 'An observed scan-time policy change invalidates earlier evidence');
$fresh = $evidence->capture($input);

// Direct database changes bypass save hooks, but participating values are still checked.
\Drupal::database()->update('node_field_data')->fields(['created' => strtotime('2026-01-11 UTC')])
  ->condition('nid', $first->id())->execute();
asAccount($reader);
check(rows($input) === $fresh['result']['rows'], 'A changed timestamp within a month keeps the count');
denied(fn() => $evidence->verify($fresh), 'evidence_unavailable', 'Participating field fingerprint catches an unannounced data change');

$fresh = $evidence->capture($input);
\Drupal::state()->set('analytics_test.denied_field', 'created');
asAccount($reader);
denied(fn() => $evidence->verify($fresh), 'evidence_unavailable', 'Current field denial prevents old evidence delivery');
\Drupal::state()->delete('analytics_test.denied_field');
asAccount($reader);
\Drupal::service('xinshi_analytics.evidence_epochs')->invalidate();
denied(fn() => $evidence->verify($fresh), 'evidence_unavailable', 'External policy invalidation is durable after access restoration');

$fresh = $evidence->capture($input);
$role->revokePermission($dataset->queryPermission())->save();
asAccount($reader);
denied(fn() => $evidence->verify($fresh), 'evidence_unavailable', 'Dataset permission revocation invalidates evidence');
$role->grantPermission($dataset->queryPermission())->save();
asAccount($reader);
denied(fn() => $evidence->verify($fresh), 'evidence_unavailable', 'Restoring a role does not revive old evidence');
$fresh = $evidence->capture($input);
$reader->setUsername('reader-renamed')->save();
asAccount($reader);
denied(fn() => $evidence->verify($fresh), 'evidence_unavailable', 'Account saves invalidate its evidence');
$fresh = $evidence->capture($input);
$dataset->disable()->save();
denied(fn() => $evidence->verify($fresh), 'evidence_unavailable', 'Disabled datasets cannot verify evidence');
$dataset->enable()->save();
denied(fn() => $evidence->verify($fresh), 'evidence_unavailable', 'Reenabled datasets require new evidence');
$fresh = $evidence->capture($input);
$dataset->set('version', 2)->set('label', 'New definition')->save();
denied(fn() => $evidence->verify($fresh), 'evidence_unavailable', 'Dataset versions cannot silently migrate evidence');
$input['datasetVersion'] = 2;
$fresh = $evidence->capture($input);
$dataset->delete();
denied(fn() => $evidence->verify($fresh), 'evidence_unavailable', 'Deleted datasets reject evidence');
check($saved['result']['rows'][0]['count'] === 3, 'Verification never rewrites the historical result');
echo 'PASS: ' . ($GLOBALS['checks'] - $beforeChecks) . ' evidence integration checks' . PHP_EOL;
