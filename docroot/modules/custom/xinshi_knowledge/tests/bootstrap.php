<?php

declare(strict_types=1);

/** Bootstrap module tests with the site's locked vendor dependencies. */
$root = dirname(__DIR__, 5);
$loader = require $root . '/vendor/autoload.php';
$loader->addPsr4('Drupal\\Core\\', $root . '/docroot/core/lib/Drupal/Core');
$loader->addPsr4('Drupal\\Component\\', $root . '/docroot/core/lib/Drupal/Component');
foreach (['node', 'media', 'file', 'user', 'field', 'language'] as $module) {
  $loader->addPsr4('Drupal\\' . $module . '\\', $root . '/docroot/core/modules/' . $module . '/src');
}
$loader->addPsr4('Drupal\\search_api\\', $root . '/docroot/modules/contrib/search_api/src');
$loader->addPsr4('Drupal\\xinshi_knowledge\\', dirname(__DIR__) . '/src', TRUE);
$loader->addPsr4('Drupal\\xinshi_ai\\', dirname(__DIR__, 2) . '/xinshi_ai/src', TRUE);
if (!class_exists('Drupal')) {
  require $root . '/docroot/core/lib/Drupal.php';
}
require_once $root . '/docroot/core/includes/bootstrap.inc';
