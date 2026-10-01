<?php

/**
 * @file
 * Bootstrap focused tests without a site database or Drupal core-dev dependencies.
 */

$root = dirname(__DIR__, 5);
$loader = require $root . '/vendor/autoload.php';
$loader->addPsr4('Drupal\\Core\\', $root . '/docroot/core/lib/Drupal/Core');
$loader->addPsr4('Drupal\\Component\\', $root . '/docroot/core/lib/Drupal/Component');
foreach (['rest', 'views'] as $module) {
  $loader->addPsr4('Drupal\\' . $module . '\\', $root . '/docroot/core/modules/' . $module . '/src');
}
$loader->addPsr4('Drupal\\xinshi_views\\', dirname(__DIR__) . '/src', TRUE);
if (!class_exists('Drupal')) {
  require $root . '/docroot/core/lib/Drupal.php';
}
