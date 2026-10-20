<?php

declare(strict_types=1);

/**
 * @file
 * Explicit offline CMS migration; never included by a route, hook or runtime service.
 */

use Consolidation\AnnotatedCommand\CommandResult;
use Xinshi\ChatMigration\Archive;
use Xinshi\ChatMigration\Export;
use Xinshi\ChatMigration\Job;
use Xinshi\ChatMigration\Plan;
use Xinshi\ChatMigration\Source;

if (PHP_SAPI !== 'cli' || !class_exists('Drupal') || !\Drupal::hasContainer()) {
  fwrite(STDERR, "Use Drush php:script on the explicitly selected frozen site.\n");
  if (class_exists(CommandResult::class)) {
    return CommandResult::exitCode(2);
  }
  exit(2);
}
// Database snapshots and lock files must be private from their first filesystem write.
umask(0077);
$migration_services = dirname(__DIR__) . '/docroot/modules/custom/';
require_once $migration_services . 'xinshi_ai/src/Service/ChatSessionFields.php';
require_once $migration_services . 'xinshi_ai/src/Service/ChatSessionFieldScan.php';
require_once $migration_services . 'xinshi_ai_reference/src/ChatSessionFormatException.php';
require_once $migration_services . 'xinshi_ai_reference/src/ChatSessionFormat.php';
require_once $migration_services . 'xinshi_ai_reference/src/ChatSessionSnapshot.php';
foreach (['Archive', 'Source', 'Export', 'PlanContent', 'Plan', 'Job'] as $migration_class) {
  require_once __DIR__ . '/chat-session-migration/' . $migration_class . '.php';
}
try {
  $migration_args = $extra ?? [];
  $migration_target_version = ($migration_args[0] ?? '') === 'plan' ? 3 : 2;
  if ($migration_target_version === 3) {
    array_shift($migration_args);
  }
  $migration_operation = $migration_args[0] ?? '';
  if (($migration_operation === 'export' && count($migration_args) !== 4) ||
    (in_array($migration_operation, ['install', 'reconcile'], TRUE) && count($migration_args) !== 5) ||
    (in_array($migration_operation, ['apply', 'rollback', 'activate'], TRUE) && count($migration_args) !== 6) ||
    !in_array($migration_operation, ['export', 'install', 'reconcile', 'apply', 'rollback', 'activate'], TRUE)) {
    fwrite(STDERR, "Usage after -- (prepend plan for the independent v2-to-v3 batch):\n" .
      "  export SITE_UUID NEW_EXPORT_DIR writers-stopped\n" .
      "  install|reconcile SITE_UUID EXPORT_DIR PROPOSAL_DIR writers-stopped\n" .
      "  apply|rollback|activate SITE_UUID EXPORT_DIR PROPOSAL_DIR STATE_DIR writers-stopped\n");
    return CommandResult::exitCode(2);
  }
  $migration_db = \Drupal::database();
  $migration_source = new Source($migration_db, $migration_args[1], $migration_target_version);
  $migration_source->frozen($migration_args[array_key_last($migration_args)]);
  $migration_result = Job::exclusive($migration_db, $migration_args[1], static function () use (
    $migration_operation, $migration_args, $migration_db, $migration_source
  ): array {
    if ($migration_operation === 'export') {
      return (new Export($migration_source))->run($migration_args[2]);
    }
    $plan = new Plan($migration_source, $migration_args[2], $migration_args[3]);
    $job = new Job($migration_db, $migration_source, $plan);
    return match ($migration_operation) {
      'install' => $job->install(),
      'reconcile' => $job->reconcile(),
      default => $job->run($migration_operation, $migration_args[4]),
    };
  });
  echo Archive::json($migration_result) . PHP_EOL;
  return CommandResult::exitCode(0);
}
catch (Throwable $migration_error) {
  // Driver and parser messages can contain source content; print only our bounded reason codes.
  $migration_code = $migration_error instanceof RuntimeException &&
    preg_match('/^[a-z_]{1,80}$/D', $migration_error->getMessage())
      ? $migration_error->getMessage() : 'migration_failed';
  fwrite(STDERR, 'Chat migration stopped: ' . $migration_code . PHP_EOL);
  return CommandResult::exitCode(1);
}
