<?php

/**
 * @file
 * Uses runtime source copies only; never boots an existing Drupal site.
 */

require dirname(__DIR__, 2) . '/xinshi_ai/tests/bootstrap.php';
$loader->addPsr4('Drupal\\xinshi_shooting_plan\\', dirname(__DIR__) . '/src');
require_once dirname(__DIR__) . '/xinshi_shooting_plan.install';
require_once dirname(__DIR__) . '/xinshi_shooting_plan.module';
