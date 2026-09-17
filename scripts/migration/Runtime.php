<?php

namespace Xinshi\Migration;

use Drupal\Core\Database\Database;

/** A public error code that never contains database values or credentials. */
final class GuardFailure extends \RuntimeException {}

/** Binds the reviewed snapshot inputs to one explicitly configured target. */
final class Runtime {

  private static ?self $instance = NULL;
  private string $directory;
  private array $settings;
  private array $plan;
  private array $manifest;
  private $lock;

  public static function initialize(string $directory): void {
    $runtime = new self();
    $resolved = realpath($directory);
    if (PHP_SAPI !== 'cli' || !$resolved || !is_dir($resolved) || !is_writable($resolved)) {
      self::reject('invalid_private_batch_directory');
    }
    $root = realpath(\Drupal::root());
    if (!$root || $resolved === $root || str_starts_with($resolved, $root . DIRECTORY_SEPARATOR)) {
      self::reject('batch_directory_is_inside_public_docroot');
    }
    $runtime->directory = $resolved;
    self::$instance = $runtime;
    $runtime->lock = fopen(self::path('.execution.lock'), 'c');
    if (!$runtime->lock || !flock($runtime->lock, LOCK_EX | LOCK_NB)) {
      self::reject('another_step_is_running_in_this_batch');
    }
    $runtime->settings = self::read('batch-runtime.json');
    $runtime->manifest = self::read('input-manifest.json');
    if (($runtime->settings['format'] ?? NULL) !== 1 || ($runtime->manifest['format'] ?? NULL) !== 1
      || !in_array($runtime->settings['environment'] ?? NULL, ['local', 'deployment'], TRUE)) {
      self::reject('unsupported_batch_format');
    }
    foreach (['source_database', 'target_database'] as $key) {
      if (!is_string($runtime->settings[$key] ?? NULL) || $runtime->settings[$key] === '') {
        self::reject('expected_database_names_are_required');
      }
    }
    foreach (['full-snapshot-plan.private.json', 'full-snapshot-plan-report.json', 'model-config-plan.private.json', 'model-config-plan-report.json', 'reference-selected-ids.json', 'source-exception-dispositions.private.json'] as $name) {
      if (!isset($runtime->manifest['files'][$name])) {
        self::reject('input_manifest_is_incomplete');
      }
    }
    foreach ($runtime->manifest['files'] as $name => $digest) {
      if (!is_string($digest) || !preg_match('/^[a-f0-9]{64}$/D', $digest)
        || !is_file(self::path($name)) || hash_file('sha256', self::path($name)) !== $digest) {
        self::reject('reviewed_input_hash_mismatch');
      }
    }
    $runtime->plan = self::read('full-snapshot-plan.private.json');
    if (($runtime->plan['source_sha256'] ?? NULL) !== ($runtime->manifest['source_snapshot_sha256'] ?? NULL)
      || ($runtime->plan['selection_sha256'] ?? NULL) !== hash_file('sha256', self::path('reference-selected-ids.json'))
      || ($runtime->plan['configuration_sha256'] ?? NULL) !== hash_file('sha256', self::path('model-config-plan.private.json'))) {
      self::reject('snapshot_inputs_do_not_match');
    }
    // Database names may differ after a restore; source and target UUIDs may not.
    $runtime->plan['target_database'] = self::targetDatabase();
    $runtime->validateConnections();
    self::write('full-snapshot-runtime-plan.private.json', $runtime->plan);
  }

  public static function requireEntryPoint(): void {
    if (!self::$instance || PHP_SAPI !== 'cli') {
      self::reject('use_the_migration_run_entry_point');
    }
  }

  public static function path(string $name): string {
    self::requireEntryPoint();
    if (str_contains($name, "\0") || str_starts_with($name, '/') || str_contains($name, '\\')
      || in_array('..', explode('/', $name), TRUE)) {
      self::reject('invalid_batch_file_path');
    }
    $path = self::$instance->directory . '/' . $name;
    $resolved = realpath($path);
    if ($resolved && $resolved !== self::$instance->directory
      && !str_starts_with($resolved, self::$instance->directory . '/')) {
      self::reject('batch_file_escapes_private_directory');
    }
    return $path;
  }

  public static function read(string $name): array {
    $path = self::path($name);
    if (!is_readable($path)) {
      self::reject('required_batch_file_is_missing');
    }
    $data = json_decode(file_get_contents($path), TRUE, 512, JSON_THROW_ON_ERROR);
    if (!is_array($data)) {
      self::reject('invalid_batch_json');
    }
    return $data;
  }

  public static function write(string $name, array $data): void {
    $path = self::path($name);
    $temporary = tempnam(self::$instance->directory, '.snapshot-');
    if ($temporary === FALSE) {
      self::reject('cannot_write_batch_report');
    }
    try {
      chmod($temporary, 0600);
      $contents = json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
      if (file_put_contents($temporary, $contents) !== strlen($contents) || !rename($temporary, $path)) {
        self::reject('cannot_write_batch_report');
      }
    }
    finally {
      if (is_file($temporary)) {
        unlink($temporary);
      }
    }
  }

  public static function plan(): array {
    self::requireEntryPoint();
    return self::$instance->plan;
  }

  public static function planPath(): string {
    return self::path('full-snapshot-runtime-plan.private.json');
  }

  public static function sourceKey(): string {
    return self::plan()['source_key'];
  }

  public static function targetDatabase(): string {
    self::requireEntryPoint();
    return self::$instance->settings['target_database'];
  }

  public static function environment(): string {
    self::requireEntryPoint();
    return self::$instance->settings['environment'];
  }

  private function validateConnections(): void {
    $source = Database::getConnection('default', self::sourceKey());
    $target = Database::getConnection();
    foreach (['source' => $source, 'target' => $target] as $kind => $connection) {
      $options = $connection->getConnectionOptions();
      if ($connection->driver() !== 'mysql' || !empty($options['prefix'])) {
        self::reject('requires_mysql_without_table_prefix');
      }
      $expected = $this->settings[$kind . '_database'];
      if (($options['database'] ?? NULL) !== $expected || $connection->query('SELECT DATABASE()')->fetchField() !== $expected) {
        self::reject($kind . '_database_identity_mismatch');
      }
      $serialized = $connection->select('config', 'c')->fields('c', ['data'])->condition('collection', '')->condition('name', 'system.site')->execute()->fetchField();
      $site = is_string($serialized) ? unserialize($serialized, ['allowed_classes' => FALSE]) : FALSE;
      if (!is_array($site) || ($site['uuid'] ?? NULL) !== ($this->plan[$kind . '_uuid'] ?? NULL)) {
        self::reject($kind . '_site_identity_mismatch');
      }
    }
    if ($this->plan['source_uuid'] === $this->plan['target_uuid']) {
      self::reject('source_and_target_are_not_isolated');
    }
    // Inspect privileges without printing account names or grant statements.
    foreach ($source->query('SHOW GRANTS')->fetchCol() as $grant) {
      if (!preg_match('/^GRANT (.+?) ON /i', $grant, $match)
        || array_diff(array_map('trim', explode(',', strtoupper($match[1]))), ['USAGE', 'SELECT', 'SHOW VIEW'])) {
        self::reject('source_connection_requires_a_read_only_account');
      }
    }
    $source->query('SET SESSION wait_timeout = 7200');
    $target->query('SET SESSION wait_timeout = 7200');
  }

  public static function ownedRows(): int {
    $db = Database::getConnection();
    return $db->schema()->tableExists('xinshi_migrate_rows') ? (int) $db->select('xinshi_migrate_rows', 'r')->countQuery()->execute()->fetchField() : 0;
  }

  public static function pendingExceptions(): array {
    $items = self::read('source-exception-dispositions.private.json')['items'];
    $accepted = self::$instance->settings['accepted_source_exceptions'] ?? [];
    if (!is_array($accepted) || array_diff($accepted, array_keys($items))) {
      self::reject('invalid_source_exception_decisions');
    }
    return array_values(array_filter(array_keys($items), static fn($key) => $items[$key]['approval'] !== 'approved' && !in_array($key, $accepted, TRUE)));
  }

  public static function assertAction(string $action): void {
    $initialization = ['backup', 'install-modules', 'adapt-block-body', 'install-config', 'map-config-users', 'clear-generated-rows'];
    if (in_array($action, $initialization, TRUE)) {
      $db = Database::getConnection();
      if (self::ownedRows() || (int) $db->select('users', 'u')->countQuery()->execute()->fetchField() !== 2
        || (int) $db->select('users', 'u')->condition('uid', [0, 1], 'IN')->countQuery()->execute()->fetchField() !== 2) {
        self::reject('refusing_to_reinitialize_a_populated_target');
      }
      if ($action !== 'backup' || is_file(self::path('target-before-model.sql'))) {
        $backup = self::read('target-before-model-backup.json');
        if (!is_file(self::path('target-before-model.sql'))
          || hash_file('sha256', self::path('target-before-model.sql')) !== ($backup['sha256'] ?? NULL)
          || ($backup['target_uuid'] ?? NULL) !== self::plan()['target_uuid']
          || ($backup['target_database'] ?? NULL) !== self::targetDatabase()) {
          self::reject('verified_target_recovery_point_is_required');
        }
      }
    }
    if ($action === 'import') {
      if (self::pendingExceptions()) {
        self::reject('source_exception_decisions_are_pending');
      }
      if (!\Drupal::moduleHandler()->moduleExists('xinshi_migrate')) {
        self::reject('install_the_migration_model_first');
      }
      if (self::ownedRows() && !is_file(self::path('full-snapshot-import-report.json'))) {
        self::reject('existing_batch_requires_its_original_execution_state');
      }
      $backup = self::read('target-before-model-backup.json');
      if (!is_file(self::path('target-before-model.sql')) || hash_file('sha256', self::path('target-before-model.sql')) !== $backup['sha256']) {
        self::reject('verified_target_recovery_point_is_required');
      }
    }
  }

  public static function oauthSigningFiles(): array {
    $options = self::$instance->settings['oauth_signing'] ?? [];
    if (($options['mode'] ?? NULL) === 'local_generated') {
      if (self::environment() !== 'local') {
        self::reject('deployment_requires_explicit_oauth_signing_files');
      }
      $directory = self::path('oauth-local');
      if (!is_dir($directory) && !mkdir($directory, 0700)) {
        self::reject('cannot_create_local_signing_directory');
      }
      $files = ['private_key' => $directory . '/private.key', 'public_key' => $directory . '/public.key'];
      if (!is_file($files['private_key']) && !is_file($files['public_key'])) {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        if (!$key || !openssl_pkey_export($key, $privateKey)) {
          self::reject('cannot_create_local_signing_key');
        }
        file_put_contents($files['private_key'], $privateKey);
        file_put_contents($files['public_key'], openssl_pkey_get_details($key)['key']);
        unset($privateKey, $key);
      }
    }
    elseif (($options['mode'] ?? NULL) === 'existing') {
      $files = array_intersect_key($options, array_flip(['private_key', 'public_key']));
    }
    else {
      self::reject('configure_target_oauth_signing_files');
    }
    foreach (['private_key', 'public_key'] as $kind) {
      $path = isset($files[$kind]) && is_string($files[$kind]) ? realpath($files[$kind]) : FALSE;
      if (!$path || !is_readable($path) || str_starts_with($path, realpath(\Drupal::root()) . '/')) {
        self::reject('oauth_signing_files_must_be_readable_and_outside_docroot');
      }
      $files[$kind] = $path;
    }
    $private = openssl_pkey_get_private(file_get_contents($files['private_key']));
    $public = openssl_pkey_get_public(file_get_contents($files['public_key']));
    if (!$private || !$public || openssl_pkey_get_details($private)['key'] !== openssl_pkey_get_details($public)['key']) {
      self::reject('oauth_signing_files_are_not_a_matching_pair');
    }
    return $files;
  }

  public static function reject(string $reason): never {
    throw new GuardFailure($reason);
  }

}
