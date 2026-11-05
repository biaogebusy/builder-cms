<?php

declare(strict_types=1);

/**
 * @file
 * Previews retained landing-page layouts. Run through Drush php:script.
 */

require_once __DIR__ . '/layout-recovery/Recovery.php';

$options = [];
foreach ($extra ?? [] as $argument) {
  [$name, $value] = array_pad(explode('=', $argument, 2), 2, NULL);
  if (!in_array($name, ['--nid', '--apply', '--site-uuid', '--confirm', '--backup'], TRUE) ||
    array_key_exists($name, $options) || ($name === '--apply' ? $value !== NULL : !$value)) {
    throw new RuntimeException('Invalid recovery argument: ' . $name);
  }
  $options[$name] = $value;
}
$nid = isset($options['--nid']) ? filter_var($options['--nid'], FILTER_VALIDATE_INT,
  ['options' => ['min_range' => 1]]) : NULL;
if ($nid === FALSE) { throw new RuntimeException('Node ID must be positive.'); }
$recovery = new PanelizerLayoutRecovery(\Drupal::database(), $nid);
if (array_key_exists('--apply', $options)) {
  $report = $recovery->apply($options['--site-uuid'] ?? '', $options['--confirm'] ?? '',
    $options['--backup'] ?? '');
}
else {
  if (count(array_diff(array_keys($options), ['--nid']))) {
    throw new RuntimeException('Write arguments require --apply.');
  }
  $report = $recovery->preview();
}
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
if ($report['blockers']) { throw new RuntimeException('Recovery is blocked; no layouts were written.'); }
