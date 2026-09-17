<?php

/**
 * Example settings.php fragment: target remains the default connection.
 * Supply these variables privately; the source account must have SELECT only.
 */
$migrationSource = [];
foreach (['database', 'username', 'password', 'host', 'port'] as $migrationField) {
  $migrationValue = getenv('XINSHI_MIGRATION_SOURCE_' . strtoupper($migrationField));
  if ($migrationValue === FALSE || ($migrationValue === '' && $migrationField !== 'password')) {
    throw new RuntimeException('Missing private migration source connection setting.');
  }
  $migrationSource[$migrationField] = $migrationValue;
}
$databases['migrate']['default'] = $migrationSource + [
  'driver' => 'mysql',
  'namespace' => 'Drupal\\mysql\\Driver\\Database\\mysql',
  'autoload' => 'core/modules/mysql/src/',
  'prefix' => '',
];
unset($migrationSource, $migrationField, $migrationValue);
