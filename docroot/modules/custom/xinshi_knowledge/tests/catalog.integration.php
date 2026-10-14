<?php

declare(strict_types=1);

require __DIR__ . '/integration-bootstrap.php';

use Drupal\Core\Session\AnonymousUserSession;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;
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
for ($i = 0; $i < 22; $i++) {
  Node::create(['type' => Knowledge::NODE, 'title' => 'Text entry ' . $i,
    'status' => 1, 'uid' => $editor->id()])->save();
}
$page = $catalog->listing($editor);
check(count($page['items']) === 20 && $page['next'] !== NULL, 'Catalog page is not bounded.');
$next = $catalog->listing($editor, $page['next']);
check(count($next['items']) === 3 && $next['next'] === NULL, 'Catalog lost entries.');
check(!array_intersect(array_column($page['items'], 'id'), array_column($next['items'], 'id')), 'Duplicate pagination entries.');
Drupal::state()->set('knowledge_test.denied_field', ['node', 'title']);
clearAccess();
$page = $catalog->listing($editor);
check(!$page['items'] && $page['next'] !== NULL, 'Inaccessible page must retain its continuation.');
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
