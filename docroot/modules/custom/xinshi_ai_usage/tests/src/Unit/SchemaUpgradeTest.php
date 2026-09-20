<?php

declare(strict_types=1);

namespace Drupal\Tests\xinshi_ai_usage\Unit;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Database\IntegrityConstraintViolationException;
use Drupal\Core\Utility\UpdateException;
use Drupal\xinshi_ai_usage\Service\CostRatingService;
use Drupal\xinshi_ai_usage\Service\PriceBookService;
use Drupal\xinshi_ai_usage\Service\UsageIngestService;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the upgrade path from the UB1.4 schema (event table only).
 *
 * Runs the same code as xinshi_ai_usage_update_10001() against memory SQLite
 * so an already installed site gets the price book and cost tables plus the
 * attempt-level unique key, and a second run is a no-op.
 */
final class SchemaUpgradeTest extends TestCase {

  private Connection $database;

  protected function setUp(): void {
    parent::setUp();
    Database::addConnectionInfo('upgrade_test', 'default', [
      'driver' => 'sqlite', 'database' => ':memory:',
      'namespace' => 'Drupal\\sqlite\\Driver\\Database\\sqlite',
    ]);
    $this->database = Database::getConnection('default', 'upgrade_test');
    // The UB1.4 deployment: only the event table, without the attempt key.
    $legacy = xinshi_ai_usage_schema()[UsageIngestService::TABLE];
    unset($legacy['unique keys']['attempt_revision']);
    $this->database->schema()->createTable(UsageIngestService::TABLE, $legacy);
  }

  protected function tearDown(): void {
    Database::removeConnection('upgrade_test');
    parent::tearDown();
  }

  public function testUpgradeAddsTablesAndAttemptKeyIdempotently(): void {
    $schema = $this->database->schema();
    $this->insertEvent('att-1:observed:1', 'att-1', 1);
    $this->insertEvent('att-1:observed:2', 'att-1', 2);

    xinshi_ai_usage_apply_schema_updates($this->database);

    $this->assertTrue($schema->tableExists(PriceBookService::TABLE));
    $this->assertTrue($schema->tableExists(CostRatingService::TABLE));
    $this->assertTrue($schema->indexExists(UsageIngestService::TABLE, 'attempt_revision'));
    // Existing facts survive the upgrade.
    $this->assertSame(2, (int) $this->database->select(UsageIngestService::TABLE)
      ->countQuery()->execute()->fetchField());
    // The key is enforced from now on.
    try {
      $this->insertEvent('att-1:observed:1-rekeyed', 'att-1', 1);
      $this->fail('expected the attempt_revision unique key to reject the row');
    }
    catch (IntegrityConstraintViolationException) {
      $this->addToAssertionCount(1);
    }

    // Running the update again must not fail or duplicate anything.
    xinshi_ai_usage_apply_schema_updates($this->database);
    $this->assertTrue($schema->indexExists(UsageIngestService::TABLE, 'attempt_revision'));
  }

  public function testUpgradeRefusesWhenDuplicateFactsExist(): void {
    $this->insertEvent('att-1:observed:1', 'att-1', 1);
    $this->insertEvent('att-1:observed:1-again', 'att-1', 1);

    $this->expectException(UpdateException::class);
    $this->expectExceptionMessageMatches('/att-1/');
    xinshi_ai_usage_apply_schema_updates($this->database);
  }

  private function insertEvent(string $eventId, string $attemptId, int $revision): void {
    $this->database->insert(UsageIngestService::TABLE)->fields([
      'site_id' => 'site-a',
      'producer_id' => 'chat-node',
      'event_id' => $eventId,
      'event_type' => 'attempt.observed',
      'schema_version' => 1,
      'operation_id' => 'op-1',
      'attempt_id' => $attemptId,
      'observation_revision' => $revision,
      'payload_hash' => str_repeat('a', 64),
      'occurred_at' => 1_700_000_000_000,
      'received_at' => 1_700_000_000_250,
      'payload_json' => '{}',
    ])->execute();
  }

}
