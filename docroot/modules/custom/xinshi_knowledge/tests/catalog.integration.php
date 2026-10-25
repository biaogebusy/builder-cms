<?php

declare(strict_types=1);

require __DIR__ . '/integration-bootstrap.php';

use Drupal\Core\Session\AnonymousUserSession;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;
use Drupal\search_api\Entity\Index;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\xinshi_knowledge\Knowledge;

$count = 0;
function check(bool $ok, string $message): void {
  global $count;
  if (!$ok) {
    throw new RuntimeException($message);
  }
  $count++;
}
function clearAccess(): void {
  foreach (['node', 'media', 'file', 'user'] as $type) {
    Drupal::entityTypeManager()->getAccessControlHandler($type)->resetCache();
  }
}

Drupal::service('module_installer')->install(['jsonapi', 'jsonapi_extras']);
Drupal::configFactory()->getEditable('jsonapi.settings')->set('read_only', FALSE)->save();
Drupal::configFactory()->getEditable('jsonapi_extras.settings')->set('path_prefix', 'api/v1')->save();
Drupal::service('kernel')->rebuildContainer();
Drupal::service('router.builder')->rebuild();
Role::create(['id' => 'knowledge_editor', 'label' => 'Knowledge editor', 'permissions' => [
  'access content', 'view media', 'view xinshi knowledge', 'access user profiles',
  'create xinshi_knowledge content', 'create xinshi_knowledge_document media',
  'view own unpublished content',
]])->save();
$editor = User::create(['name' => 'catalog-editor', 'pass' => 'catalog-test-password',
  'status' => 1, 'roles' => ['knowledge_editor']]);
$editor->save();
$outsider = User::create(['name' => 'catalog-outsider', 'pass' => 'catalog-test-password', 'status' => 1]);
$outsider->save();
Drupal::currentUser()->setAccount($editor);
mkdir('private://xinshi-knowledge', 0777, TRUE);
$uri = 'private://xinshi-knowledge/catalog.txt';
file_put_contents($uri, "Knowledge catalog acceptance fixture.\n");
$file = File::create(['uri' => $uri, 'filename' => 'catalog.txt', 'uid' => $editor->id(), 'status' => 1]);
$file->save();
$media = Media::create(['bundle' => Knowledge::MEDIA, 'name' => 'catalog.txt',
  'uid' => $editor->id(), 'status' => 1, Knowledge::FILE => ['target_id' => $file->id()]]);
$media->save();
$node = Node::create(['type' => Knowledge::NODE, 'title' => 'Catalog fixture',
  'uid' => $editor->id(), 'status' => 1, Knowledge::ATTACHMENTS => [['target_id' => $media->id()]]]);
$node->save();
$catalog = Drupal::service('xinshi_knowledge.catalog');
$extraction = Drupal::service('xinshi_knowledge.extraction');
$row = $catalog->detail($node->uuid(), $editor);
check($row['owner'] === 'catalog-editor', 'Owner was not projected.');
check($row['attachments'][0]['status'] === 'pending', 'New upload was not queued.');
check(!str_contains(json_encode($row), 'source_key') && !str_contains(json_encode($row), 'private://'), 'Internal metadata escaped.');
$state = $extraction->state((int) $media->id());
$extraction->process(['mid' => (int) $media->id(), 'generation' => $state['generation']]);
check($catalog->detail($node->uuid(), $editor)['attachments'][0]['status'] === 'ready', 'TXT parsing did not complete.');
foreach (['processing', 'failed'] as $status) {
  Drupal::database()->update(Knowledge::TABLE)->fields(['status' => $status, 'error' => 'needs_ocr'])
    ->condition('mid', $media->id())->execute();
  check($catalog->detail($node->uuid(), $editor)['attachments'][0]['status'] === $status, 'Wrong status.');
}
check($catalog->detail($node->uuid(), $editor)['attachments'][0]['error'] === 'needs_ocr', 'Missing parser reason.');
Drupal::database()->update(Knowledge::TABLE)->fields(['source_key' => 'retired'])
  ->condition('mid', $media->id())->execute();
$row = $catalog->detail($node->uuid(), $editor)['attachments'][0];
check($row['status'] === 'pending' && $row['error'] === '', 'Stale generation leaked its failure.');
foreach ([['node', 'title'], ['node', Knowledge::ATTACHMENTS], ['media', 'name'],
    ['media', Knowledge::FILE], ['file', 'filename'], ['node', 'uid'], ['user', 'name']] as $field) {
  Drupal::state()->set('knowledge_test.denied_field', $field);
  clearAccess();
  $row = $catalog->detail($node->uuid(), $editor);
  if ($field === ['node', 'title']) {
    check($row === NULL, 'Hidden title escaped.');
  }
  elseif (in_array($field, [['node', 'uid'], ['user', 'name']], TRUE)) {
    check($row['owner'] === NULL, 'Hidden owner escaped.');
  }
  else {
    check($row['restricted'] && !$row['attachments'], 'Hidden attachment escaped.');
  }
}
Drupal::state()->delete('knowledge_test.denied_field');
Drupal::state()->set('knowledge_test.denied_file', $uri);
clearAccess();
check(!$catalog->detail($node->uuid(), $editor)['attachments'], 'Private download veto bypassed.');
Drupal::state()->delete('knowledge_test.denied_file');
Drupal::state()->set('knowledge_test.denied_node', (string) $node->id());
clearAccess();
check($catalog->detail($node->uuid(), $editor) === NULL, 'Node access veto bypassed.');
Drupal::state()->delete('knowledge_test.denied_node');
clearAccess();
check($catalog->detail($node->uuid(), new AnonymousUserSession()) === NULL, 'Anonymous access allowed.');
check($catalog->detail($node->uuid(), $outsider) === NULL, 'Reader permission bypassed.');
$peer = User::create(['name' => 'catalog-peer', 'pass' => 'catalog-test-password',
  'status' => 1, 'roles' => ['knowledge_editor']]);
$peer->save();
$peerNode = Node::create(['type' => Knowledge::NODE, 'title' => 'Other owner private notes',
  'status' => 1, 'uid' => $peer->id(), 'body' => ['value' => 'Ownershipneedle private facts.']]);
$peerNode->save();
clearAccess();
check(!$node->access('view', $peer), 'Another reader can view personal knowledge.');
check(!$media->access('view', $peer), 'Another reader can view personal media.');
check(!$file->access('download', $peer), 'Another reader can download personal files.');
check($catalog->detail($node->uuid(), $peer) === NULL, 'Detail leaked personal metadata.');
check($catalog->detail($peerNode->uuid(), $peer) !== NULL, 'Peer cannot view their own document.');
check($catalog->detail($node->uuid(), User::load(1)) !== NULL, 'Administrator cannot view personal knowledge.');
check(!$catalog->detail($node->uuid(), $editor)['shared'], 'Personal knowledge marked as shared.');
check(array_column($catalog->listing($peer)['items'], 'id') === [$peerNode->uuid()],
  'Catalog did not use its supplied account for ownership filtering.');
$documents = Drupal::service('xinshi_knowledge.documents');
try {
  $documents->content($peerNode, $editor);
  throw new RuntimeException('Document reader leaked another owner\'s text.');
}
catch (DomainException $error) {
  check($error->getMessage() === 'not_found', 'Wrong denial for private document text.');
}
Index::load(Knowledge::INDEX)->indexItems();
check(!$documents->search('Ownershipneedle', 'en', [Knowledge::NODE], 0, $editor)['matches'],
  'Search leaked another owner\'s text.');
check(count($documents->search('Ownershipneedle', 'en', [Knowledge::NODE], 0, $peer)['matches']) === 1,
  'Owner search lost personal knowledge.');
for ($i = 0; $i < 22; $i++) {
  Node::create(['type' => Knowledge::NODE, 'title' => 'Text entry ' . $i,
    'status' => 1, 'uid' => $editor->id()])->save();
  Node::create(['type' => Knowledge::NODE, 'title' => 'Hidden entry ' . $i,
    'status' => 1, 'uid' => $peer->id()])->save();
}
$page = $catalog->listing($editor);
check(count($page['items']) === 20 && $page['next'] !== NULL, 'Catalog page is not bounded.');
$next = $catalog->listing($editor, $page['next']);
check(count($next['items']) === 3 && $next['next'] === NULL, 'Catalog lost entries.');
check(!array_intersect(array_column($page['items'], 'id'), array_column($next['items'], 'id')), 'Duplicate pagination entries.');
$summary = $catalog->summary($editor);
check($summary['entries'] === 23 && $summary['personal'] === 23 && $summary['shared'] === 0,
  'Summary must count all owned entries, not the current page or other owners.');
check($summary['files'] === 1 && $summary['pending'] === 1 && $summary['failed'] === 0,
  'Summary used stale extraction status.');
check($summary['scope'] === 'accessible' && $catalog->summary(User::load(1))['entries'] === 46,
  'Administrator summary does not include all owners.');
Drupal::state()->set('knowledge_test.denied_field', ['media', 'name']);
clearAccess();
check($catalog->summary($editor)['files'] === 0, 'Summary leaked a restricted attachment.');
Drupal::state()->set('knowledge_test.denied_field', ['node', 'title']);
clearAccess();
$page = $catalog->listing($editor);
check(!$page['items'] && $page['next'] !== NULL, 'Inaccessible page must retain its continuation.');
check($catalog->summary($editor)['entries'] === 0, 'Summary leaked entries with hidden titles.');
Drupal::state()->delete('knowledge_test.denied_field');
clearAccess();
$node->setUnpublished()->save();
check($catalog->detail($node->uuid(), $editor) !== NULL, 'Owner cannot inspect unpublished upload.');
$node->setPublished()->save();
// Save only synthetic test identities for the HTTP test process.
file_put_contents('/tmp/xinshi-knowledge-test/catalog-fixture.json', json_encode([
  'id' => $node->uuid(), 'uid' => (string) $editor->id(),
], JSON_THROW_ON_ERROR));
fwrite(STDOUT, "Catalog integration: $count checks passed.\n");
