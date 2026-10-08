<?php

declare(strict_types=1);

/** Exercise optional moderation only inside the disposable integration site. */

use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\User;
use Drupal\workflows\Entity\Workflow;
use Drupal\xinshi_knowledge_sync\Service\SourceAccess;

// Drush subprocesses fill caches between the setup changes made by this request.
Drupal::service('cache_tags.invalidator.checksum')->reset();
Drupal::service('module_installer')->install(['content_moderation']);
// The installer kernel does not replace containers cached by earlier Drush calls.
Drupal::service('kernel')->invalidateContainer();
$moderationAdmin = User::load(1);
Drupal::currentUser()->setAccount($moderationAdmin);
NodeType::create(['type' => 'sync_editorial', 'name' => 'Sync editorial fixture'])->save();
$workflow = Workflow::create(['id' => 'sync_editorial', 'label' => 'Sync editorial fixture',
  'type' => 'content_moderation', 'type_settings' => [
    'entity_types' => ['node' => ['sync_editorial']], 'default_moderation_state' => 'draft',
  ]]);
$workflow->save();
$moderationInfo = Drupal::service('content_moderation.moderation_information');
$unmoderatedNode = Node::create(['type' => 'xinshi_knowledge']);
check($unmoderatedNode->hasField('moderation_state'), 'The moderation field must also exist on other node bundles.');
check(!$moderationInfo->isModeratedEntity($unmoderatedNode), 'The knowledge bundle must not be moderated yet.');

$moderationSettings = Drupal::configFactory()->getEditable('xinshi_knowledge_sync.settings');
$moderationSources = $moderationSettings->get('sources');
$moderationSources[] = ['id' => 'moderation-test', 'enabled' => TRUE, 'default_policy' => 'readers',
  'policies' => [['id' => 'readers', 'all_readers' => TRUE, 'roles' => [], 'users' => []]], 'rules' => []];
$moderationSettings->set('sources', $moderationSources)->save();
$moderationImporter = Drupal::service('xinshi_knowledge_sync.importer');
$moderationManifest = snapshot('moderation-test', ['help.md']);
$moderationRows = static fn(): array => Drupal::database()->select(SourceAccess::TABLE, 'd')->fields('d')
  ->condition('source', 'moderation-test')->execute()->fetchAllAssoc('path', PDO::FETCH_ASSOC);
check($moderationImporter->import($moderationManifest, 'moderation-test', $moderationAdmin)['created'] === 1, 'Unmoderated preview was blocked.');
check($moderationRows() === [], 'Unmoderated preview wrote source records.');
check($moderationImporter->import($moderationManifest, 'moderation-test', $moderationAdmin, TRUE)['created'] === 1, 'Unmoderated creation was blocked.');
check($moderationImporter->import($moderationManifest, 'moderation-test', $moderationAdmin, TRUE)['unchanged'] === 1, 'Unmoderated repeat import changed content.');
$moderationUpdate = snapshot('moderation-test', ['help.md'], 'Updated unmoderated content');
check($moderationImporter->import($moderationUpdate, 'moderation-test', $moderationAdmin, TRUE)['updated'] === 1, 'Unmoderated update was blocked.');
$moderationEmpty = snapshot('moderation-test', []);
check($moderationImporter->import($moderationEmpty, 'moderation-test', $moderationAdmin, TRUE, TRUE)['unpublished'] === 1, 'Unmoderated unpublishing was blocked.');
check($moderationImporter->import($moderationManifest, 'moderation-test', $moderationAdmin, TRUE)['updated'] === 1, 'Unmoderated restoration was blocked.');
$moderationSnapshotPath = dirname(Drupal::root()) . '/moderation-snapshot.json';
file_put_contents($moderationSnapshotPath, json_encode($moderationManifest, JSON_THROW_ON_ERROR));
$expectStats($runSync('./moderation-snapshot.json', ['--source=moderation-test', '--account=1']), 'unchanged', 1);

// Actual workflow assignment must still block every kind of source write.
Drupal::service('cache_tags.invalidator.checksum')->reset();
$workflow->getTypePlugin()->addEntityTypeAndBundle('node', 'xinshi_knowledge');
$workflow->save();
Drupal::entityTypeManager()->getStorage('node')->resetCache();
check($moderationInfo->isModeratedEntity(Node::create(['type' => 'xinshi_knowledge'])), 'Knowledge workflow assignment did not take effect.');
$beforeRows = $moderationRows();
$moderatedNodeId = $beforeRows['help.md']['nid'];
$revisionCount = static fn(): int => (int) Drupal::database()->select('node_revision', 'r')
  ->condition('nid', $moderatedNodeId)->countQuery()->execute()->fetchField();
$beforeRevisions = $revisionCount();
foreach ([FALSE, TRUE] as $apply) {
  foreach ([snapshot('moderation-test', ['new.md']), $moderationUpdate, $moderationEmpty] as $manifest) {
    rejects(fn() => $moderationImporter->import($manifest, 'moderation-test', $moderationAdmin, $apply, TRUE),
      'moderated_source_not_supported');
  }
}
$expectFailure($runSync('./moderation-snapshot.json', ['--source=moderation-test', '--account=1', '--apply']),
  'moderated_source_not_supported');
check($moderationRows() === $beforeRows, 'Rejected moderated imports changed source records.');
check($revisionCount() === $beforeRevisions, 'Rejected moderated imports created revisions.');
$moderatedNode = Node::load($moderatedNodeId);
check($moderatedNode->isPublished() && str_contains($moderatedNode->get('body')->value, 'Quasarengine'),
  'Rejected moderated imports changed existing content.');

Drupal::service('cache_tags.invalidator.checksum')->reset();
$workflow->getTypePlugin()->removeEntityTypeAndBundle('node', 'xinshi_knowledge');
$workflow->save();
check($moderationImporter->import($moderationManifest, 'moderation-test', $moderationAdmin)['unchanged'] === 1,
  'Unmoderated import did not recover after removing the workflow assignment.');
