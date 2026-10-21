<?php

declare(strict_types=1);

/**
 * @file
 * Tests actual Drupal services and SQLite transactions using synthetic documents.
 */

use Drupal\Core\Session\AnonymousUserSession;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\xinshi_office\OfficeInput;

require __DIR__ . '/install-fixture.php';
\Drupal::service('module_installer')->install(['xinshi_office']);
$checks = 0;
function check(bool $ok, string $label): void {
  if (!$ok) throw new RuntimeException($label);
  $GLOBALS['checks']++;
  echo 'PASS: ' . $label . PHP_EOL;
}
function denied(callable $run, string $code, string $label): void {
  try { $run(); }
  catch (Throwable $error) {
    check($error->getMessage() === $code, $label . ' returned ' . $error->getMessage());
    return;
  }
  throw new RuntimeException('Expected refusal: ' . $label);
}
function id(): string { return \Drupal::service('uuid')->generate(); }
function value(array $data): stdClass { return json_decode(json_encode($data, JSON_THROW_ON_ERROR), FALSE, 64, JSON_THROW_ON_ERROR); }
function content(string $text = '中文 working copy'): stdClass {
  return value(['version' => 1, 'type' => 'doc', 'content' => [
    ['type' => 'paragraph', 'attrs' => ['id' => 'p1'], 'content' => [['type' => 'text', 'text' => $text]]],
  ]]);
}
function createInput(): stdClass {
  return (object) ['operationId' => id(), 'kind' => 'word', 'name' => 'Synthetic office document',
    'content' => content(), 'assetIds' => [], 'source' => 'manual', 'chatId' => NULL,
    'originalFileId' => NULL, 'importReport' => NULL];
}
function updateInput(int $revision, string $text = 'New draft'): stdClass {
  return (object) ['operationId' => id(), 'name' => 'Edited document', 'content' => content($text),
    'assetIds' => [], 'source' => 'manual', 'expectedRevision' => $revision];
}
function asUser($user): void { \Drupal::currentUser()->setAccount($user); }
function rows(string $suffix): int {
  return (int) \Drupal::database()->select('xinshi_office_' . $suffix, 'o')->countQuery()->execute()->fetchField();
}
$documents = \Drupal::service('xinshi_office.documents');
$files = \Drupal::service('xinshi_office.files');
$access = \Drupal::service('xinshi_office.access');
$types = \Drupal::service('xinshi_office.types');
$settings = \Drupal::configFactory()->getEditable('xinshi_office.settings');
check(!\Drupal::moduleHandler()->moduleExists('xinshi_ai') && !\Drupal::moduleHandler()->moduleExists('xinshi_chat'),
  'Office installs without AI and Chat modules');
check(count(\Drupal::service('config.typed')->createFromNameAndData('xinshi_office.settings', $settings->getRawData())->validate()) === 0,
  'Installed settings validate against their schema');
check($types->kinds() === ['word'], 'Only implemented document types are registered');
$role = Role::create(['id' => 'office_editor', 'label' => 'Office editor', 'permissions' => [
  'read own office documents', 'edit own office documents', 'access content', 'view own unpublished content',
]]);
$role->save();
$owner = User::create(['name' => 'office-owner', 'status' => 1, 'roles' => ['office_editor']]); $owner->save();
$other = User::create(['name' => 'office-other', 'status' => 1, 'roles' => ['office_editor']]); $other->save();
$none = User::create(['name' => 'office-none', 'status' => 1]); $none->save();
foreach ([new AnonymousUserSession(), $none] as $account) {
  asUser($account);
  denied(fn() => $documents->listing(), 'permission_denied', 'No implicit document read grant');
}
asUser($owner);
check($access->capabilities()['writeTypes'] === ['word'], 'Owner receives enabled write capabilities');
$input = createInput();
$receipt = $documents->write(NULL, $input);
$doc = $receipt['documentId'];
check($receipt['revision'] === 1 && rows('document') === 1 && rows('revision') === 1 && rows('operation') === 1,
  'Create commits a document, immutable first revision and receipt');
check($documents->find($input->operationId) === $receipt, 'Receipt recovers an ignored write response');
check($documents->write(NULL, $input) === $receipt && rows('document') === 1, 'Duplicate creation returns the original identity');
$changed = clone $input; $changed->name = 'Different request';
denied(fn() => $documents->write(NULL, $changed), 'operation_id_conflict', 'Operation ID cannot be reused for another input');
$read = $documents->read($doc);
check(OfficeInput::encode($read['content']) === OfficeInput::encode($input->content), 'Working copy survives a fresh read');
check($documents->listing()['items'][0]['documentId'] === $doc, 'Metadata listing includes the saved document');
asUser($other);
denied(fn() => $documents->read($doc), 'document_unavailable', 'Other owner cannot read content');
denied(fn() => $documents->revisions($doc), 'document_unavailable', 'Other owner cannot read history');
check($documents->find($input->operationId) === NULL && $documents->listing()['items'] === [], 'Receipt and listing do not disclose other owners');
denied(fn() => $documents->write($doc, updateInput(1)), 'document_unavailable', 'Other owner cannot update a document');
asUser($owner);
$update = updateInput(1);
$second = $documents->write($doc, $update);
check($second['revision'] === 2 && $documents->write($doc, $update) === $second && rows('revision') === 2,
  'Duplicate update never creates an extra revision');
denied(fn() => $documents->write($doc, updateInput(1, 'Stale draft')), 'revision_conflict', 'Stale manual or AI baseline cannot overwrite');
check(OfficeInput::encode($documents->read($doc, 1)['content']) === OfficeInput::encode($input->content), 'Historical content stays immutable');
$invalid = updateInput(2); $invalid->content->content[0]->attrs->unexpected = 'field';
denied(fn() => $documents->write($doc, $invalid), 'invalid_input', 'Unknown content fields fail before persistence');
check(rows('revision') === 2 && $documents->find($invalid->operationId) === NULL, 'Invalid input leaves no partial revision or receipt');
\Drupal::database()->getClientConnection()->exec("CREATE TRIGGER office_fail_receipt BEFORE INSERT ON xinshi_office_operation BEGIN SELECT RAISE(ABORT, 'synthetic receipt failure'); END");
$failed = updateInput(2, 'Must roll back');
try { $documents->write($doc, $failed); throw new RuntimeException('Expected SQL failure'); }
catch (\Throwable $error) { check(str_contains($error->getMessage(), 'synthetic receipt failure'), 'Synthetic database failure reached the receipt insert'); }
\Drupal::database()->query('DROP TRIGGER office_fail_receipt');
check($documents->read($doc)['revision'] === 2 && rows('revision') === 2 && $documents->find($failed->operationId) === NULL,
  'A failed receipt insert rolls back the current pointer and the new revision');
$settings->set('write_types', [])->save();
denied(fn() => $documents->write($doc, updateInput(2)), 'write_disabled', 'Disabling a type refuses new writes');
check($documents->read($doc)['revision'] === 2 && $documents->find($input->operationId) === $receipt &&
  $documents->write(NULL, $input) === $receipt, 'Disabled writes retain authorized reading and exact duplicate receipts');
$settings->set('write_types', ['word'])->save();
$owner->block()->save();
denied(fn() => $documents->read($doc), 'permission_denied', 'Live blocked-account state overrides stale account objects');
$owner->activate()->save(); asUser($owner);
$settings->set('max_storage_bytes', 1)->save();
denied(fn() => $documents->write($doc, updateInput(2)), 'storage_limit', 'Quota failure preserves existing history');
$settings->set('max_storage_bytes', 268435456)->save();
$settings->set('max_documents', 1)->save();
denied(fn() => $documents->write(NULL, createInput()), 'storage_limit', 'Document count quota refuses a new document');
$settings->set('max_documents', 100)->save();
NodeType::create(['type' => 'conversation', 'name' => 'Conversation'])->save();
$conversation = Node::create(['type' => 'conversation', 'title' => 'Owner chat', 'uid' => $owner->id(), 'status' => 0]);
$conversation->save();
$bound = createInput(); $bound->chatId = $conversation->uuid();
$bound_receipt = $documents->write(NULL, $bound);
check(count($documents->listing(chat: $conversation->uuid())['items']) === 1, 'Chat listing filters owner-bound documents');
asUser($other);
denied(fn() => $documents->write(NULL, $bound), 'operation_id_conflict', 'Another owner cannot borrow a creation operation');
$foreign = createInput(); $foreign->chatId = $conversation->uuid();
denied(fn() => $documents->write(NULL, $foreign), 'conversation_unavailable', 'Chat binding requires actual owner access');
asUser($owner); $conversation->delete();
check($documents->read($bound_receipt['documentId'])['chatId'] === $bound->chatId, 'Chat deletion does not delete or revoke independently owned documents');

$image = imagecreatetruecolor(2, 3); imagepng($image, '/tmp/office-test/image.png'); imagedestroy($image);
$image_input = (object) ['fileId' => id(), 'kind' => 'word', 'role' => 'image', 'name' => '示例.png'];
$image_meta = $files->upload($image_input, '/tmp/office-test/image.png');
check($image_meta['width'] === 2 && $image_meta['height'] === 3 && !isset($image_meta['uri']), 'Server validates image dimensions and hides private storage paths');
check($files->upload($image_input, '/tmp/office-test/image.png') === $image_meta && rows('file') === 1,
  'Repeated upload recovers the same immutable resource');
$bad_image = clone $image_input; $bad_image->name = 'Changed.png';
denied(fn() => $files->upload($bad_image, '/tmp/office-test/image.png'), 'file_id_conflict', 'Upload ID cannot change metadata');
file_put_contents('/tmp/office-test/not-image', '<svg xmlns="http://www.w3.org/2000/svg"/>');
denied(fn() => $files->upload((object) ['fileId' => id(), 'kind' => 'word', 'role' => 'image', 'name' => 'fake.png'],
  '/tmp/office-test/not-image'), 'invalid_image', 'Image MIME and extension claims cannot admit SVG');
$with_image = updateInput(2);
$with_image->assetIds = [$image_meta['fileId']];
$with_image->content->content[] = value(['type' => 'image', 'attrs' => ['id' => 'image1', 'assetId' => $image_meta['sha256'],
  'width' => 20, 'height' => 30, 'alt' => 'Synthetic diagram']]);
$image_receipt = $documents->write($doc, $with_image);
check(hash_file('sha256', $documents->file($doc, $image_meta['fileId'])['path']) === $image_meta['sha256'],
  'Authorized file download preserves exact image bytes');
denied(fn() => $documents->file($doc, $image_meta['fileId'], 1), 'file_unavailable', 'A resource must belong to the requested revision');
asUser($other);
denied(fn() => $files->metadata($image_meta['fileId']), 'file_unavailable', 'File metadata is owner-bound');
$stolen = createInput(); $stolen->assetIds = $with_image->assetIds; $stolen->content = $with_image->content;
denied(fn() => $documents->write(NULL, $stolen), 'file_unavailable', 'Foreign asset IDs cannot enter another document');
asUser($owner);
$missing = updateInput(3); $missing->content = $with_image->content;
denied(fn() => $documents->write($doc, $missing), 'invalid_reference', 'Unregistered image digest cannot be saved');
$extra = updateInput(3); $extra->assetIds = $with_image->assetIds;
denied(fn() => $documents->write($doc, $extra), 'invalid_reference', 'Unreferenced image cannot be attached to a revision');
$zip = new ZipArchive(); $zip->open('/tmp/office-test/source.docx', ZipArchive::CREATE);
$zip->addFromString('[Content_Types].xml', '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
$zip->addFromString('_rels/.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>');
$zip->addFromString('word/document.xml', '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body/></w:document>'); $zip->close();
$source = $files->upload((object) ['fileId' => id(), 'kind' => 'word', 'role' => 'original', 'name' => 'Source.docx'], '/tmp/office-test/source.docx');
$import = createInput(); $import->source = 'import'; $import->originalFileId = $source['fileId'];
$import->importReport = (object) ['sourceSha256' => $source['sha256'], 'importer' => 'word-v1', 'issues' => []];
$imported = $documents->write(NULL, $import);
check(hash_file('sha256', $documents->file($imported['documentId'], $source['fileId'])['path']) === hash_file('sha256', '/tmp/office-test/source.docx'),
  'Imported original is retained byte-for-byte separately from the working copy');
$documents->write($imported['documentId'], updateInput(1, 'Different working copy'));
check($documents->read($imported['documentId'])['original']['sha256'] === $source['sha256'], 'Editing leaves original provenance immutable');
$bad_origin = clone $import; $bad_origin->operationId = id(); $bad_origin->importReport = clone $import->importReport;
$bad_origin->importReport->sourceSha256 = str_repeat('0', 64);
denied(fn() => $documents->write(NULL, $bad_origin), 'invalid_origin', 'Browser import report cannot override server original digest');
check(xinshi_office_file_download('private://xinshi-office/2/file') === -1, 'Generic private-file route cannot bypass document membership');
check(count(\Drupal::service('xinshi_office.uninstall_validator')->validate('xinshi_office')) === 1,
  'Uninstall is refused while documents, resources or receipts remain');
check($documents->revisions($doc)['items'][0]['revision'] === 3, 'Revision metadata is newest first');
$restored = updateInput(3); $restored->source = 'restore'; $restored->content = $documents->read($doc, 1)['content'];
check($documents->write($doc, $restored)['revision'] === 4 && $documents->read($doc, 3)['assets'][0]['fileId'] === $image_meta['fileId'],
  'Restore creates a new revision and retains earlier image references');
echo 'Office storage integration: ' . $checks . ' checks passed.' . PHP_EOL;
