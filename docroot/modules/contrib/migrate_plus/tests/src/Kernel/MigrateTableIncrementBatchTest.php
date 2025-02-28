<?php

namespace Drupal\Tests\migrate_plus\Kernel;

/**
 * Verifies all tests pass with batching enabled, uneven batches.
 *
 * @group migrate
 */
class MigrateTableIncrementBatchTest extends MigrateTableIncrementTest {

  /**
   * The batch size to configure.
   *
   * @var int
   */
  protected $batchSize = 2;

}
