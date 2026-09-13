<?php

/**
 * @file
 * Bootstrap focused tests without a site database or Drupal core-dev dependencies.
 */

$root = dirname(__DIR__, 5);
$loader = require $root . '/vendor/autoload.php';
$loader->addPsr4('Drupal\\Core\\', $root . '/docroot/core/lib/Drupal/Core');
$loader->addPsr4('Drupal\\Component\\', $root . '/docroot/core/lib/Drupal/Component');
foreach (['block_content', 'content_moderation', 'filter', 'layout_builder', 'node', 'sqlite', 'user'] as $module) {
  $loader->addPsr4('Drupal\\' . $module . '\\', $root . '/docroot/core/modules/' . $module . '/src');
}
$loader->addPsr4('Drupal\\xinshi_api\\', dirname(__DIR__) . '/src', TRUE);
if (!class_exists('Drupal')) {
  require $root . '/docroot/core/lib/Drupal.php';
}
require_once dirname(__DIR__) . '/xinshi_api.install';
