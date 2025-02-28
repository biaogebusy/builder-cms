<?php

namespace Drupal\Tests\migrate_plus\Kernel;

/**
 * Verifies all tests pass with batching enabled, even batches.
 *
 * @group migrate
 */
class MigrateTableIncrementEvenBatchTest extends MigrateTableIncrementTest {

  /**
   * The batch size to configure.
   *
   * @var int
   */
  protected $batchSize = 3;

}
