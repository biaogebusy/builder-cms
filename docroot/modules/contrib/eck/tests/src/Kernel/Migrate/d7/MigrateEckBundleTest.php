<?php

namespace Drupal\Tests\eck\Kernel\Migrate\d7;

/**
 * Tests migration of ECK bundles.
 *
 * @group eck
 */
class MigrateEckBundleTest extends MigrateEckTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['eck', 'node'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->executeMigrations([
      'd7_eck_type',
      'd7_eck_bundle',
    ]);
  }

  /**
   * Tests migrating Eck entity types.
   */
  public function testEckBundle() {
    $bundle = [
      'type' => 'simple_entity',
      'name' => 'Simple entity',
      'description' => 'Simple entity',
      'langcode' => 'en',
    ];
    $this->assertEckBundle($bundle);
    $bundle = [
      'type' => 'complex_entity',
      'name' => 'Complex entity',
      'description' => 'Complex entity',
      'langcode' => 'en',
    ];
    $this->assertEckBundle($bundle);
    $bundle = [
      'type' => 'another_bundle',
      'name' => 'Another bundle',
      'description' => 'Another bundle',
      'langcode' => 'en',
    ];
    $this->assertEckBundle($bundle);
  }

}
