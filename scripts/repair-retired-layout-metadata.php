<?php

declare(strict_types=1);

/**
 * @file
 * Previews or repairs retired layout metadata without booting field plugins.
 */

use Drupal\Core\Config\BootstrapConfigStorageFactory;
use Drupal\Component\FileCache\FileCacheFactory;
use Drupal\Core\Config\DatabaseStorage;
use Drupal\Core\Database\Database;
use Drupal\Core\DrupalKernel;
use Drupal\Core\Extension\ExtensionDiscovery;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Yaml\Yaml;

if (PHP_SAPI !== 'cli') {
  exit(1);
}
$options = getopt('', ['uri:', 'site-path:', 'apply', 'confirm:', 'backup:', 'layout-migrated']);
if (empty($options['uri'])) {
  fwrite(STDERR, "Usage: php scripts/repair-retired-layout-metadata.php --uri=https://site.example\n");
  exit(1);
}
try {
  $root = dirname(__DIR__) . '/docroot';
  $loader = require $root . '/autoload.php';
  $request = Request::create($options['uri']);
  if (!in_array($request->getScheme(), ['http', 'https'], TRUE)) {
    throw new RuntimeException('An HTTP or HTTPS site URI is required.');
  }
  // Multisite discovery uses the web entry point, not this CLI script's filename.
  $request->server->set('SCRIPT_NAME', rtrim($request->getPathInfo(), '/') . '/index.php');
  $request->server->set('SCRIPT_FILENAME', $root . '/index.php');
  // Initializing settings selects the database; booting the kernel would load broken plugins.
  $kernel = DrupalKernel::createFromRequest($request, $loader, 'prod', FALSE, $root);
  $sitePath = $kernel->getSitePath();
  $expectedSitePath = $options['site-path'] ?? 'sites/' . $request->getHost();
  if ($sitePath !== $expectedSitePath) {
    throw new RuntimeException('Selected site path "' . $sitePath . '" differs from expected "'
      . $expectedSitePath . '". Confirm the target mapping before passing --site-path explicitly.');
  }
  // Read current extension files without booting the kernel or consulting a stale file cache.
  FileCacheFactory::setConfiguration([FileCacheFactory::DISABLE_CACHE => TRUE]);
  $storage = BootstrapConfigStorageFactory::get($loader);
  if (!$storage instanceof DatabaseStorage) {
    throw new RuntimeException('This repair requires database-backed active configuration.');
  }
  $database = Database::getConnection();
  $apply = isset($options['apply']);
  $transaction = $apply ? $database->startTransaction() : NULL;
  $configQuery = $database->select('config', 'c')->fields('c')->condition('collection', '');
  $schemaQuery = $database->select('key_value', 'k')->fields('k')->condition('collection', 'system.schema');
  if ($apply) {
    $configQuery->forUpdate();
    $schemaQuery->forUpdate();
  }
  $configRows = array_column($configQuery->execute()->fetchAll(PDO::FETCH_ASSOC), NULL, 'name');
  $schemaRows = array_column($schemaQuery->execute()->fetchAll(PDO::FETCH_ASSOC), NULL, 'name');
  $configs = [];
  foreach ($configRows as $name => $row) {
    $configs[$name] = unserialize($row['data'], ['allowed_classes' => FALSE]);
    if (!is_array($configs[$name])) {
      throw new RuntimeException('Invalid active configuration: ' . $name);
    }
  }
  $siteUuid = $configs['system.site']['uuid'] ?? '';
  $extension = $configs['core.extension'] ?? [];
  if ($siteUuid === '' || !isset($extension['module'])) {
    throw new RuntimeException('Installed site identity and module configuration are required.');
  }
  $discovery = new ExtensionDiscovery($root, TRUE, NULL, $sitePath);
  $available = $discovery->scan('module') + $discovery->scan('profile');
  $retired = ['ckeditor', 'ckeditor_textindent', 'codesnippet', 'color', 'conflict',
    'key_value', 'panelizer', 'panels', 'panels_ipe', 'rdf', 'switch_page_theme'];
  $removedModules = $removedSchemas = $resetModules = $deleted = $changed = $blockers = $retainedSearchConfig = [];
  foreach ($retired as $name) {
    if (isset($available[$name])) {
      continue;
    }
    if (isset($extension['module'][$name])) {
      $removedModules[] = $name;
      unset($extension['module'][$name]);
    }
    if (isset($schemaRows[$name])) {
      $removedSchemas[] = $name;
    }
  }
  foreach (array_keys($extension['module']) as $name) {
    if (!isset($available[$name])) {
      $blockers[] = 'Unreviewed missing module: ' . $name;
      continue;
    }
    $info = Yaml::parseFile($root . '/' . $available[$name]->getPathname());
    foreach ($info['dependencies'] ?? [] as $dependency) {
      $dependency = preg_replace('/[ (].*$/', '', $dependency);
      $dependency = basename(str_replace(':', '/', $dependency));
      if (in_array($dependency, $removedModules, TRUE)) {
        $blockers[] = $name . ' still requires ' . $dependency;
      }
    }
  }
  // Reset registration only without installed data. Search's transferred optional config is retained.
  foreach (['xinshi_ai_usage', 'search_node'] as $name) {
    if (!isset($extension['module'][$name]) || isset($schemaRows[$name]) || !isset($available[$name])) {
      continue;
    }
    $modulePath = $root . '/' . dirname($available[$name]->getPathname());
    if (is_file($modulePath . '/' . $name . '.install')) {
      require_once $modulePath . '/' . $name . '.install';
    }
    $schemaFunction = $name . '_schema';
    $tables = function_exists($schemaFunction) ? array_keys($schemaFunction()) : [];
    $owned = [];
    foreach ($tables as $table) {
      if ($database->schema()->tableExists($table)) {
        $owned[] = 'table ' . $table;
      }
    }
    $configFiles = array_merge(glob($modulePath . '/config/install/*.yml') ?: [],
      glob($modulePath . '/config/optional/*.yml') ?: []);
    foreach ($configFiles as $file) {
      $configName = basename($file, '.yml');
      if (isset($configs[$configName])) {
        // Core moved these defaults out of Search; optional installation skips existing config.
        if ($name === 'search_node' && basename(dirname($file)) === 'optional') {
          $retainedSearchConfig[] = $configName;
          continue;
        }
        $owned[] = 'configuration ' . $configName;
      }
    }
    if ($owned !== []) {
      $blockers[] = 'Incomplete ' . $name . ' has retained installation data: ' . implode(', ', $owned);
      continue;
    }
    $resetModules[] = $name;
    unset($extension['module'][$name]);
  }
  $fields = ['field.field.node.job.panelizer', 'field.field.node.landing_page.panelizer',
    'field.storage.node.panelizer'];
  foreach ($configs as $name => $config) {
    if (in_array($name, $fields, TRUE) && ($config['field_type'] ?? $config['type'] ?? '') !== 'panelizer') {
      $blockers[] = 'Unexpected field type in ' . $name;
    }
    if (str_starts_with($name, 'field.') && ($config['field_type'] ?? $config['type'] ?? '') === 'panelizer') {
      $expectedId = str_replace(['field.field.', 'field.storage.'], '', $name);
      if (!in_array($name, $fields, TRUE) || ($config['entity_type'] ?? '') !== 'node'
        || ($config['field_name'] ?? '') !== 'panelizer' || ($config['id'] ?? '') !== $expectedId
        || (str_starts_with($name, 'field.field.') && ($config['bundle'] ?? '') !== explode('.', $name)[3])) {
        $blockers[] = 'Unreviewed Panelizer field: ' . $name;
      }
      elseif (isset($available['panelizer'])) {
        $blockers[] = 'Panelizer code is available; use its normal field and module removal workflow.';
      }
      else {
        $deleted[] = $name;
      }
    }
    if (preg_match('/^core\.entity_(view|form)_display\.node\.(job|landing_page)\./', $name)) {
      $updated = $config;
      unset($updated['content']['panelizer'], $updated['hidden']['panelizer'], $updated['third_party_settings']['panelizer']);
      foreach (['config' => $fields, 'module' => ['panelizer', 'panels', 'panels_ipe']] as $type => $values) {
        if (isset($updated['dependencies'][$type])) {
          $updated['dependencies'][$type] = array_values(array_diff($updated['dependencies'][$type], $values));
        }
      }
      if ($updated !== $config) {
        $changed[$name] = $updated;
      }
    }
  }
  if ($extension !== $configs['core.extension']) {
    $changed['core.extension'] = $extension;
  }
  $editors = array_keys(array_filter($configs, static fn($config, $name) =>
    str_starts_with($name, 'editor.editor.') && ($config['editor'] ?? '') === 'ckeditor', ARRAY_FILTER_USE_BOTH));
  $originals = array_intersect_key($configRows, array_flip([...$deleted, ...array_keys($changed)]));
  $schemas = array_intersect_key($schemaRows, array_flip($removedSchemas));
  ksort($originals);
  ksort($schemas);
  $hash = hash('sha256', serialize([$siteUuid, $originals, $schemas, $deleted, $changed, $blockers]));
  $report = ['status' => 'preview', 'site_path' => $sitePath, 'site_uuid' => $siteUuid,
    'remove_module_flags' => $removedModules, 'remove_schema_records' => $removedSchemas,
    'reset_incomplete_modules' => $resetModules, 'remove_field_config' => $deleted,
    'update_config' => array_keys($changed), 'retained_ckeditor4_config' => $editors,
    'retained_search_node_config' => $retainedSearchConfig,
    'blockers' => $blockers, 'confirmation_hash' => $hash];
  if ($apply) {
    if ($blockers !== [] || !isset($options['layout-migrated']) || !hash_equals($hash, $options['confirm'] ?? '')) {
      throw new RuntimeException('Apply requires a blocker-free current preview, --layout-migrated and its --confirm hash.');
    }
    $backup = $options['backup'] ?? '';
    $parent = realpath(dirname($backup));
    if ($backup === '' || !str_starts_with($backup, '/') || !$parent || $parent === realpath($root)
      || str_starts_with($parent, realpath($root) . '/') || file_exists($backup)) {
      throw new RuntimeException('Use a new absolute --backup file in an existing private directory outside docroot.');
    }
    $snapshot = ['site_uuid' => $siteUuid, 'site_path' => $sitePath, 'confirmation_hash' => $hash,
      'config' => $originals, 'system_schema' => $schemas];
    foreach ($snapshot['config'] as &$row) { $row['data'] = base64_encode($row['data']); }
    unset($row);
    foreach ($snapshot['system_schema'] as &$row) { $row['value'] = base64_encode($row['value']); }
    unset($row);
    $previousMask = umask(0077);
    $file = fopen($backup, 'x');
    umask($previousMask);
    $encoded = json_encode($snapshot, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    if (!$file || fwrite($file, $encoded) !== strlen($encoded) || !fflush($file) || !fsync($file)) {
      throw new RuntimeException('Metadata backup could not be completed; nothing was changed.');
    }
    fclose($file);
    foreach ($deleted as $name) {
      if ($database->delete('config')->condition('collection', '')->condition('name', $name)->execute() !== 1) {
        throw new RuntimeException('Could not delete field metadata: ' . $name);
      }
    }
    foreach ($changed as $name => $config) {
      // DatabaseStorage::write() may attempt DDL after an error, which commits a MySQL transaction.
      $count = $database->update('config')->fields(['data' => $storage->encode($config)])
        ->condition('collection', '')->condition('name', $name)->execute();
      if ($count !== 1) {
        throw new RuntimeException('Could not write configuration: ' . $name);
      }
    }
    if ($removedSchemas !== []) {
      $database->delete('key_value')->condition('collection', 'system.schema')
        ->condition('name', $removedSchemas, 'IN')->execute();
    }
    unset($transaction);
    $report['status'] = 'applied';
    $report['backup'] = $backup;
  }
  echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;
}
catch (Throwable $error) {
  if (isset($transaction)) { $transaction->rollBack(); }
  fwrite(STDERR, $error->getMessage() . PHP_EOL);
  exit(1);
}
