<?php

namespace Drupal\Tests\eck\Kernel\Migrate\d7;

/**
 * Tests migration of ECK entity types.
 *
 * @group eck
 */
class MigrateEckTypeTest extends MigrateEckTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['eck', 'node'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->executeMigrations(['d7_eck_type']);
  }

  /**
   * Tests migrating Eck entity types.
   */
  public function testEckEntityType() {
    $type = [
      'id' => 'simple_entity',
      'label' => 'Simple entity',
      'langcode' => 'en',
    ];
    $this->assertEckEntityType($type);
    $type = [
      'id' => 'complex_entity',
      'label' => 'Complex entity',
      'langcode' => 'en',
    ];
    $this->assertEckEntityType($type);
  }

}
