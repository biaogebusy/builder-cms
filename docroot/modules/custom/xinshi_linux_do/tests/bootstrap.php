<?php

/**
 * @file
 * Bootstrap isolated OAuth tests without a site or external provider.
 */

$root = dirname(__DIR__, 5);
$loader = require $root . '/vendor/autoload.php';
$loader->addPsr4('Drupal\\Core\\', $root . '/docroot/core/lib/Drupal/Core');
$loader->addPsr4('Drupal\\Component\\', $root . '/docroot/core/lib/Drupal/Component');
foreach (['user', 'file'] as $module) {
  $loader->addPsr4('Drupal\\' . $module . '\\', $root . '/docroot/core/modules/' . $module . '/src');
}
$loader->addPsr4('Drupal\\xinshi_linux_do\\', dirname(__DIR__) . '/src', TRUE);
if (!class_exists('Drupal')) {
  require $root . '/docroot/core/lib/Drupal.php';
}

