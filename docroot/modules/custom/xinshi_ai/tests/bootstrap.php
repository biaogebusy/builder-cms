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
$loader->addPsr4('Drupal\\node\\', $root . '/docroot/core/modules/node/src');
$loader->addPsr4('Drupal\\xinshi_ai\\', dirname(__DIR__) . '/src', TRUE);
// Image metering (UB2.4) builds on the usage module's producer and contract.
$loader->addPsr4('Drupal\\xinshi_ai_usage\\', dirname(__DIR__, 2) . '/xinshi_ai_usage/src', TRUE);
if (!class_exists('Drupal')) {
  require $root . '/docroot/core/lib/Drupal.php';
}
require_once $root . '/docroot/core/includes/bootstrap.inc';
