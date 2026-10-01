<?php

/**
 * @file
 * Bootstraps migration command tests without a site database.
 */

$root = dirname(__DIR__, 5);
$loader = require $root . '/vendor/autoload.php';
$loader->addPsr4('Drupal\\Core\\', $root . '/docroot/core/lib/Drupal/Core');
$loader->addPsr4('Drupal\\Component\\', $root . '/docroot/core/lib/Drupal/Component');
$loader->addPsr4('Drupal\\update_to_d11\\', dirname(__DIR__) . '/src', TRUE);
if (!class_exists('Drupal')) {
  require $root . '/docroot/core/lib/Drupal.php';
}
