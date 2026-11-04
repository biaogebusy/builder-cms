<?php

declare(strict_types=1);

/** @file Runs one competing request against the disposable MySQL fixture. */

if (getenv('XINSHI_USAGE_ISOLATED_MYSQL') !== '1' || PHP_SAPI !== 'cli') {
  throw new RuntimeException('The isolated MySQL fixture is required.');
}
require __DIR__ . '/bootstrap.php';

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\KeyValueStore\KeyValueMemoryFactory;
use Drupal\Core\Lock\DatabaseLockBackend;
use Drupal\Core\PrivateKey;
use Drupal\Core\Session\UserSession;
use Drupal\Core\Site\Settings;
use Drupal\xinshi_ai_usage\Service\CostBackfillService;
use Drupal\xinshi_ai_usage\Service\CostRatingService;
use Drupal\xinshi_ai_usage\Service\PriceBookException;
use Drupal\xinshi_ai_usage\Service\PriceBookService;
use Drupal\xinshi_ai_usage\Service\ProducerVault;
use Drupal\xinshi_ai_usage\Service\UsageProjectionService;
use Psr\Log\NullLogger;

Database::addConnectionInfo('worker', 'default', ['driver' => 'mysql',
  'database' => 'usage_backfill_test', 'username' => 'usage_test', 'password' => 'isolated-test',
  'host' => '127.0.0.1', 'port' => '3306', 'namespace' => 'Drupal\\mysql\\Driver\\Database\\mysql']);
$database = Database::getConnection('default', 'worker');
$time = new class((int) $argv[4]) implements TimeInterface {
  public function __construct(private int $now) {}
  public function getCurrentMicroTime() { return $this->now / 1000; }
  public function getRequestMicroTime() { return $this->getCurrentMicroTime(); }
  public function getCurrentTime() { return intdiv($this->now, 1000); }
  public function getRequestTime() { return $this->getCurrentTime(); }
};
$books = new PriceBookService($database, $time);
$rating = new CostRatingService($database, $books, $time);
$lock = new DatabaseLockBackend($database);
$projection = new UsageProjectionService($database, $rating, $lock, $time, new NullLogger());
$account = new class(['uid' => '42']) extends UserSession {
  public function hasPermission($permission) { return TRUE; }
};
$settings = new Settings(['hash_salt' => 'synthetic', 'xinshi_ai_usage.site_id' => 'site-a']);
$key = new class extends PrivateKey {
  public function __construct() {}
  public function get() { return 'synthetic-site-key'; }
};
$backfill = new CostBackfillService($database, $books, $rating, $projection,
  $time, $account, $settings, new ProducerVault(new KeyValueMemoryFactory(), $key), $lock);
$deadline = microtime(TRUE) + 15;
while (!is_file($argv[3])) {
  if (microtime(TRUE) > $deadline) {
    throw new RuntimeException('The worker start gate timed out.');
  }
  usleep(1000);
}
try {
  if ($argv[1] === 'activate') {
    $books->activate((int) $argv[2], 'site-a', PriceBookService::KIND_SUPPLIER_CHAT, '42');
    $status = 'published';
  }
  else {
    $result = $backfill->execute(json_decode(file_get_contents($argv[2]), TRUE, 64, JSON_THROW_ON_ERROR), 'verified');
    $status = $result['replayed'] ? 'replayed' : 'written';
  }
}
catch (PriceBookException $e) {
  $status = $e->priceCode;
}
print json_encode(['status' => $status], JSON_THROW_ON_ERROR);
