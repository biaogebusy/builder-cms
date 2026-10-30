<?php

declare(strict_types=1);

use Drupal\Core\DrupalKernel;
use Drupal\xinshi_credits\CreditException;
use Symfony\Component\HttpFoundation\Request;

$root = dirname(__DIR__, 5);
$fixture = '/tmp/credits-test';
if (getenv('XINSHI_CREDITS_ISOLATED_TEST') !== '1' || !is_file('/.dockerenv')
  || $root !== '/app' || !is_file($fixture . '/installed')) throw new RuntimeException('Use the isolated runner.');
$loader = require $root . '/vendor/autoload.php';
$loader->addPsr4('Drupal\\Core\\', $root . '/docroot/core/lib/Drupal/Core');
$loader->addPsr4('Drupal\\Component\\', $root . '/docroot/core/lib/Drupal/Component');
require_once $root . '/docroot/core/includes/bootstrap.inc';
chdir($root . '/docroot');
$request = Request::create('http://127.0.0.1/');
$kernel = DrupalKernel::createFromRequest($request, $loader, 'prod', FALSE);
$kernel->boot(); $kernel->preHandle($request);
$db = \Drupal::database();
if (!in_array($db->getConnectionOptions()['database'], [$fixture . '/site.sqlite', 'credits_test'], TRUE)) throw new RuntimeException('Unexpected database.');
$checks = 0;
function check(bool $ok, string $message): void {
  if (!$ok) throw new RuntimeException($message);
  $GLOBALS['checks']++; echo 'PASS: ' . $message . PHP_EOL;
}
function denied(callable $run, string $reason): void {
  try { $run(); }
  catch (CreditException $error) { check($error->reason === $reason, 'Denied: ' . $reason . ' (got ' . $error->reason . ')'); return; }
  throw new RuntimeException('Expected: ' . $reason);
}
