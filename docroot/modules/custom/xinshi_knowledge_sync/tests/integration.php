<?php

declare(strict_types=1);

/** Run only against the disposable site installed by the knowledge test bootstrap. */
require dirname(__DIR__, 2) . '/xinshi_knowledge/tests/integration-bootstrap.php';

use Drupal\Core\Session\AnonymousUserSession;
use Drupal\filter\Entity\FilterFormat;
use Drupal\node\Entity\Node;
use Drupal\search_api\Entity\Index;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\xinshi_knowledge_sync\Service\SourceAccess;

$count = 0;
function check(bool $condition, string $message): void {
  global $count;
  if (!$condition) {
    throw new RuntimeException($message);
  }
  $count++;
}
function rejects(callable $call, string $reason): void {
  try {
    $call();
  }
  catch (DomainException | InvalidArgumentException $error) {
    check($error->getMessage() === $reason, 'Unexpected rejection: ' . $error->getMessage());
    return;
  }
  throw new RuntimeException('Expected rejection: ' . $reason);
}
function snapshot(string $source, array $paths, string $text = 'Quasarengine guide'): array {
  return ['version' => 1, 'source' => $source, 'language' => 'en', 'revision' => 'revision-1',
    'documents' => array_map(function ($path) use ($text) {
      $title = 'Guide ' . $path;
      $url = 'https://docs.example.test/' . $path;
      return ['path' => $path, 'title' => $title, 'text' => $text, 'url' => $url,
        'hash' => hash('sha256', implode("\0", [$title, $text, $url]))];
    }, $paths)];
}
function resetAccess(): void {
  Drupal::entityTypeManager()->getAccessControlHandler('node')->resetCache();
  Drupal::entityTypeManager()->getStorage('node')->resetCache();
}

Drupal::service('module_installer')->install(['xinshi_knowledge_sync', 'xinshi_knowledge_sync_test']);
if (!FilterFormat::load('basic_html')) {
  FilterFormat::create(['format' => 'basic_html', 'name' => 'Basic HTML', 'status' => TRUE,
    'filters' => ['filter_html' => ['status' => TRUE, 'settings' => ['allowed_html' => '<br>']]]])->save();
}
\Drupal\language\Entity\ConfigurableLanguage::createFromLangcode('zh-hans')->save();
$admin = User::load(1);
Drupal::currentUser()->setAccount($admin);
$policies = [
  ['id' => 'readers', 'all_readers' => TRUE, 'roles' => [], 'users' => []],
  ['id' => 'operations', 'all_readers' => FALSE, 'roles' => ['operations'], 'users' => []],
];
$sources = array_map(fn($id) => ['id' => $id, 'enabled' => TRUE, 'default_policy' => 'readers',
  'policies' => $policies, 'rules' => [['prefix' => 'internal/', 'policy' => 'operations']]], ['product', 'customer']);
$syncSettings = Drupal::configFactory()->getEditable('xinshi_knowledge_sync.settings');
$syncSettings->set('sources', $sources)->save();
check(count(Drupal::service('config.typed')->createFromNameAndData('xinshi_knowledge_sync.settings', $syncSettings->getRawData())->validate()) === 0, 'Configuration schema is invalid.');
$importer = Drupal::service('xinshi_knowledge_sync.importer');
$manifest = snapshot('product', ['help.md', 'internal/operations.md']);
$before = Drupal::database()->select('node', 'n')->countQuery()->execute()->fetchField();
check($importer->import($manifest, 'product', $admin)['created'] === 2, 'Preview must plan creates.');
check(Drupal::database()->select('node', 'n')->countQuery()->execute()->fetchField() === $before, 'Preview wrote nodes.');
check($importer->import($manifest, 'product', $admin, TRUE)['created'] === 2, 'Initial import failed.');
$rows = Drupal::database()->select(SourceAccess::TABLE, 'd')->fields('d')->condition('source', 'product')
  ->execute()->fetchAllAssoc('path', PDO::FETCH_ASSOC);
$publicId = (int) $rows['help.md']['nid'];
$privateId = (int) $rows['internal/operations.md']['nid'];
$privateUuid = Node::load($privateId)->uuid();
check(Node::load($privateId)->access('view', $admin), 'Drupal administrative bypass changed.');
check($importer->import($manifest, 'product', $admin, TRUE)['unchanged'] === 2, 'Repeated import must be idempotent.');
check((int) Node::load($privateId)->getRevisionId() === (int) $rows['internal/operations.md']['revision_id'], 'Unchanged import made a revision.');
check($importer->import(snapshot('customer', ['help.md']), 'customer', $admin, TRUE)['created'] === 1, 'Customer source collision.');
$customerId = Drupal::database()->select(SourceAccess::TABLE, 'd')->fields('d', ['nid'])->condition('source', 'customer')->execute()->fetchField();
check((int) $customerId !== $publicId, 'Sources share a node.');
$chinese = snapshot('customer', ['help.md']);
$chinese['language'] = 'zh-hans';
check($importer->import($chinese, 'customer', $admin, TRUE)['created'] === 1, 'Language identity collision.');
$chineseId = Drupal::database()->select(SourceAccess::TABLE, 'd')->fields('d', ['nid'])->condition('source', 'customer')->condition('language', 'zh-hans')->execute()->fetchField();
rejects(fn() => $importer->import($manifest, 'customer', $admin, TRUE), 'invalid_snapshot');
$bad = $manifest;
$bad['documents'][] = $bad['documents'][0];
rejects(fn() => $importer->import($bad, 'product', $admin, TRUE), 'invalid_document');
$bad = $manifest;
$bad['documents'][0]['policy'] = 'readers';
rejects(fn() => $importer->import($bad, 'product', $admin, TRUE), 'invalid_document');
$bad = $manifest;
$bad['documents'][0]['text'] = 'Tampered text';
rejects(fn() => $importer->import($bad, 'product', $admin, TRUE), 'invalid_document');
$bad['documents'] = [];
rejects(fn() => $importer->import($bad, 'product', $admin, TRUE), 'invalid_snapshot');
rejects(fn() => $importer->import($manifest, 'product', new AnonymousUserSession(), TRUE), 'import_forbidden');
Role::create(['id' => 'reader', 'label' => 'Reader', 'permissions' => ['access content', 'view xinshi knowledge', 'edit any xinshi_knowledge content', 'revert all revisions', 'delete all revisions']])->save();
Role::create(['id' => 'operations', 'label' => 'Operations'])->save();
$reader = User::create(['name' => 'reader', 'status' => 1, 'roles' => ['reader']]);
$reader->save();
$operator = User::create(['name' => 'operator', 'status' => 1, 'roles' => ['reader', 'operations']]);
$operator->save();
check(!Node::load($publicId)->access('update', $reader), 'Generated node is editable by ordinary editor.');
$documents = Drupal::service('xinshi_knowledge.documents');
Drupal::configFactory()->getEditable('xinshi_ai.settings')->set('harness.mcp.product_documents', [
  'enabled' => TRUE, 'content_types' => ['xinshi_knowledge'],
])->save();
$mcp = new \Drupal\xinshi_ai\Service\ProductDocuments(Drupal::configFactory(), Drupal::entityTypeManager(),
  Drupal::service('entity_field.manager'), Drupal::currentUser(), Drupal::languageManager(), $documents);

Index::load('xinshi_knowledge')->indexItems();
foreach ([$reader, $operator] as $account) {
  Drupal::currentUser()->setAccount($account);
  resetAccess();
  $expected = $account === $operator;
  check(Node::load($privateId)->access('view', $account) === $expected, 'Private node access mismatch.');
  check(Node::load($publicId)->access('view', $account), 'Public help denied to reader.');
  $ids = Drupal::entityQuery('node')->accessCheck(TRUE)->condition('type', 'xinshi_knowledge')->execute();
  check(in_array($privateId, array_map('intval', $ids), TRUE) === $expected, 'Entity query exposed private node.');
  $hits = $documents->search('Quasarengine', 'en', ['xinshi_knowledge'], 0, $account)['matches'];
  check(in_array($privateId, array_map(fn($hit) => (int) $hit['node']->id(), $hits), TRUE) === $expected, 'Search exposed private content.');
  if (!$expected) {
    rejects(fn() => $documents->content(Node::load($privateId), $account), 'not_found');
  }
  else {
    check(str_contains($documents->content(Node::load($privateId), $account)['content'], 'Quasarengine'), 'Authorized read failed.');
  }
}
$cache = Drupal::service('cache_context.user.node_grants');
$contextBefore = $cache->getContext('view');
$firstRead = $mcp->call('get_product_document', ['id' => $privateUuid, 'language' => 'en']);
check(str_contains($firstRead['content'], 'Quasarengine'), 'MCP authorized read failed.');
$sources[0]['policies'][1]['roles'] = [];
$syncSettings->set('sources', $sources)->save();
resetAccess();
check($contextBefore !== $cache->getContext('view'), 'Policy change did not change query cache context.');
rejects(fn() => $documents->content(Node::load($privateId), $operator), 'not_found');
rejects(fn() => $mcp->call('get_product_document', ['id' => $privateUuid, 'language' => 'en',
  'offset' => 1, 'revision' => $firstRead['document']['revisionId'], 'snapshot' => $firstRead['document']['snapshot']]), 'not_found');
check(!in_array($privateUuid, array_column($mcp->call('search_product_documents', ['query' => 'Quasarengine', 'language' => 'en'])['documents'], 'id'), TRUE), 'MCP search leaked revoked document.');
check(!in_array($privateId, array_map('intval', Drupal::entityQuery('node')->accessCheck(TRUE)->execute()), TRUE), 'Revoked document remains queryable.');
$sources[0]['policies'][1]['users'] = [(int) $reader->id()];
$syncSettings->set('sources', $sources)->save();
resetAccess();
check(Node::load($privateId)->access('view', $reader), 'Explicit user policy failed.');
$reader->block()->save();
resetAccess();
rejects(fn() => $documents->content(Node::load($privateId), $reader), 'not_found');
$reader->activate()->save();
resetAccess();
Drupal::currentUser()->setAccount($admin);
check($importer->import(snapshot('product', ['help.md'], 'Updated <tag> text'), 'product', $admin, TRUE)['unpublished'] === 1, 'Removed file was not unpublished.');
check(Node::load($customerId)->isPublished(), 'Other source was unpublished.');
check(!Node::load($privateId)->isPublished(), 'Deleted source stays published.');
check(str_contains($documents->content(Node::load($publicId), $reader)['content'], '<tag>'), 'Code-like text lost during body extraction.');
check($importer->import($manifest, 'product', $admin, TRUE)['updated'] === 2, 'Restoring source did not restore documents.');
check(Node::load($privateId)->uuid() === $privateUuid, 'Restore lost source identity.');
$oldRevision = Drupal::entityTypeManager()->getStorage('node')->loadRevision($rows['help.md']['revision_id']);
check(!$oldRevision->access('revert', $reader), 'Ordinary revision revert bypassed source ownership.');
Drupal::state()->set('knowledge_sync_test.fail_title', 'Guide failure.md');
try {
  $importer->import(snapshot('customer', ['help.md', 'failure.md'], 'Must roll back'), 'customer', $admin, TRUE);
  throw new RuntimeException('Expected synthetic persistence failure.');
}
catch (\Drupal\Core\Entity\EntityStorageException $error) {
  check(str_contains($error->getMessage(), 'synthetic_save_failure'), 'Unexpected save failure.');
}
Drupal::state()->delete('knowledge_sync_test.fail_title');
resetAccess();
check(str_contains($documents->content(Node::load($customerId), $reader)['content'], 'Quasarengine'), 'Failed batch left partial content.');
check((int) Drupal::database()->select(SourceAccess::TABLE, 'd')->condition('source', 'customer')->condition('language', 'en')->countQuery()->execute()->fetchField() === 1, 'Failed batch left partial ledger.');
$empty = snapshot('customer', []);
check($importer->import($empty, 'customer', $admin, FALSE, TRUE)['unpublished'] === 1, 'Explicit empty preview failed.');
check(Node::load($customerId)->isPublished(), 'Empty preview changed publication.');
check($importer->import($empty, 'customer', $admin, TRUE, TRUE)['unpublished'] === 1, 'Explicit empty apply failed.');
check(Node::load($chineseId)->isPublished(), 'English snapshot unpublished Chinese content.');
$manual = Node::load($publicId);
$manual->setNewRevision(TRUE);
$manual->setTitle('Manually edited');
$manual->save();
rejects(fn() => $importer->import($manifest, 'product', $admin, TRUE), 'source_revision_conflict');
check(count(Drupal::service('xinshi_knowledge_sync.uninstall_validator')->validate('xinshi_knowledge_sync')) === 1, 'Uninstall would remove access enforcement.');
$sources[0]['enabled'] = FALSE;
$syncSettings->set('sources', $sources)->save();
resetAccess();
check(!Node::load($publicId)->access('view', $reader), 'Disabled source remains readable.');
rejects(fn() => $importer->import($manifest, 'product', $admin, TRUE), 'unknown_or_disabled_source');
if (is_file('/tmp/xinshi-docs-snapshot.json')) {
  $sources[] = ['id' => 'xinshi-docs', 'enabled' => TRUE, 'default_policy' => 'readers', 'policies' => $policies, 'rules' => []];
  $syncSettings->set('sources', $sources)->save();
  $actual = json_decode(file_get_contents('/tmp/xinshi-docs-snapshot.json'), TRUE, 32, JSON_THROW_ON_ERROR);
  check($importer->import($actual, 'xinshi-docs', $admin, TRUE)['created'] === count($actual['documents']), 'Actual docs import failed.');
  check($importer->import($actual, 'xinshi-docs', $admin, TRUE)['unchanged'] === count($actual['documents']), 'Actual docs repeat import failed.');
  Index::load('xinshi_knowledge')->indexItems();
  check(count($documents->search('信使', 'zh-hans', ['xinshi_knowledge'], 0, $reader)['matches']) > 0, 'Actual Chinese docs cannot be searched.');
}
fwrite(STDOUT, "Knowledge sync integration: $count checks passed.\n");
$count = 0;
require __DIR__ . '/form.integration.php';
fwrite(STDOUT, "Knowledge sync forms: $count checks passed.\n");
$count = 0;
require __DIR__ . '/cli.integration.php';
fwrite(STDOUT, "Knowledge sync CLI: $count checks passed.\n");
$count = 0;
require __DIR__ . '/moderation.integration.php';
fwrite(STDOUT, "Knowledge sync moderation: $count checks passed.\n");
