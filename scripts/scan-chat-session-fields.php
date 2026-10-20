<?php

declare(strict_types=1);

/**
 * @file
 * Read-only field preflight, invoked by Drush php:script on an explicitly selected site.
 */

use Drupal\xinshi_ai\Service\ChatSessionFieldScan;
use Consolidation\AnnotatedCommand\CommandResult;

if (!class_exists('Drupal') || !\Drupal::hasContainer()) {
  fwrite(STDERR, "Use Drush php:script on the selected site.\n");
  exit(2);
}
$service = dirname(__DIR__) . '/docroot/modules/custom/xinshi_ai/src/Service/';
require_once $service . 'ChatSessionFields.php';
require_once $service . 'ChatSessionFieldScan.php';
try {
  $scan_args = $extra ?? [];
  if ($scan_args !== [] && $scan_args !== ['plan']) {
    throw new \InvalidArgumentException('unsupported_scan_arguments');
  }
  $report = (new ChatSessionFieldScan(\Drupal::service('entity_field.manager'),
    \Drupal::entityTypeManager(), \Drupal::service('jsonapi.resource_type.repository')))
    ->scan($scan_args === ['plan'] ? 3 : 2);
  echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
  return CommandResult::exitCode($report['matches'] ? 0 : 1);
}
catch (Throwable) {
  fwrite(STDERR, "Field scan unavailable; check the selected site and JSON:API configuration.\n");
  return CommandResult::exitCode(2);
}
