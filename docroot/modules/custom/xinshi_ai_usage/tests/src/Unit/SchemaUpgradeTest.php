<?php

declare(strict_types=1);

namespace Drupal\Tests\xinshi_ai_usage\Unit;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Database\IntegrityConstraintViolationException;
use Drupal\Core\Utility\UpdateException;
use Drupal\xinshi_ai_usage\Service\CostRatingService;
use Drupal\xinshi_ai_usage\Service\LocalUsageProducer;
use Drupal\xinshi_ai_usage\Service\PriceBookService;
use Drupal\xinshi_ai_usage\Service\UsageIngestService;
use Drupal\xinshi_ai_usage\Service\UsageProjectionService;
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
    // The UB1.4 deployment: only the event table, without the attempt key and
    // without the UB1.6 consumer bookkeeping columns.
    $legacy = xinshi_ai_usage_schema()[UsageIngestService::TABLE];
    unset($legacy['unique keys']['attempt_revision']);
    unset($legacy['fields']['failure_count'], $legacy['fields']['last_error'], $legacy['fields']['quarantined_at']);
    $legacy['indexes']['pending'] = ['processed_at', 'id'];
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
    $this->assertTrue($schema->tableExists(UsageProjectionService::ATTEMPT_TABLE));
    $this->assertTrue($schema->tableExists(UsageProjectionService::ROLLUP_TABLE));
    $this->assertTrue($schema->tableExists(UsageProjectionService::WATERMARK_TABLE));
    $this->assertTrue($schema->indexExists(UsageIngestService::TABLE, 'attempt_revision'));
    foreach (['failure_count', 'last_error', 'quarantined_at'] as $field) {
      $this->assertTrue($schema->fieldExists(UsageIngestService::TABLE, $field), $field);
    }
    $this->assertTrue($schema->indexExists(UsageIngestService::TABLE, 'pending'));
    // Existing rows get the bookkeeping defaults.
    $this->assertSame(0, (int) $this->database->select(UsageIngestService::TABLE, 'e')
      ->fields('e', ['failure_count'])->range(0, 1)->execute()->fetchField());
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

  public function testUpgradeAddsBillingRoleAndResetsTheRollupForRecomputation(): void {
    $schema = $this->database->schema();
    $definitions = xinshi_ai_usage_schema();
    // The UB1.6 deployment: projection and rollup without billing_role.
    $attempt = $definitions[UsageProjectionService::ATTEMPT_TABLE];
    unset($attempt['fields']['billing_role']);
    $attempt['indexes']['rollup_cell'] = array_values(array_diff($attempt['indexes']['rollup_cell'], ['billing_role']));
    $rollup = $definitions[UsageProjectionService::ROLLUP_TABLE];
    unset($rollup['fields']['billing_role']);
    $rollup['unique keys']['cell'] = array_values(array_diff($rollup['unique keys']['cell'], ['billing_role']));
    $schema->createTable(UsageProjectionService::ATTEMPT_TABLE, $attempt);
    $schema->createTable(UsageProjectionService::ROLLUP_TABLE, $rollup);
    $schema->createTable(UsageProjectionService::WATERMARK_TABLE, $definitions[UsageProjectionService::WATERMARK_TABLE]);
    $this->insertEvent('att-1:observed:1', 'att-1', 1);
    $this->database->update(UsageIngestService::TABLE)->fields(['processed_at' => 5])->execute();
    $this->database->insert(UsageProjectionService::ROLLUP_TABLE)->fields([
      'site_id' => 'site-a', 'bucket_start' => 0, 'feature' => 'common', 'stage' => 'common',
      'payer' => 'platform', 'provider_account_ref' => 'xinshi', 'model_id' => 'model-a',
      'actor_user_id' => '-', 'currency' => '-', 'attempt_count' => 1, 'projection_version' => 1,
      'data_as_of' => 1, 'updated_at' => 1,
    ])->execute();
    $this->database->insert(UsageProjectionService::WATERMARK_TABLE)->fields([
      'site_id' => 'site-a', 'projection' => 'attempt', 'projection_version' => 1,
      'last_event_id' => 1, 'processed_count' => 1, 'data_as_of' => 1, 'updated_at' => 1,
    ])->execute();

    xinshi_ai_usage_apply_schema_updates($this->database);

    $this->assertTrue($schema->fieldExists(UsageProjectionService::ATTEMPT_TABLE, 'billing_role'));
    $this->assertTrue($schema->fieldExists(UsageProjectionService::ROLLUP_TABLE, 'billing_role'));
    // The rollup is a cache: it is emptied and the events are queued again so the
    // consumer recomputes every cell with the new dimension.
    $this->assertSame(0, (int) $this->database->select(UsageProjectionService::ROLLUP_TABLE)
      ->countQuery()->execute()->fetchField());
    $this->assertSame(0, (int) $this->database->select(UsageProjectionService::WATERMARK_TABLE)
      ->countQuery()->execute()->fetchField());
    $this->assertNull($this->database->select(UsageIngestService::TABLE, 'e')
      ->fields('e', ['processed_at'])->execute()->fetchField() ?: NULL);
    // The new unique key includes the role: two rows differing only by role coexist.
    foreach (['primary', 'repair'] as $role) {
      $this->database->insert(UsageProjectionService::ROLLUP_TABLE)->fields([
        'site_id' => 'site-a', 'bucket_start' => 0, 'feature' => 'common', 'stage' => 'common',
        'payer' => 'platform', 'billing_role' => $role, 'provider_account_ref' => 'xinshi',
        'model_id' => 'model-a', 'actor_user_id' => '-', 'currency' => '-', 'attempt_count' => 1,
        'projection_version' => 1, 'data_as_of' => 1, 'updated_at' => 1,
      ])->execute();
    }
    $this->assertSame(2, (int) $this->database->select(UsageProjectionService::ROLLUP_TABLE)
      ->countQuery()->execute()->fetchField());

    // Idempotent: a second run leaves the recomputed rows alone.
    xinshi_ai_usage_apply_schema_updates($this->database);
    $this->assertSame(2, (int) $this->database->select(UsageProjectionService::ROLLUP_TABLE)
      ->countQuery()->execute()->fetchField());
  }

  public function testUpgradeAddsImageCountsAndTheDeliveryTableAndResetsTheRollup(): void {
    $schema = $this->database->schema();
    $definitions = xinshi_ai_usage_schema();
    // The UB2.1 deployment: projection and rollup with billing_role but without
    // images_generated, and no delivery table yet.
    $attempt = $definitions[UsageProjectionService::ATTEMPT_TABLE];
    unset($attempt['fields']['images_generated']);
    $rollup = $definitions[UsageProjectionService::ROLLUP_TABLE];
    unset($rollup['fields']['images_generated']);
    $schema->createTable(UsageProjectionService::ATTEMPT_TABLE, $attempt);
    $schema->createTable(UsageProjectionService::ROLLUP_TABLE, $rollup);
    $schema->createTable(UsageProjectionService::WATERMARK_TABLE, $definitions[UsageProjectionService::WATERMARK_TABLE]);
    $this->insertEvent('att-1:observed:1', 'att-1', 1);
    $this->database->update(UsageIngestService::TABLE)->fields(['processed_at' => 5])->execute();
    $this->database->insert(UsageProjectionService::ROLLUP_TABLE)->fields([
      'site_id' => 'site-a', 'bucket_start' => 0, 'feature' => 'common', 'stage' => 'common',
      'payer' => 'platform', 'billing_role' => 'primary', 'provider_account_ref' => 'xinshi',
      'model_id' => 'model-a', 'actor_user_id' => '-', 'currency' => '-', 'attempt_count' => 1,
      'projection_version' => 1, 'data_as_of' => 1, 'updated_at' => 1,
    ])->execute();
    $this->database->insert(UsageProjectionService::WATERMARK_TABLE)->fields([
      'site_id' => 'site-a', 'projection' => 'attempt', 'projection_version' => 1,
      'last_event_id' => 1, 'processed_count' => 1, 'data_as_of' => 1, 'updated_at' => 1,
    ])->execute();

    xinshi_ai_usage_apply_schema_updates($this->database);

    $this->assertTrue($schema->tableExists(LocalUsageProducer::DELIVERY_TABLE));
    $this->assertTrue($schema->fieldExists(UsageProjectionService::ATTEMPT_TABLE, 'images_generated'));
    $this->assertTrue($schema->fieldExists(UsageProjectionService::ROLLUP_TABLE, 'images_generated'));
    // The rollup is a cache: emptied and the events queued again so every cell
    // is recomputed with the image column.
    $this->assertSame(0, (int) $this->database->select(UsageProjectionService::ROLLUP_TABLE)
      ->countQuery()->execute()->fetchField());
    $this->assertSame(0, (int) $this->database->select(UsageProjectionService::WATERMARK_TABLE)
      ->countQuery()->execute()->fetchField());
    $this->assertNull($this->database->select(UsageIngestService::TABLE, 'e')
      ->fields('e', ['processed_at'])->execute()->fetchField() ?: NULL);

    // One row per artifact of an attempt: a replayed save is rejected by the key.
    $delivery = ['site_id' => 'site-a', 'operation_id' => 'job-1', 'attempt_id' => 'att-1', 'output_index' => 0,
      'artifact_kind' => 'image_asset', 'artifact_ref' => 'asset-0', 'state' => 'committed', 'delivered_at' => 1];
    $this->database->insert(LocalUsageProducer::DELIVERY_TABLE)->fields($delivery)->execute();
    try {
      $this->database->insert(LocalUsageProducer::DELIVERY_TABLE)->fields($delivery)->execute();
      $this->fail('expected the artifact unique key to reject the row');
    }
    catch (IntegrityConstraintViolationException) {
      $this->addToAssertionCount(1);
    }

    // Idempotent: a second run keeps the delivery fact and adds nothing.
    xinshi_ai_usage_apply_schema_updates($this->database);
    $this->assertSame(1, (int) $this->database->select(LocalUsageProducer::DELIVERY_TABLE)
      ->countQuery()->execute()->fetchField());
    $this->assertTrue($schema->fieldExists(UsageProjectionService::ROLLUP_TABLE, 'images_generated'));
  }

  public function testUpgradeAddsTheImageCostColumnToExistingEntries(): void {
    $schema = $this->database->schema();
    $definitions = xinshi_ai_usage_schema();
    // The UB2.7 deployment: cost entries without the per-image component.
    $entries = $definitions[CostRatingService::TABLE];
    unset($entries['fields']['image_cost_micros']);
    $schema->createTable(CostRatingService::TABLE, $entries);
    $this->database->insert(CostRatingService::TABLE)->fields([
      'site_id' => 'site-a', 'attempt_id' => 'att-1', 'observation_revision' => 1, 'total_cost_micros' => 7,
      'valuation_state' => 'rated_estimate', 'source_kind' => 'price_book', 'computed_at' => 1,
    ])->execute();

    xinshi_ai_usage_apply_schema_updates($this->database);

    $this->assertTrue($schema->fieldExists(CostRatingService::TABLE, 'image_cost_micros'));
    // Existing entries are immutable: the new column is NULL, the total untouched.
    $row = $this->database->select(CostRatingService::TABLE, 'c')->fields('c')->execute()->fetchAssoc();
    $this->assertNull($row['image_cost_micros']);
    $this->assertSame(7, (int) $row['total_cost_micros']);
    xinshi_ai_usage_apply_schema_updates($this->database);
    $this->assertSame(1, (int) $this->database->select(CostRatingService::TABLE)->countQuery()->execute()->fetchField());
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
