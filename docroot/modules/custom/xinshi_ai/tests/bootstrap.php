<?php

/**
 * @file
 * Bootstrap focused tests without a site database or Drupal core-dev dependencies.
 */

$root = dirname(__DIR__, 5);
$loader = require $root . '/vendor/autoload.php';
$loader->addPsr4('Drupal\\Core\\', $root . '/docroot/core/lib/Drupal/Core');
$loader->addPsr4('Drupal\\Component\\', $root . '/docroot/core/lib/Drupal/Component');
$loader->addPsr4('Drupal\\user\\', $root . '/docroot/core/modules/user/src');
$loader->addPsr4('Drupal\\xinshi_ai\\', dirname(__DIR__) . '/src', TRUE);
if (!class_exists('Drupal')) {
  require $root . '/docroot/core/lib/Drupal.php';
}
require_once $root . '/docroot/core/includes/bootstrap.inc';
