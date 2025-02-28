<?php

namespace Drupal\Tests\conflict\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the Conflict schema.
 *
 * @group conflict
 */
class SchemaTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['conflict', 'node'];

  /**
   * Tests that schema is present.
   */
  public function testSchema() {
    $this->config('conflict.settings')
      ->set('resolution_type.default.default', 'dialog')
      ->set('resolution_type.node.default', 'dialog')
      ->set('resolution_type.node.article', 'dialog')
      ->save();
    // This is necessary to pass the test, even though no assertions are
    // necessary to check for presence of schema.
    $this->assertTrue(TRUE);
  }

}
