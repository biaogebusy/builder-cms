<?php

/**
 * @file
 * Bootstrap isolated authentication tests without a site or SMS gateway.
 */

$root = dirname(__DIR__, 5);
$loader = require $root . '/vendor/autoload.php';
$loader->addPsr4('Drupal\\Core\\', $root . '/docroot/core/lib/Drupal/Core');
$loader->addPsr4('Drupal\\Component\\', $root . '/docroot/core/lib/Drupal/Component');
foreach (['user', 'rest', 'file'] as $module) {
  $loader->addPsr4('Drupal\\' . $module . '\\', $root . '/docroot/core/modules/' . $module . '/src');
}
$loader->addPsr4('Drupal\\sms\\', $root . '/docroot/modules/contrib/smsframework/src');
$loader->addPsr4('Drupal\\otp_login\\', $root . '/docroot/modules/contrib/otp_login/src');
$loader->addPsr4('Drupal\\xinshi_sms\\', dirname(__DIR__) . '/src', TRUE);
if (!class_exists('Drupal')) {
  require $root . '/docroot/core/lib/Drupal.php';
}

