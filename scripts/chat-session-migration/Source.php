<?php

declare(strict_types=1);

namespace Xinshi\ChatMigration;

use Drupal\Core\Database\Connection;
use Drupal\xinshi_ai\Service\ChatSessionFields;
use Drupal\xinshi_ai\Service\ChatSessionFieldScan;

/** Frozen Drupal SQL snapshots; this class never saves entities or invokes task code. */
final class Source {

  private array $tables = [];
  private array $newTables = [];

  public function __construct(private readonly Connection $db, public readonly string $site,
    public readonly int $targetVersion = 2) {
    ChatSessionFields::definitions($targetVersion);
    if (!\Drupal\Component\Uuid\Uuid::isValid($site) ||
      \Drupal::config('system.site')->get('uuid') !== $site) {
      throw new \RuntimeException('site_identity_mismatch');
    }
    $entity_type = \Drupal::entityTypeManager()->getDefinition('node');
    $names = [$entity_type->getBaseTable(), $entity_type->getDataTable(),
      $entity_type->getRevisionTable(), $entity_type->getRevisionDataTable()];
    if ($names !== ['node', 'node_field_data', 'node_revision', 'node_field_revision']) {
      throw new \RuntimeException('unsupported_node_storage');
    }
    foreach ($names as $table) {
      $this->tables[$table] = ['id' => 'nid', 'keys' => match ($table) {
        'node' => ['nid'], 'node_field_data' => ['nid', 'langcode'],
        'node_revision' => ['nid', 'vid'], default => ['nid', 'vid', 'langcode'],
      }];
    }
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $mapping = $storage->getTableMapping();
    $definitions = \Drupal::service('entity_field.manager')->getFieldStorageDefinitions('node');
    $fields = array_unique(array_merge(...array_values(array_map('array_keys', ChatSessionFieldScan::sourceDefinitions()))));
    $new_fields = array_keys(ChatSessionFields::definitions());
    if ($targetVersion === 3) {
      $fields = [...$fields, ...$new_fields];
      $new_fields = ['plan_context'];
    }
    foreach ([FALSE => $fields, TRUE => $new_fields] as $new => $list) {
      foreach ($list as $field) {
        if (!isset($definitions[$field])) {
          if ($new) {
            continue;
          }
          throw new \RuntimeException('source_field_missing');
        }
        $definition = $definitions[$field];
        if (!$mapping->requiresDedicatedTableStorage($definition)) {
          throw new \RuntimeException('unsupported_field_storage');
        }
        $columns = [];
        foreach (array_keys($definition->getColumns()) as $property) {
          $columns[$property] = $mapping->getFieldColumnName($definition, $property);
        }
        foreach ([FALSE => $mapping->getDedicatedDataTableName($definition),
          TRUE => $mapping->getDedicatedRevisionTableName($definition)] as $revision => $table) {
          $entry = ['id' => 'entity_id', 'keys' => ['entity_id', 'revision_id', 'deleted', 'langcode', 'delta'],
            'field' => $field, 'revision' => (bool) $revision, 'columns' => $columns];
          if ($new) {
            $this->newTables[$table] = $entry;
          }
          else {
            $this->tables[$table] = $entry;
          }
        }
      }
    }
    ksort($this->tables, SORT_STRING);
    ksort($this->newTables, SORT_STRING);
  }

  /** Maintenance is an additional gate; the operator must also stop every external writer. */
  public function frozen(string $acknowledgement): void {
    if ($acknowledgement !== 'writers-stopped' || !\Drupal::state()->get('system.maintenance_mode')) {
      throw new \RuntimeException('frozen_site_required');
    }
    if (!in_array($this->db->driver(), ['mysql', 'sqlite'], TRUE)) {
      throw new \RuntimeException('unsupported_database');
    }
    if ($this->db->inTransaction()) {
      throw new \RuntimeException('outer_transaction_rejected');
    }
    if ($this->db->driver() === 'mysql') {
      foreach (array_keys($this->layout()) as $table) {
        $engine = $this->db->query('SELECT ENGINE FROM information_schema.TABLES ' .
          'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table',
          [':table' => $this->db->getPrefix() . $table])->fetchField();
        if (!is_string($engine) || strtolower($engine) !== 'innodb') {
          throw new \RuntimeException('transactional_tables_required');
        }
      }
    }
  }

  /** Recheck the shared schema, without changing or adapting deployment configuration. */
  public function scan(bool $installed = FALSE): array {
    $report = (new ChatSessionFieldScan(\Drupal::service('entity_field.manager'),
      \Drupal::entityTypeManager(), \Drupal::service('jsonapi.resource_type.repository')))->scan($this->targetVersion);
    if (!$report['matches'] || ($installed &&
      count(array_filter($report['targets'], static fn($value) => $value['installed'])) !==
        count(ChatSessionFields::definitions($this->targetVersion)))) {
      throw new \RuntimeException('field_configuration_mismatch');
    }
    if ($this->targetVersion === 3) {
      // The v2 fields are source configuration in this batch, even before Plan field installation.
      $report['sourceFingerprint'] = hash('sha256', Archive::json([
        $report['sourceFingerprint'], array_intersect_key($report['targets'], ChatSessionFields::definitions()),
      ]));
    }
    return $report;
  }

  /** IDs and UUIDs are bounded batches, including messages with no conversation. */
  public function inventory(): array {
    $nodes = [];
    $last = '0';
    do {
      $rows = $this->db->select('node', 'n')->fields('n', ['nid', 'uuid', 'type'])
        ->condition('type', ['conversation', 'ai_session'], 'IN')->condition('nid', $last, '>')
        ->orderBy('nid')->range(0, 250)->execute()->fetchAll(\PDO::FETCH_ASSOC);
      foreach ($rows as $row) {
        $nodes[(string) $row['nid']] = ['uuid' => (string) $row['uuid'], 'bundle' => (string) $row['type']];
        $last = (string) $row['nid'];
      }
    } while (count($rows) === 250);
    return $nodes;
  }

  /** All current and revision relations bind protected locators; dangling targets stay in SQL. */
  public function bindings(array $nodes): array {
    $bindings = [];
    foreach ($this->tables as $table => $definition) {
      if (($definition['field'] ?? NULL) !== 'sessions') {
        continue;
      }
      $group = NULL;
      $seen = [];
      for ($offset = 0;; $offset += 500) {
        $query = $this->db->select($table, 'r')->fields('r', ['entity_id', 'revision_id',
          'langcode', 'sessions_target_id'])->condition('r.bundle', 'conversation')->condition('r.deleted', 0);
        $query->leftJoin('node', 'target', 'target.nid = r.sessions_target_id');
        $query->addField('target', 'type', 'target_bundle');
        foreach ($definition['keys'] as $key) {
          $query->orderBy('r.' . $key);
        }
        $rows = $query->range($offset, 500)->execute()->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
          $next_group = [$row['entity_id'], $row['revision_id'], $row['langcode']];
          if ($group !== $next_group) {
            $group = $next_group;
            $seen = [];
          }
          if (isset($seen[$row['sessions_target_id']])) {
            throw new \RuntimeException('duplicate_relation_target');
          }
          $seen[$row['sessions_target_id']] = TRUE;
          if ($row['target_bundle'] !== NULL && $row['target_bundle'] !== 'ai_session') {
            throw new \RuntimeException('relation_target_type_mismatch');
          }
          $owner = $nodes[$row['entity_id']] ?? NULL;
          if (!$owner || $owner['bundle'] !== 'conversation') {
            throw new \RuntimeException('orphan_relation_owner');
          }
          $bindings[(string) $row['sessions_target_id']][$owner['uuid']] = TRUE;
        }
        if (count($rows) < 500) {
          break;
        }
      }
    }
    foreach ($bindings as &$chats) {
      $chats = array_keys($chats);
      sort($chats, SORT_STRING);
    }
    unset($chats);
    ksort($bindings, SORT_NATURAL);
    return $bindings;
  }

  /** Capture all active field tables, base properties, translations and revision metadata. */
  public function read(array $ids, bool $with_new = FALSE): array {
    $entities = array_fill_keys($ids, []);
    foreach ($this->layout($with_new) as $table => $definition) {
      foreach ($ids as $id) {
        $entities[$id][$table] = [];
      }
      $query = $this->db->select($table, 'r')->fields('r')->condition($definition['id'], $ids, 'IN');
      foreach ($definition['keys'] as $key) {
        $query->orderBy($key);
      }
      foreach ($query->execute() as $record) {
        $row = array_map(static fn($value) => $value === NULL ? NULL : (string) $value, (array) $record);
        ksort($row, SORT_STRING);
        $entities[$row[$definition['id']]][$table][] = $row;
      }
    }
    return $entities;
  }

  /** SQL mappings are discovered from the installed schema, never accepted from an artifact. */
  public function layout(bool $with_new = TRUE): array {
    $tables = $with_new ? $this->tables + $this->newTables : $this->tables;
    ksort($tables, SORT_STRING);
    return $tables;
  }

  /** Empty target tables must be proven even when fields were installed before export. */
  public function assertLegacy(): void {
    if ((\Drupal::config('xinshi_ai_reference.settings')->get('storage_version') ?? 1) !== $this->targetVersion - 1) {
      throw new \RuntimeException('source_storage_version_required');
    }
    foreach ($this->newTables as $table => $definition) {
      if ($this->db->select($table, 'r')->condition('bundle', 'ai_session')->countQuery()->execute()->fetchField()) {
        throw new \RuntimeException('target_fields_not_empty');
      }
    }
  }

  /** Preserve the original setting, including absence on an older installation. */
  public function settings(): array {
    $settings = \Drupal::configFactory()->getEditable('xinshi_ai_reference.settings');
    return ['exists' => !$settings->isNew(), 'data' => $settings->getRawData()];
  }

  /** Produce one converter input for each original revision and translation. */
  public function snapshots(array $entity, array $bindings): array {
    $node = $entity['node'][0] ?? NULL;
    if (!$node || count($entity['node']) !== 1) {
      throw new \RuntimeException('invalid_node_snapshot');
    }
    if ($node['type'] !== 'ai_session') {
      return [];
    }
    $snapshots = [];
    $revisions = [];
    foreach ($entity['node_revision'] as $revision) {
      $revisions[$revision['vid']] = $revision['langcode'];
    }
    if (!isset($revisions[$node['vid']])) {
      throw new \RuntimeException('default_revision_missing');
    }
    foreach ($this->layout(FALSE) as $table => $definition) {
      if (!isset($definition['field'])) {
        continue;
      }
      foreach ($entity[$table] as $row) {
        if ($row['deleted'] !== '0') {
          continue;
        }
        if ($row['bundle'] !== 'ai_session' || !isset($revisions[$row['revision_id']]) ||
          (!$definition['revision'] && $row['revision_id'] !== $node['vid'])) {
          throw new \RuntimeException('field_revision_identity_mismatch');
        }
      }
    }
    foreach ($entity['node_field_revision'] as $translation) {
      $vid = $translation['vid'];
      $language = $revisions[$vid] ?? NULL;
      if ($language === NULL) {
        throw new \RuntimeException('missing_revision_metadata');
      }
      if ($vid === $node['vid']) {
        $current = array_values(array_filter($entity['node_field_data'], static fn($row) =>
          $row['langcode'] === $translation['langcode']));
        if (count($current) !== 1 || $current[0]['vid'] !== $vid ||
          $current[0]['title'] !== $translation['title']) {
          throw new \RuntimeException('current_revision_title_diverged');
        }
      }
      $fields = ['title' => $translation['title']];
      $definitions = ChatSessionFieldScan::sourceDefinitions()['ai_session'];
      if ($this->targetVersion === 3) {
        $definitions += ChatSessionFields::definitions();
      }
      foreach ($definitions as $name => $definition) {
        $items = $this->fieldRows($entity, $name, TRUE, $vid);
        $default = array_values(array_filter($items, static fn($row) => $row['langcode'] === $language));
        if (count($default) > ($definition['cardinality'] ?? 1) ||
          array_column($default, 'delta') !== array_map('strval', array_keys($default))) {
          throw new \RuntimeException('invalid_source_cardinality');
        }
        $value = $default[0][$name . '_value'] ?? NULL;
        $fields[$name] = match ($name) {
          'content' => $default === [] ? NULL : (object) ['value' => $value,
            'format' => $default[0]['content_format'] ?? NULL],
          'total_tokens', 'storage_version', 'edit_version', 'run_created_at_ms' =>
            $value === NULL ? NULL : $this->integer($value),
          'uiparsing', 'is_error' => $value === NULL ? NULL : match ($value) {
            '0' => FALSE, '1' => TRUE, default => throw new \RuntimeException('invalid_source_boolean'),
          },
          'knowledge_refs' => array_column($default, 'knowledge_refs_value'),
          'ui_review', 'approval_timeline', 'result_reference' => $value === NULL ? NULL :
            \Drupal\xinshi_ai_reference\ChatSessionFormat::decode($value),
          default => $value,
        };
        // Stale nondefault copies must not be silently discarded by a nontranslatable target.
        $normalize = static function (array $rows): array {
          return array_map(static function ($row) {
            unset($row['langcode']);
            return $row;
          }, $rows);
        };
        foreach (array_unique(array_column($items, 'langcode')) as $copy_language) {
          $copy = array_values(array_filter($items, static fn($row) => $row['langcode'] === $copy_language));
          if ($normalize($copy) !== $normalize($default)) {
            throw new \RuntimeException('nontranslatable_source_diverged');
          }
        }
        if ($vid === $node['vid']) {
          $current = $this->fieldRows($entity, $name, FALSE, $vid);
          if ($current !== $items) {
            throw new \RuntimeException('current_revision_fields_diverged');
          }
        }
      }
      // Keep the converter's input member order stable across repeated exports.
      $ordered = [];
      foreach (['title', 'session_role', 'content', 'reasoning_content', 'summary',
        'total_tokens', 'img', 'uiparsing', 'style_preset'] as $name) {
        $ordered[$name] = $fields[$name];
      }
      if ($this->targetVersion === 3) {
        foreach (array_keys(ChatSessionFields::definitions()) as $name) {
          $ordered[$name] = $fields[$name];
        }
        \Drupal\xinshi_ai_reference\ChatSessionFormat::validate((object) $ordered,
          $node['uuid'], $bindings[$node['nid']] ?? []);
        if ($ordered['storage_version'] !== 2) {
          throw new \RuntimeException('source_storage_version_required');
        }
      }
      $snapshots[] = ['source' => ['site' => $this->site, 'entityUuid' => $node['uuid'],
        'revisionId' => $vid, 'langcode' => $translation['langcode']],
        'conversationIds' => $bindings[$node['nid']] ?? [], 'fields' => (object) $ordered];
    }
    if (!$snapshots || count($revisions) !== count(array_unique(array_column(array_column($snapshots, 'source'), 'revisionId')))) {
      throw new \RuntimeException('missing_revision_translation');
    }
    $current_languages = array_column($entity['node_field_data'], 'langcode');
    $revision_languages = array_column(array_values(array_filter($entity['node_field_revision'],
      static fn($row) => $row['vid'] === $node['vid'])), 'langcode');
    sort($current_languages, SORT_STRING);
    sort($revision_languages, SORT_STRING);
    if ($current_languages !== $revision_languages) {
      throw new \RuntimeException('current_translation_set_changed');
    }
    return $snapshots;
  }

  /** Stored items remain in physical delta order, including explicit empty strings. */
  public function fieldRows(array $entity, string $field, bool $revision, string $vid): array {
    foreach ($this->layout() as $table => $definition) {
      if (($definition['field'] ?? NULL) === $field && $definition['revision'] === $revision) {
        return array_values(array_filter($entity[$table] ?? [], static fn($row) =>
          $row['deleted'] === '0' && $row['revision_id'] === $vid));
      }
    }
    throw new \RuntimeException('field_table_missing');
  }

  private function integer(string $value): int {
    if (!preg_match('/^-?(?:0|[1-9][0-9]*)$/D', $value) ||
      filter_var($value, FILTER_VALIDATE_INT) === FALSE) {
      throw new \RuntimeException('invalid_source_integer');
    }
    return (int) $value;
  }

}
