<?php

namespace Drupal\Tests\eck\Kernel\Migrate\d7;

/**
 * Test EckDeriver.
 *
 * @group eck
 */
class MigrateEckDeriverTest extends MigrateEckTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['eck', 'node'];

  /**
   * Tests the eck dervier.
   */
  public function testDeriver() {
    $migration_ids = [
      'd7_eck_entity:complex_entity:complex_entity',
      'd7_eck_entity:complex_entity:another_bundle',
      'd7_eck_translation:simple_entity:simple_entity',
    ];
    foreach ($migration_ids as $migration_id) {
      $migration = $this->getMigration($migration_id);
      $this->assertFALSE($migration);
    }

    $process = $this->getMigration('d7_eck:simple_entity:simple_entity')->getProcess();
    $this->assertSame('field_text', $process['field_text'][0]['source']);
    $this->assertCount(4, $process);

    $process = $this->getMigration('d7_eck_translation:complex_entity:complex_entity')->getProcess();
    $this->assertSame('field_text', $process['field_text'][0]['source']);
    $this->assertSame('field_simple_entities', $process['field_simple_entities'][0]['source']);
    $this->assertSame('field_node', $process['field_node'][0]['source']);
    $this->assertSame('field_complex_entity', $process['field_complex_entity'][0]['source']);
    $this->assertSame('field_file', $process['field_file'][0]['source']);
    $this->assertCount(8, $process);
  }

}
