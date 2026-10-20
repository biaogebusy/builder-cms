<?php

declare(strict_types=1);

namespace Xinshi\ChatMigration;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Database\Connection;
use Drupal\xinshi_ai\Service\ChatSessionFields;

/** Offline SQL application with exact source/target recognition and an immutable batch identity. */
final class Job {

  public function __construct(
    private readonly Connection $db,
    private readonly Source $source,
    private readonly Plan $plan,
  ) {}

  /** One database-level lock prevents different state directories from running competing jobs. */
  public static function exclusive(Connection $db, string $site, callable $operation): mixed {
    $name = 'chat-migration:' . substr(hash('sha256', $site . ':' .
      $db->getConnectionOptions()['database']), 0, 48);
    $handle = NULL;
    if ($db->driver() === 'mysql') {
      if ((string) $db->query('SELECT GET_LOCK(:name, 0)', [':name' => $name])->fetchField() !== '1') {
        throw new \RuntimeException('migration_already_running');
      }
    }
    else {
      $path = $db->getConnectionOptions()['database'];
      if (!str_starts_with($path, '/') || is_link($path)) {
        throw new \RuntimeException('file_database_required');
      }
      $lock = $path . '.chat-migration.lock';
      if (is_link($lock) || (file_exists($lock) &&
        (!is_file($lock) || (fileperms($lock) & 0077) !== 0))) {
        throw new \RuntimeException('invalid_migration_lock');
      }
      $handle = fopen($lock, 'c+b');
      if (!$handle || !flock($handle, LOCK_EX | LOCK_NB)) {
        if ($handle) {
          fclose($handle);
        }
        throw new \RuntimeException('migration_already_running');
      }
      if (!chmod($lock, 0600)) {
        fclose($handle);
        throw new \RuntimeException('invalid_migration_lock');
      }
    }
    try {
      return $operation();
    }
    finally {
      if ($db->driver() === 'mysql') {
        $db->query('SELECT RELEASE_LOCK(:name)', [':name' => $name]);
      }
      elseif ($handle) {
        flock($handle, LOCK_UN);
        fclose($handle);
      }
    }
  }

  /** Install is a separate DDL step; data transactions never pretend to roll schema changes back. */
  public function install(): array {
    $this->source->assertLegacy();
    $this->plan->context(FALSE);
    $this->audit('before', FALSE);
    ChatSessionFields::install($this->source->targetVersion);
    return ['operation' => 'install', 'fields' => count(ChatSessionFields::definitions($this->source->targetVersion)),
      'storage_version' => $this->source->targetVersion - 1, 'dataChanged' => FALSE];
  }

  /** Reconcile every base, field, revision and relation row without loading a runtime manager. */
  public function reconcile(): array {
    return ['operation' => 'reconcile', 'counts' => $this->audit(),
      'storage_version' => $this->source->settings()['data']['storage_version'] ?? 1];
  }

  /** Apply, reverse or activate only the already-prepared batch while maintenance remains enabled. */
  public function run(string $operation, string $state): array {
    if (!in_array($operation, ['apply', 'rollback', 'activate'], TRUE)) {
      throw new \RuntimeException('invalid_operation');
    }
    $state = Archive::directory($state, !file_exists($state));
    $identity = ['version' => 1, 'site' => $this->source->site,
      'manifestSha256' => hash('sha256', Archive::json($this->plan->manifest)),
      'proposalsSha256' => $this->plan->report['proposalsSha256']];
    if (file_exists($state . '/batch.json')) {
      if (Archive::manifest($state . '/batch.json') !== $identity) {
        throw new \RuntimeException('different_batch_state');
      }
    }
    else {
      Archive::publish($state . '/batch.json', $identity);
    }
    $journal = Archive::create($state . '/attempt-' . \Drupal::service('uuid')->generate() . '.ndjson');
    Archive::syncDirectory($state);
    try {
      $this->record($journal, ['operation' => $operation, 'stage' => 'preflight']);
      $this->audit($operation === 'activate' ? 'after' : NULL);
      if ($operation === 'activate') {
        if (!\Drupal::moduleHandler()->moduleExists('xinshi_ai_reference')) {
          throw new \RuntimeException('session_guard_not_enabled');
        }
        $missing = $this->plan->manifest['missingTargets'];
        if ($missing && !Plan::same((object) Archive::manifest($state . '/exceptions.json'), (object) [
          'version' => 1, 'site' => $this->source->site,
          'inputSha256' => $this->plan->manifest['inputSha256'], 'missingTargets' => $missing,
        ])) {
          throw new \RuntimeException('missing_targets_not_accounted_for');
        }
        foreach (array_chunk(array_keys($this->plan->manifest['nodes']), 250) as $ids) {
          $this->invalidate($ids);
        }
        \Drupal::configFactory()->getEditable('xinshi_ai_reference.settings')->set('storage_version', $this->source->targetVersion)->save();
        $this->record($journal, ['operation' => $operation, 'stage' => 'complete']);
        return ['operation' => $operation, 'storage_version' => $this->source->targetVersion, 'maintenanceRetained' => TRUE,
          'acceptedMissingTargets' => count($missing)];
      }
      if ($operation === 'apply' && ($this->source->settings()['data']['storage_version'] ?? 1) !== $this->source->targetVersion - 1) {
        throw new \RuntimeException('source_storage_version_required');
      }
      $destination = $operation === 'apply' ? 'after' : 'before';
      $changed = 0;
      foreach ($this->batches() as $batch) {
        $transaction = $this->db->startTransaction();
        $committed = [];
        try {
          $query = $this->db->select('node', 'n')->fields('n', ['nid'])
            ->condition('nid', array_keys($batch), 'IN')->orderBy('nid');
          if ($this->db->driver() === 'mysql') {
            $query->forUpdate();
          }
          $query->execute()->fetchCol();
          $current = $this->source->read(array_keys($batch), TRUE);
          foreach ($batch as $nid => $pair) {
            $actual = $current[$nid];
            $this->classify($actual, $pair);
            if ($actual === $pair[$destination]) {
              continue;
            }
            $this->write((string) $nid, $actual, $pair[$destination]);
            $committed[] = (string) $nid;
          }
          $verified = $this->source->read(array_keys($batch), TRUE);
          foreach ($batch as $nid => $pair) {
            if ($verified[$nid] !== $pair[$destination]) {
              throw new \RuntimeException('transaction_reconciliation_failed');
            }
          }
          // Drupal commits on destruction. Publish progress only after the transaction is gone.
          unset($transaction);
        }
        catch (\Throwable $error) {
          if (isset($transaction)) {
            $transaction->rollBack();
            unset($transaction);
          }
          throw $error;
        }
        $changed += count($committed);
        // A previous attempt may have committed SQL and stopped before invalidating its caches.
        $this->invalidate(array_keys($batch));
        $this->record($journal, ['operation' => $operation, 'stage' => 'committed',
          'through' => (string) array_key_last($batch), 'changed' => $committed]);
      }
      $counts = $this->audit($destination);
      if ($operation === 'rollback') {
        $original = $this->plan->manifest['storageConfiguration'];
        $configuration = \Drupal::configFactory()->getEditable('xinshi_ai_reference.settings');
        if ($original['exists']) {
          $configuration->setData($original['data'])->save();
        }
        else {
          $configuration->delete();
        }
      }
      $result = ['operation' => $operation, 'changed' => $changed, 'counts' => $counts,
        'storage_version' => $this->source->settings()['data']['storage_version'] ?? 1,
        'maintenanceRetained' => TRUE];
      $this->record($journal, ['stage' => 'complete', ...$result]);
      return $result;
    }
    finally {
      // An interrupted attempt remains evidence. The next invocation inspects SQL, not this cursor.
      fclose($journal);
    }
  }

  private function audit(?string $required = NULL, bool $installed = TRUE): array {
    $this->plan->context($installed);
    $counts = ['unchanged' => 0, 'pending' => 0, 'applied' => 0];
    foreach ($this->batches() as $batch) {
      $current = $this->source->read(array_keys($batch), TRUE);
      foreach ($batch as $nid => $pair) {
        $counts[$this->classify($current[$nid], $pair)]++;
        if ($required !== NULL && $current[$nid] !== $pair[$required]) {
          throw new \RuntimeException('batch_not_in_required_state');
        }
      }
    }
    $this->plan->context($installed);
    return $counts;
  }

  private function classify(array $actual, array $pair): string {
    if ($actual === $pair['before']) {
      return $pair['before'] === $pair['after'] ? 'unchanged' : 'pending';
    }
    if ($actual === $pair['after']) {
      return 'applied';
    }
    throw new \RuntimeException('source_or_target_rows_changed');
  }

  private function batches(): \Generator {
    $batch = [];
    foreach ($this->plan->entities() as $nid => $pair) {
      $batch[$nid] = $pair;
      if (count($batch) === 25) {
        yield $batch;
        $batch = [];
      }
    }
    if ($batch) {
      yield $batch;
    }
  }

  private function write(string $nid, array $actual, array $target): void {
    foreach ($this->source->layout() as $table => $definition) {
      if ($actual[$table] === $target[$table]) {
        continue;
      }
      $field = $definition['field'] ?? NULL;
      if (!in_array($field, $this->source->targetVersion === 3
        ? ['content', 'storage_version', 'edit_version', 'approval_timeline', 'plan_context']
        : ['content', ...array_keys(ChatSessionFields::definitions())], TRUE)) {
        throw new \RuntimeException('immutable_table_change');
      }
      // The source archive retains removed wrappers; all changes are inside this SQL transaction.
      $this->db->delete($table)->condition('entity_id', $nid)->execute();
      foreach (array_chunk($target[$table], 100) as $rows) {
        $insert = $this->db->insert($table)->fields(array_keys($rows[0]));
        foreach ($rows as $row) {
          $insert->values($row);
        }
        $insert->execute();
      }
    }
  }

  private function record($journal, array $entry): void {
    Archive::write($journal, Archive::json($entry) . "\n");
    Archive::sync($journal);
  }

  private function invalidate(array $ids): void {
    \Drupal::entityTypeManager()->getStorage('node')->resetCache($ids);
    Cache::invalidateTags(['node_list', ...array_map(static fn($id) => 'node:' . $id, $ids)]);
  }

}
