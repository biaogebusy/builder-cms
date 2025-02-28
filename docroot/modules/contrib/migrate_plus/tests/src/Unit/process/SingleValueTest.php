<?php

namespace Drupal\Tests\migrate_plus\Unit\process;

use Drupal\migrate_plus\Plugin\migrate\process\SingleValue;
use Drupal\Tests\migrate\Unit\process\MigrateProcessTestCase;

/**
 * @coversDefaultClass \Drupal\migrate_plus\Plugin\migrate\process\SingleValue
 * @group migrate
 */
class SingleValueTest extends MigrateProcessTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $this->plugin = new SingleValue([], 'single_value', []);
    parent::setUp();
  }

  /**
   * Test input treated as single value output.
   */
  public function testTreatAsSingle(): void {
    $value = ['v1', 'v2', 'v3'];
    $output = $this->plugin->transform($value, $this->migrateExecutable, $this->row, 'destinationproperty');
    $this->assertSame($output, $value);
    $this->assertFalse($this->plugin->multiple());
  }

}
