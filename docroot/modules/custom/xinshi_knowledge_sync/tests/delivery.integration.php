<?php

declare(strict_types=1);

/** Verify persistence and authorization in the disposable Drupal site, not mocks. */

use Drupal\Core\Session\AnonymousUserSession;
use Drupal\search_api\Entity\Index;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\xinshi_knowledge_sync\Service\DeliveryExecution;
use Drupal\xinshi_knowledge_sync\Service\SourceAccess;
use Symfony\Component\Process\Process;

$delivery = Drupal::service('xinshi_knowledge_sync.delivery');
$deliveryAdmin = User::load(1);
$settings = Drupal::configFactory()->getEditable('xinshi_knowledge_sync.settings');
$sourceList = $settings->get('sources');
$sourceList[] = ['id' => 'delivery-test', 'enabled' => TRUE, 'default_policy' => 'readers',
  'policies' => [['id' => 'readers', 'all_readers' => TRUE, 'roles' => [], 'users' => []]], 'rules' => []];
$settings->set('sources', $sourceList)->save();
$encode = static fn(array $manifest): string => json_encode($manifest, JSON_THROW_ON_ERROR);
$deliveryManifest = snapshot('delivery-test', ['one.md', 'two.md']);
$deliveryManifest['revision'] = str_repeat('a', 40);
$bytes = $encode($deliveryManifest);
$request = static fn(string $action, string $snapshot, ?string $parent = NULL, int $limit = 0): array => [
  'version' => 1, 'id' => Drupal::service('uuid')->generate(), 'action' => $action,
  'source' => 'delivery-test', 'language' => 'en', 'revision' => str_repeat('a', 40),
  'snapshotSha256' => hash('sha256', $snapshot), 'sourceConfigSha256' => str_repeat('c', 64),
  'maxUnpublished' => $limit, 'parentId' => $parent,
];
// Upgrade the actual prior table shape while preserving a bound historical failure.
Drupal::moduleHandler()->loadInclude('xinshi_knowledge_sync', 'install');
$legacyRequest = $request('preview', $bytes);
$legacyHash = hash('sha256', json_encode([$legacyRequest, 1], JSON_THROW_ON_ERROR));
Drupal::database()->insert(DeliveryExecution::TABLE)->fields([
  'task_id' => $legacyRequest['id'], 'request_hash' => $legacyHash,
  'request' => json_encode($legacyRequest), 'account_uid' => 1, 'status' => 'failed',
  'result' => '{}', 'error' => 'delivery_execution_failed', 'created' => 1, 'finished' => 2,
])->execute();
foreach (['guard_hash', 'reconciled_by', 'reconciled'] as $field) {
  Drupal::database()->schema()->dropField(DeliveryExecution::TABLE, $field);
}
xinshi_knowledge_sync_update_11002();
xinshi_knowledge_sync_update_11002();
check($delivery->receipt($legacyRequest['id'], $deliveryAdmin)['requestSha256'] === $legacyHash &&
  $delivery->receipt($legacyRequest['id'], $deliveryAdmin)['error'] === 'delivery_execution_failed',
  'Receipt upgrade changed a historical binding or failure.');
check(Drupal::database()->select(DeliveryExecution::TABLE, 'r')->fields('r', ['guard_hash'])
  ->condition('task_id', $legacyRequest['id'])->execute()->fetchField() === '',
  'Receipt upgrade invented historical process-guard evidence.');
$state = static function (): string {
  return hash('sha256', serialize([
    Drupal::database()->select(SourceAccess::TABLE, 'd')->fields('d')->condition('source', 'delivery-test')
      ->orderBy('document_key')->execute()->fetchAll(PDO::FETCH_ASSOC),
    Drupal::database()->select('node_revision', 'r')->fields('r')->orderBy('vid')->execute()->fetchAll(PDO::FETCH_ASSOC),
  ]));
};
rejects(fn() => $delivery->execute($request('preview', $bytes), $bytes, new AnonymousUserSession()), 'import_forbidden');
rejects(fn() => $delivery->reconcile($request('preview', $bytes), 1, new AnonymousUserSession()), 'delivery_recovery_forbidden');
$previewRequest = $request('preview', $bytes);
rejects(fn() => $delivery->execute([...$previewRequest, 'arbitrary' => TRUE], $bytes, $deliveryAdmin), 'invalid_delivery_request');
rejects(fn() => $delivery->execute([...$previewRequest, 'maxUnpublished' => '0'], $bytes, $deliveryAdmin), 'invalid_delivery_request');
rejects(fn() => $delivery->execute($previewRequest, $bytes . ' ', $deliveryAdmin), 'delivery_snapshot_mismatch');
$before = $state();
$preview = $delivery->execute($previewRequest, $bytes, $deliveryAdmin);
check($preview['status'] === 'succeeded' && $preview['result']['stats']['created'] === 2, 'Delivery preview failed.');
check($before === $state(), 'Delivery preview wrote content.');
check($preview === $delivery->execute(array_reverse($previewRequest, TRUE), $bytes, $deliveryAdmin), 'Reordered replay changed the receipt.');
rejects(fn() => $delivery->execute([...$previewRequest, 'sourceConfigSha256' => str_repeat('d', 64)], $bytes, $deliveryAdmin), 'delivery_request_conflict');
$importRequest = $request('import', $bytes, $preview['id']);
rejects(fn() => $delivery->execute([...$importRequest, 'parentId' => Drupal::service('uuid')->generate()], $bytes, $deliveryAdmin), 'delivery_parent_mismatch');
$applied = $delivery->execute($importRequest, $bytes, $deliveryAdmin);
check($applied['status'] === 'succeeded' && $applied['result']['stats']['created'] === 2 && $applied['result']['stats']['applied'], 'Delivery import failed.');
$importedState = $state();
check($applied === $delivery->execute($importRequest, $bytes, $deliveryAdmin), 'Import replay changed the result.');
check($importedState === $state(), 'Repeated delivery import made revisions.');
check($delivery->receipt($applied['id'], $deliveryAdmin) === $applied, 'Original receipt could not be queried.');
check(!str_contains(json_encode($applied), 'Quasarengine'), 'Receipt copied protected document text.');

// A separate Drush process proves the receipt survives service and process lifetimes.
$siteRoot = dirname(Drupal::root());
$cliReceipt = new Process([$siteRoot . '/vendor/bin/drush', '--no-ansi', 'xinshi-knowledge:delivery-receipt', $applied['id'], '--account=1'], $siteRoot, timeout: 30);
$cliReceipt->mustRun();
check(json_decode(trim($cliReceipt->getOutput()), TRUE, 16, JSON_THROW_ON_ERROR)['receipt'] === $applied, 'Fresh Drush process lost the receipt.');
file_put_contents($siteRoot . '/delivery-request.json', json_encode($previewRequest));
file_put_contents($siteRoot . '/delivery-snapshot.json', $bytes);
$cliExecution = new Process([$siteRoot . '/vendor/bin/drush', '--no-ansi', 'xinshi-knowledge:delivery', './delivery-request.json', './delivery-snapshot.json', '--account=1'], $siteRoot, timeout: 30);
$cliExecution->mustRun();
check(json_decode(trim($cliExecution->getOutput()), TRUE, 16, JSON_THROW_ON_ERROR)['receipt'] === $preview, 'Drush replay did not return the original preview.');
check($importedState === $state(), 'Drush replay changed imported content.');
$cmsId = Drupal::config('system.site')->get('uuid');
$boundReceipt = new Process([$siteRoot . '/vendor/bin/drush', '--no-ansi', 'xinshi-knowledge:delivery-receipt', $applied['id'], '--account=1', '--site-uuid=' . $cmsId], $siteRoot, timeout: 30);
$boundReceipt->mustRun();
$envelope = json_decode(trim($boundReceipt->getOutput()), TRUE, 16, JSON_THROW_ON_ERROR);
check($envelope['cmsId'] === $cmsId && $envelope['receipt'] === $applied, 'Pinned CMS receipt identity changed.');
$wrongSite = new Process([$siteRoot . '/vendor/bin/drush', '--no-ansi', 'xinshi-knowledge:delivery', './delivery-request.json', './delivery-snapshot.json', '--account=1', '--site-uuid=' . Drupal::service('uuid')->generate()], $siteRoot, timeout: 30);
$wrongSite->run();
check(!$wrongSite->isSuccessful() && str_contains($wrongSite->getErrorOutput(), 'delivery_site_mismatch'), 'Wrong CMS identity was accepted.');
check($importedState === $state(), 'Rejected CMS identity changed content.');

// Index failures do not reopen the import stage or repeat node writes.
$index = Index::load('xinshi_knowledge');
$index->disable()->save();
$indexRequest = $request('index', $bytes, $applied['id']);
$indexFailed = $delivery->execute($indexRequest, $bytes, $deliveryAdmin);
check($indexFailed['status'] === 'failed' && $indexFailed['error'] === 'delivery_index_unavailable', 'Disabled index was reported as successful.');
check($importedState === $state() && $delivery->receipt($applied['id'], $deliveryAdmin)['status'] === 'succeeded', 'Index failure changed the import.');
$index->enable()->save();
$indexed = $delivery->execute($request('index', $bytes, $applied['id']), $bytes, $deliveryAdmin);
check($indexed['status'] === 'succeeded' && $indexed['result']['index']['remaining'] === 0, 'Independent indexing recovery failed.');
check($importedState === $state(), 'Index recovery imported content again.');
$index->set('read_only', TRUE)->save();
$readOnly = $delivery->execute($request('index', $bytes, $applied['id']), $bytes, $deliveryAdmin);
check($readOnly['status'] === 'failed' && $readOnly['error'] === 'delivery_index_unavailable', 'Read-only index was reported as available.');
$index->set('read_only', FALSE)->save();

// The lower limit is checked under the import lock, before the first node save.
$smaller = $deliveryManifest;
array_pop($smaller['documents']);
$smallerBytes = $encode($smaller);
$limited = $delivery->execute($request('preview', $smallerBytes), $smallerBytes, $deliveryAdmin);
check($limited['status'] === 'failed' && $limited['error'] === 'delivery_unpublish_limit', 'Default unpublish limit was bypassed.');
check($importedState === $state(), 'Rejected downsize wrote content.');
$approvedLimit = $delivery->execute($request('preview', $smallerBytes, limit: 1), $smallerBytes, $deliveryAdmin);
check($approvedLimit['status'] === 'succeeded' && $approvedLimit['result']['stats']['unpublished'] === 1 && !$approvedLimit['result']['stats']['applied'], 'Explicit preview limit did not preserve the plan.');

$freshPreview = $delivery->execute($request('preview', $bytes), $bytes, $deliveryAdmin);
$changedSources = $sourceList;
$changedSources[array_key_last($changedSources)]['policies'][0]['all_readers'] = FALSE;
$settings->set('sources', $changedSources)->save();
$policyChanged = $delivery->execute($request('import', $bytes, $freshPreview['id']), $bytes, $deliveryAdmin);
check($policyChanged['status'] === 'failed' && $policyChanged['error'] === 'delivery_plan_changed', 'Changed source policy retained approval.');
check($importedState === $state(), 'Changed policy approval wrote content.');
$settings->set('sources', $sourceList)->save();

// An injected save failure rolls back both earlier writes and the success receipt.
$failureManifest = snapshot('delivery-test', ['one.md', 'two.md', 'failure.md'], 'Different body');
$failureManifest['revision'] = str_repeat('a', 40);
$failureBytes = $encode($failureManifest);
$failurePreview = $delivery->execute($request('preview', $failureBytes), $failureBytes, $deliveryAdmin);
Drupal::state()->set('knowledge_sync_test.fail_title', 'Guide failure.md');
$failed = $delivery->execute($request('import', $failureBytes, $failurePreview['id']), $failureBytes, $deliveryAdmin);
Drupal::state()->delete('knowledge_sync_test.fail_title');
check($failed['status'] === 'failed', 'Save failure retained a success receipt.');
check($importedState === $state(), 'Atomic delivery failure left revisions or ledger changes.');

// Kill real Drush after a node write, before the transaction and success receipt commit.
$killManifest = $deliveryManifest;
foreach ($killManifest['documents'] as &$document) {
  $document['text'] = 'Body written in an interrupted transaction';
  $document['hash'] = hash('sha256', implode("\0", [$document['title'], $document['text'], $document['url']]));
}
unset($document);
$killBytes = $encode($killManifest);
$killPreview = $delivery->execute($request('preview', $killBytes), $killBytes, $deliveryAdmin);
$killRequest = $request('import', $killBytes, $killPreview['id']);
file_put_contents($siteRoot . '/delivery-request.json', json_encode($killRequest));
file_put_contents($siteRoot . '/delivery-snapshot.json', $killBytes);
Drupal::state()->set('knowledge_sync_test.pause_title', 'Guide one.md');
$paused = '/tmp/xinshi-knowledge-test/delivery-paused';
$child = new Process([$siteRoot . '/vendor/bin/drush', '--no-ansi', 'xinshi-knowledge:delivery', './delivery-request.json', './delivery-snapshot.json', '--account=1'], $siteRoot, timeout: 40);
$child->start();
$deadline = microtime(TRUE) + 20;
while (!is_file($paused) && $child->isRunning() && microtime(TRUE) < $deadline) {
  usleep(100000);
}
$didPause = is_file($paused);
if ($didPause) {
  check($delivery->reconcile($killRequest, 1, $deliveryAdmin)['status'] === 'running',
    'A live import lost its durable exclusion.');
}
$child->stop(0, 9);
check($didPause, 'Drush did not reach the interrupted transaction: ' . $child->getErrorOutput());
Drupal::state()->delete('knowledge_sync_test.pause_title');
unlink($paused);
check($delivery->receipt($killRequest['id'], $deliveryAdmin)['status'] === 'running', 'Killed import was reported as resolved.');
check($importedState === $state(), 'Killed import committed partial revisions or source rows.');
// Simulate expiry of the killed process's temporary lock, without releasing its durable row.
Drupal::database()->update('semaphore')->fields(['expire' => 0])
  ->condition('name', 'xinshi_knowledge_sync.import')->execute();
rejects(fn() => $delivery->execute($request('preview', $bytes), $bytes, $deliveryAdmin), 'delivery_unresolved');
check($delivery->execute($killRequest, $killBytes, $deliveryAdmin)['status'] === 'running', 'Killed task was repeated after lock expiry.');
check($importedState === $state(), 'Killed task replay wrote content.');
$guardPath = '/tmp/xinshi-knowledge-test/private/xinshi-knowledge-delivery/' . $cmsId . '.lock';
rename($guardPath, $guardPath . '.original');
file_put_contents($guardPath, file_get_contents($guardPath . '.original'));
chmod($guardPath, 0600);
try {
  check($delivery->reconcile($killRequest, 1, $deliveryAdmin)['status'] === 'running',
    'Replacing the process lock invented evidence that the original execution stopped.');
}
finally {
  unlink($guardPath);
  rename($guardPath . '.original', $guardPath);
}
$recovered = $delivery->reconcile($killRequest, 1, $deliveryAdmin);
check($recovered['id'] === $killRequest['id'] && $recovered['status'] === 'failed' &&
  $recovered['error'] === 'delivery_interrupted', 'Stopped import did not retain a terminal original receipt.');
check($recovered === $delivery->execute($killRequest, $killBytes, $deliveryAdmin), 'Reconciled import was executed again.');
check($importedState === $state(), 'Reconciliation changed imported content.');
file_put_contents($siteRoot . '/delivery-legacy-snapshot.json', $bytes);
$legacySync = new Process([$siteRoot . '/vendor/bin/drush', '--no-ansi', 'xinshi-knowledge:sync',
  './delivery-legacy-snapshot.json', '--source=delivery-test', '--account=1', '--apply'], $siteRoot, timeout: 30);
$legacySync->mustRun();
check(str_contains($legacySync->getOutput(), 'applied: true') && $importedState === $state(),
  'Reconciled delivery kept legacy apply blocked or repeated content writes.');

// A durable failure fences a delayed request that never entered CMS execution.
$absentRequest = $request('preview', $bytes);
$notStarted = $delivery->reconcile($absentRequest, 1, $deliveryAdmin);
check($notStarted['status'] === 'failed' && $notStarted['error'] === 'delivery_not_started', 'Missing request was not fenced.');
check($notStarted === $delivery->execute($absentRequest, $bytes, $deliveryAdmin), 'A late request crossed the reconciliation fence.');
rejects(fn() => $delivery->reconcile([...$absentRequest, 'maxUnpublished' => 1], 1, $deliveryAdmin), 'delivery_request_conflict');
check($importedState === $state(), 'Missing-request recovery wrote content.');

// Simulate process loss after durable admission. No timeout is permitted to release it.
$unknownRequest = $request('preview', $bytes);
$unknownHash = hash('sha256', json_encode([$unknownRequest, 1], JSON_THROW_ON_ERROR));
Drupal::database()->insert(DeliveryExecution::TABLE)->fields([
  'task_id' => $unknownRequest['id'], 'request_hash' => $unknownHash, 'request' => json_encode($unknownRequest),
  'account_uid' => 1, 'status' => 'running', 'result' => '{}', 'error' => '', 'created' => 1,
])->execute();
check($delivery->execute($unknownRequest, $bytes, $deliveryAdmin)['status'] === 'running', 'Old running task was executed again.');
check($delivery->reconcile($unknownRequest, 1, $deliveryAdmin)['status'] === 'running',
  'An earlier receipt without process-guard evidence was cleared.');
rejects(fn() => $delivery->execute($request('preview', $bytes), $bytes, $deliveryAdmin), 'delivery_unresolved');
rejects(fn() => Drupal::service('xinshi_knowledge_sync.importer')->import($deliveryManifest, 'delivery-test', $deliveryAdmin, TRUE), 'delivery_unresolved');
check($importedState === $state(), 'Unresolved execution allowed content writes.');
rejects(fn() => $delivery->receipt($applied['id'], $reader), 'import_forbidden');
Role::create(['id' => 'delivery-owner', 'label' => 'Delivery owner',
  'permissions' => ['administer nodes', 'administer xinshi knowledge', 'bypass node access',
    'access content', 'use text format basic_html']])->save();
$otherAdmin = User::create(['name' => 'delivery-other-admin', 'status' => 1, 'roles' => ['delivery-owner']]);
$otherAdmin->save();
rejects(fn() => $delivery->receipt($applied['id'], $otherAdmin), 'delivery_account_mismatch');
rejects(fn() => $delivery->reconcile($importRequest, 1, $otherAdmin), 'delivery_recovery_forbidden');
Role::create(['id' => 'delivery-recovery', 'label' => 'Delivery reconciliation only',
  'permissions' => ['reconcile xinshi knowledge delivery']])->save();
$reconciler = User::create(['name' => 'delivery-reconciler', 'status' => 1, 'roles' => ['delivery-recovery']]);
$reconciler->save();
check($delivery->reconcile($importRequest, 1, $reconciler) === $applied, 'Recovery-only account could not read the original result.');
check($delivery->reconcile($killRequest, 1, $reconciler) === $recovered, 'Reading a reconciled receipt changed its original auditor.');
rejects(fn() => $delivery->execute($request('preview', $bytes), $bytes, $reconciler), 'import_forbidden');
$otherAdmin->set('roles', [])->save();
rejects(fn() => $delivery->receipt($applied['id'], $otherAdmin), 'import_forbidden');
$deliveryAdmin->block()->save();
rejects(fn() => $delivery->receipt($applied['id'], $deliveryAdmin), 'import_forbidden');
check($delivery->reconcile($importRequest, 1, $reconciler) === $applied, 'Blocking the original owner prevented independent recovery.');
$deliveryAdmin->activate()->save();
Role::load('delivery-recovery')->revokePermission('reconcile xinshi knowledge delivery')->save();
rejects(fn() => $delivery->reconcile($importRequest, 1, $reconciler), 'delivery_recovery_forbidden');
Role::load('delivery-recovery')->grantPermission('reconcile xinshi knowledge delivery')->save();
$reconciler->block()->save();
rejects(fn() => $delivery->reconcile($importRequest, 1, $reconciler), 'delivery_recovery_forbidden');
$reconciler->activate()->save();
// Cleanup is restricted to this synthetic row; production has no reset endpoint.
Drupal::database()->delete(DeliveryExecution::TABLE)->condition('task_id', $unknownRequest['id'])->execute();
$otherAdmin->set('roles', ['delivery-owner'])->save();
$deletedOwnerRequest = $request('preview', $bytes);
$deletedOwnerReceipt = $delivery->execute($deletedOwnerRequest, $bytes, $otherAdmin);
check($deletedOwnerReceipt['status'] === 'succeeded', 'Original account could not execute its preview.');
$deletedOwnerUid = (int) $otherAdmin->id();
$otherAdmin->delete();
check($delivery->reconcile($deletedOwnerRequest, $deletedOwnerUid, $reconciler) === $deletedOwnerReceipt,
  'Removing the original account prevented receipt reconciliation.');
check(count(Drupal::service('xinshi_knowledge_sync.uninstall_validator')->validate('xinshi_knowledge_sync')) === 1, 'Uninstall would remove durable receipts.');
Drupal::moduleHandler()->loadInclude('xinshi_knowledge_sync', 'install');
xinshi_knowledge_sync_update_11001();
xinshi_knowledge_sync_update_11001();
xinshi_knowledge_sync_update_11002();
xinshi_knowledge_sync_update_11002();
check($delivery->receipt($applied['id'], $deliveryAdmin) === $applied && $importedState === $state(), 'Repeated schema update changed existing receipts or content.');
