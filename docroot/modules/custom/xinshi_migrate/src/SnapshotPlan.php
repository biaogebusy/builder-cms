<?php

namespace Drupal\xinshi_migrate;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Site\Settings;

/** Validates and applies one private, reviewed snapshot plan. */
final class SnapshotPlan {

  private ?array $plan = NULL;
  private bool $validatedTarget = FALSE;
  private array $validatedTables = [];

  public function __construct(private readonly Connection $target, private readonly ConfigFactoryInterface $config) {}

  public function data(): array {
    if ($this->plan === NULL) {
      $path = Settings::get('xinshi_migration_plan_path');
      $this->plan = $path && is_readable($path) ? json_decode(file_get_contents($path), TRUE, 512, JSON_THROW_ON_ERROR) : [];
    }
    return $this->plan;
  }

  public function tables(): array {
    return $this->data()['tables'] ?? [];
  }

  public function sourceKey(): string {
    return $this->data()['source_key'] ?? 'migrate';
  }

  public function table(string $table): array {
    if (!preg_match('/^[a-z][a-z0-9_]*$/D', $table) || !isset($this->tables()[$table])) {
      throw new \RuntimeException('Table is not in the prepared migration plan.');
    }
    return $this->tables()[$table];
  }

  public function validateTarget(): void {
    if ($this->validatedTarget) {
      return;
    }
    $plan = $this->data();
    if (PHP_SAPI !== 'cli' || !$plan || ($plan['target_uuid'] ?? NULL) !== $this->config->get('system.site')->get('uuid')) {
      throw new \RuntimeException('The migration plan does not match this CLI target site.');
    }
    $source = Database::getConnection('default', $this->sourceKey());
    $left = $source->getConnectionOptions();
    $right = $this->target->getConnectionOptions();
    $identity = static fn(array $options) => array_intersect_key($options, array_flip(['driver', 'host', 'port', 'database', 'prefix', 'unix_socket']));
    if ($identity($left) === $identity($right) || ($right['database'] ?? NULL) !== ($plan['target_database'] ?? NULL)) {
      throw new \RuntimeException('Source and target database isolation does not match the plan.');
    }
    $sourceSite = unserialize($source->select('config', 'c')->fields('c', ['data'])->condition('collection', '')->condition('name', 'system.site')->execute()->fetchField(), ['allowed_classes' => FALSE]);
    if (($sourceSite['uuid'] ?? NULL) !== ($plan['source_uuid'] ?? NULL) || $sourceSite['uuid'] === $plan['target_uuid']) {
      throw new \RuntimeException('Source site identity does not match the snapshot plan.');
    }
    $adminMail = $this->target->select('users_field_data', 'u')->fields('u', ['mail'])->condition('uid', 1)->condition('default_langcode', 1)->execute()->fetchField();
    if ($adminMail !== ($plan['target_admin_email'] ?? NULL)) {
      throw new \RuntimeException('The new-site administrator email has not been prepared.');
    }
    $this->validatedTarget = TRUE;
  }

  private function validateTable(string $table): void {
    if (isset($this->validatedTables[$table])) {
      return;
    }
    $specification = $this->table($table);
    if (!$this->target->schema()->tableExists($table)) {
      throw new \RuntimeException('Destination data model is missing table: ' . $table);
    }
    foreach ($specification['columns'] as $column) {
      if (!$this->target->schema()->fieldExists($table, $column)) {
        throw new \RuntimeException('Destination data model is missing field: ' . $table . '.' . $column);
      }
    }
    $this->validatedTables[$table] = TRUE;
  }

  public function transform(string $table, array $record): array {
    $specification = $this->table($table);
    foreach ($specification['user_id_columns'] ?? [] as $column) {
      if (isset($record[$column]) && (string) $record[$column] === '1') {
        $record[$column] = (string) $this->data()['source_admin_destination_uid'];
      }
    }
    foreach ($specification['user_path_columns'] ?? [] as $column) {
      if (isset($record[$column])) {
        $record[$column] = preg_replace('@^((?:internal:)?/user/)1(?=/|$)@', '${1}' . $this->data()['source_admin_destination_uid'], $record[$column]);
      }
    }
    foreach ($specification['polymorphic_user_id_columns'] ?? [] as $column => $typeColumn) {
      if (($record[$typeColumn] ?? NULL) === 'user' && (string) ($record[$column] ?? '') === '1') {
        $record[$column] = (string) $this->data()['source_admin_destination_uid'];
      }
    }
    foreach ($specification['constant_columns'] ?? [] as $column => $value) {
      $record[$column] = $value;
    }
    if ($table === 'key_value' && $record['collection'] === 'pathauto_state.user' && $record['name'] === '1') {
      $record['name'] = (string) $this->data()['source_admin_destination_uid'];
    }
    if ($table === 'users_data' && $record['name'] === 'otp_user_data'
      && in_array($record['module'], ['otp_login', 'xinshi_sms'], TRUE)) {
      $metadata = unserialize($record['value'], ['allowed_classes' => FALSE]);
      if (!is_array($metadata)) {
        throw new \RuntimeException('Unexpected OTP user metadata format.');
      }
      // Mobile-number bindings remain; old login challenges and sessions do not.
      $metadata['otps'] = [];
      $metadata['sessions'] = [];
      $metadata['last_otp_time'] = 0;
      $record['value'] = serialize($metadata);
    }
    return $record;
  }

  public static function checksum(array $record): string {
    ksort($record);
    return hash('sha256', serialize(array_map(static fn($value) => $value === NULL ? NULL : (string) $value, $record)));
  }

  private function key(string $table, array $record): array {
    $key = [];
    foreach (array_keys($this->table($table)['ids']) as $column) {
      $key[$column] = $record[$column];
    }
    return $key;
  }

  private function existing(string $table, array $key): array|false {
    $query = $this->target->select($table, 't')->fields('t', $this->table($table)['columns']);
    foreach ($key as $column => $value) {
      $query->condition($column, $value);
    }
    return $query->execute()->fetchAssoc();
  }

  private function isAnonymous(string $table, array $record): bool {
    return in_array($table, ['users', 'users_field_data'], TRUE) && (string) ($record['uid'] ?? '') === '0';
  }

  public function write(string $table, array $record): array {
    $this->validateTarget();
    $this->validateTable($table);
    $record = $this->transform($table, $record);
    $key = $this->key($table, $record);
    if ($this->isAnonymous($table, $record)) {
      return array_values($key);
    }
    $rowKey = self::checksum($key);
    $dataset = $this->data()['source_sha256'];
    $checksum = self::checksum($record);
    $transaction = $this->target->startTransaction();
    try {
      $ledger = $this->target->select('xinshi_migrate_rows', 'r')->fields('r')->condition('table_name', $table)->condition('row_key', $rowKey)->execute()->fetchAssoc();
      $existing = $this->existing($table, $key);
      if ($ledger) {
        if ($ledger['dataset'] !== $dataset || ($existing && self::checksum($existing) !== $ledger['checksum'])) {
          throw new \RuntimeException('Destination row changed outside this snapshot batch: ' . $table);
        }
      }
      elseif ($existing) {
        throw new \RuntimeException('Destination row already exists without batch ownership: ' . $table);
      }
      if ($existing) {
        $query = $this->target->update($table)->fields($record);
        foreach ($key as $column => $value) {
          $query->condition($column, $value);
        }
        $query->execute();
      }
      else {
        $this->target->insert($table)->fields($record)->execute();
      }
      $this->target->merge('xinshi_migrate_rows')->keys(['table_name' => $table, 'row_key' => $rowKey])->fields([
        'dataset' => $dataset, 'target_key' => serialize($key), 'checksum' => $checksum,
      ])->execute();
      unset($transaction);
      return array_values($key);
    }
    catch (\Throwable $error) {
      $transaction->rollBack();
      throw $error;
    }
  }

  public function rollback(string $table, array $identifiers): void {
    $this->validateTarget();
    $key = array_combine(array_keys($this->table($table)['ids']), array_values($identifiers));
    if ($this->isAnonymous($table, $key)) {
      return;
    }
    $rowKey = self::checksum($key);
    $ledger = $this->target->select('xinshi_migrate_rows', 'r')->fields('r')->condition('table_name', $table)->condition('row_key', $rowKey)->execute()->fetchAssoc();
    if (!$ledger) {
      return;
    }
    $existing = $this->existing($table, $key);
    if ($ledger['dataset'] !== $this->data()['source_sha256'] || ($existing && self::checksum($existing) !== $ledger['checksum'])) {
      throw new \RuntimeException('Refusing to roll back an unrelated or changed destination row.');
    }
    $transaction = $this->target->startTransaction();
    try {
      $query = $this->target->delete($table);
      foreach ($key as $column => $value) {
        $query->condition($column, $value);
      }
      $query->execute();
      $this->target->delete('xinshi_migrate_rows')->condition('table_name', $table)->condition('row_key', $rowKey)->execute();
      unset($transaction);
    }
    catch (\Throwable $error) {
      $transaction->rollBack();
      throw $error;
    }
  }

}
