<?php

use Xinshi\Migration\Runtime;

Runtime::requireEntryPoint();

/** Load every selected current entity, revision and actual translation. */
use Drupal\Core\Database\Database;

umask(0077);
$started = microtime(TRUE);
$read = static fn($name) => json_decode(file_get_contents(Runtime::path('') . $name), TRUE, 512, JSON_THROW_ON_ERROR);
$target = Database::getConnection();
$selection = $read('reference-selected-ids.json');
$integrity = $read('full-snapshot-validation-report.json');
if ($target->getConnectionOptions()['database'] !== Runtime::targetDatabase() || $integrity['status'] !== 'passed') {
  throw new RuntimeException('The verified prepared import is required.');
}
$target->query('SET SESSION wait_timeout = 7200');
$models = [
  'user' => ['users', 'uid', NULL, NULL],
  'file' => ['file_managed', 'fid', NULL, NULL],
  'taxonomy_term' => ['taxonomy_term_data', 'tid', 'taxonomy_term_revision', 'revision_id'],
  'crop' => ['crop', 'cid', 'crop_revision', 'vid'],
  'media' => ['media', 'mid', 'media_revision', 'vid'],
  'block_content' => ['block_content', 'id', 'block_content_revision', 'revision_id'],
  'node' => ['node', 'nid', 'node_revision', 'vid'],
  'consumer' => ['consumer', 'id', NULL, NULL],
  'content_moderation_state' => ['content_moderation_state', 'id', 'content_moderation_state_revision', 'revision_id'],
  'webform_submission' => ['webform_submission', 'sid', NULL, NULL],
  'ai_log' => ['ai_log', 'id', NULL, NULL],
  'path_alias' => ['path_alias', 'id', 'path_alias_revision', 'revision_id'],
  'redirect' => ['redirect', 'rid', NULL, NULL],
  'wechat_user' => ['wechat_user', 'id', NULL, NULL],
];
$errors = [];
$report = ['status' => 'checking', 'entities' => [], 'source_written' => FALSE, 'execution_environment' => Runtime::environment(), 'physical_files_verified' => FALSE];
$manager = \Drupal::entityTypeManager();
foreach ($models as $type => [$table, $idColumn, $revisionTable, $revisionColumn]) {
  $stats = ['current' => 0, 'current_translations' => 0, 'revisions' => 0, 'revision_translations' => 0, 'errors' => 0];
  $storage = $manager->getStorage($type);
  $ids = $target->select($table, 't')->fields('t', [$idColumn])->orderBy($idColumn)->execute()->fetchCol();
  foreach (array_chunk($ids, 50) as $chunk) {
    try {
      $entities = $storage->loadMultiple($chunk);
      foreach ($chunk as $id) {
        if (!isset($entities[$id]) || (string) $entities[$id]->id() !== (string) $id) {
          throw new RuntimeException('Current entity did not load.');
        }
        $entity = $entities[$id];
        foreach ($entity->getTranslationLanguages() as $langcode => $language) {
          $entity->getTranslation($langcode)->toArray();
          $stats['current_translations']++;
        }
        $stats['current']++;
      }
    }
    catch (Throwable $error) {
      $errors[] = ['entity_type' => $type, 'ids' => $chunk, 'phase' => 'current', 'class' => get_class($error), 'message' => $error->getMessage()];
      $stats['errors']++;
    }
    unset($entities, $entity);
    $storage->resetCache();
  }
  if ($revisionTable) {
    $revisionIds = $target->select($revisionTable, 'r')->fields('r', [$revisionColumn])->orderBy($revisionColumn)->execute()->fetchCol();
    foreach (array_chunk($revisionIds, 50) as $chunk) {
      try {
        $revisions = $storage->loadMultipleRevisions($chunk);
        foreach ($chunk as $id) {
          if (!isset($revisions[$id]) || (string) $revisions[$id]->getRevisionId() !== (string) $id) {
            throw new RuntimeException('Historical revision did not load.');
          }
          $revision = $revisions[$id];
          foreach ($revision->getTranslationLanguages() as $langcode => $language) {
            $revision->getTranslation($langcode)->toArray();
            $stats['revision_translations']++;
          }
          $stats['revisions']++;
        }
      }
      catch (Throwable $error) {
        $errors[] = ['entity_type' => $type, 'ids' => $chunk, 'phase' => 'revision', 'class' => get_class($error), 'message' => $error->getMessage()];
        $stats['errors']++;
      }
      unset($revisions, $revision);
      $storage->resetCache();
      if ($stats['revisions'] > 0 && $stats['revisions'] % 2000 === 0) {
        print json_encode(['entity_type' => $type, 'revisions_loaded' => $stats['revisions'], 'errors' => $stats['errors'], 'elapsed_seconds' => round(microtime(TRUE) - $started)], JSON_THROW_ON_ERROR) . PHP_EOL;
      }
    }
  }
  $report['entities'][$type] = $stats;
  print json_encode(['entity_type' => $type] + $stats, JSON_THROW_ON_ERROR) . PHP_EOL;
  file_put_contents(Runtime::path('entity-loading-report.json'), json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
  gc_collect_cycles();
}
$report['error_count'] = count($errors);
$report['status'] = $errors ? 'failed' : 'passed';
$report['elapsed_seconds'] = round(microtime(TRUE) - $started, 2);
file_put_contents(Runtime::path('entity-loading-report.json'), json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
if ($errors) {
  file_put_contents(Runtime::path('entity-loading-errors.private.json'), json_encode($errors, JSON_THROW_ON_ERROR));
}
print json_encode(['status' => $report['status'], 'error_count' => count($errors), 'elapsed_seconds' => $report['elapsed_seconds']], JSON_THROW_ON_ERROR) . PHP_EOL;
if ($errors) {
  exit(1);
}
