<?php

declare(strict_types=1);

namespace Xinshi\ChatMigration;

/** Exports a frozen site without installing fields, changing versions, or loading a task store. */
final class Export {

  public function __construct(private readonly Source $source) {}

  /** Publish the manifest only after both the raw archive and converter input are durable. */
  public function run(string $directory): array {
    $scan = $this->source->scan();
    $this->source->assertLegacy();
    $settings = $this->source->settings();
    $directory = Archive::directory($directory, TRUE);
    $nodes = $this->source->inventory();
    $bindings = $this->source->bindings($nodes);
    $raw = Archive::create($directory . '/entities.ndjson.partial');
    $input = Archive::create($directory . '/sessions.ndjson.partial');
    $hashes = ['entities' => hash_init('sha256'), 'sessions' => hash_init('sha256')];
    $counts = ['entities' => 0, 'conversations' => 0, 'messages' => 0, 'snapshots' => 0,
      'unreferencedMessages' => 0, 'missingRelationTargets' => 0];
    $row_counts = [];
    $missing_targets = [];
    try {
      foreach (array_chunk(array_keys($nodes), 25) as $ids) {
        foreach ($this->source->read($ids) as $nid => $rows) {
          $line = Archive::json(['nid' => (string) $nid, 'rows' => Archive::pack($rows)]) . "\n";
          if (strlen($line) > Archive::ROW_BYTES) {
            throw new \RuntimeException('entity_archive_too_large');
          }
          Archive::write($raw, $line);
          hash_update($hashes['entities'], $line);
          foreach ($rows as $table => $items) {
            $row_counts[$table] = ($row_counts[$table] ?? 0) + count($items);
          }
          $counts['entities']++;
          $is_message = $nodes[$nid]['bundle'] === 'ai_session';
          $counts[$is_message ? 'messages' : 'conversations']++;
          if ($is_message && !isset($bindings[$nid])) {
            $counts['unreferencedMessages']++;
          }
          foreach ($this->source->snapshots($rows, $bindings) as $snapshot) {
            $line = Archive::json($snapshot) . "\n";
            if (strlen($line) > \Drupal\xinshi_ai_reference\ChatSessionFormat::RECORD_BYTES) {
              throw new \RuntimeException('converter_record_too_large');
            }
            Archive::write($input, $line);
            hash_update($hashes['sessions'], $line);
            $counts['snapshots']++;
          }
        }
      }
      foreach ($bindings as $nid => $chats) {
        if (($nodes[$nid]['bundle'] ?? NULL) !== 'ai_session') {
          $counts['missingRelationTargets']++;
          $missing_targets[] = (string) $nid;
        }
      }
      $recheck = hash_init('sha256');
      foreach (array_chunk(array_keys($nodes), 25) as $ids) {
        foreach ($this->source->read($ids) as $nid => $rows) {
          hash_update($recheck, Archive::json(['nid' => (string) $nid, 'rows' => Archive::pack($rows)]) . "\n");
        }
      }
      if (hash_final($recheck) !== hash_final(hash_copy($hashes['entities']))) {
        throw new \RuntimeException('source_changed_during_export');
      }
      if ($this->source->inventory() !== $nodes || $this->source->bindings($nodes) !== $bindings ||
        $this->source->scan() !== $scan || $this->source->settings() !== $settings) {
        throw new \RuntimeException('source_changed_during_export');
      }
      Archive::sync($raw);
      Archive::sync($input);
    }
    finally {
      fclose($raw);
      fclose($input);
    }
    foreach (['entities', 'sessions'] as $name) {
      if (!rename($directory . '/' . $name . '.ndjson.partial', $directory . '/' . $name . '.ndjson')) {
        throw new \RuntimeException('artifact_publish_failed');
      }
    }
    Archive::syncDirectory($directory);
    $manifest = ['version' => 1, 'operation' => $this->source->targetVersion === 3 ? 'cms-chat-plan-export' : 'cms-chat-export', 'complete' => TRUE,
      'site' => $this->source->site, 'nodes' => $nodes, 'bindings' => $bindings,
      'storageConfiguration' => $settings,
      'missingTargets' => $missing_targets,
      'sourceFingerprint' => $scan['sourceFingerprint'], 'targetFingerprint' => $scan['targetFingerprint'],
      'layout' => $this->source->layout(FALSE), 'counts' => $counts, 'rows' => $row_counts,
      'entitiesSha256' => hash_final($hashes['entities']), 'inputSha256' => hash_final($hashes['sessions'])];
    Archive::publish($directory . '/manifest.json', $manifest);
    return ['operation' => $manifest['operation'], 'counts' => $counts,
      'entitiesSha256' => $manifest['entitiesSha256'], 'inputSha256' => $manifest['inputSha256']];
  }

}
