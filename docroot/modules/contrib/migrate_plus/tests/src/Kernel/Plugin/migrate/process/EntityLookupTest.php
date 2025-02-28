<?php

namespace Drupal\Tests\migrate_plus\Kernel\Plugin\migrate\process;

use Drupal\KernelTests\KernelTestBase;
use Drupal\migrate\Row;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests the entity_lookup plugin.
 *
 * @coversDefaultClass \Drupal\migrate_plus\Plugin\migrate\process\EntityLookup
 * @group migrate_plus
 */
class EntityLookupTest extends KernelTestBase {

  use UserCreationTrait;
  use NodeCreationTrait;

  /**
   * The migrate executable mock object.
   *
   * @var \Drupal\migrate\MigrateExecutable|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $migrateExecutable;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'migrate_plus',
    'migrate',
    'user',
    'system',
    'node',
    'filter',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('system', ['sequences']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installConfig(['filter']);

    $this->migrateExecutable = $this->getMockBuilder('Drupal\migrate\MigrateExecutable')
      ->disableOriginalConstructor()
      ->getMock();

    $test_nodes = [
      ['title' => 'foo 1'],
      ['title' => 'foo 2'],
      ['title' => 'bar 1'],
    ];

    foreach ($test_nodes as $test_node) {
      $this->createNode($test_node);
    }
  }

  /**
   * Lookup an entity without bundles on destination key.
   *
   * Using user entity as destination entity without bundles as example for
   * testing.
   *
   * @covers ::transform
   */
  public function testLookupEntityWithoutBundles(): void {
    $migration = \Drupal::service('plugin.manager.migration')
      ->createStubMigration([
        'id' => 'test',
        'source' => [],
        'process' => [],
        'destination' => [
          'plugin' => 'entity:user',
        ],
      ]);

    // Create a user.
    $known_user = $this->createUser([], 'lucuma');

    $configuration = [
      'entity_type' => 'user',
      'value_key' => 'name',
    ];
    $plugin = \Drupal::service('plugin.manager.migrate.process')
      ->createInstance('entity_lookup', $configuration, $migration);
    $row = new Row();

    // Check the known user is found.
    $value = $plugin->transform('lucuma', $this->migrateExecutable, $row, 'name');
    $this->assertSame($known_user->id(), $value);

    // Check an unknown user is not found.
    $value = $plugin->transform('orange', $this->migrateExecutable, $row, 'name');
    $this->assertNull($value);
  }

  /**
   * Tests a lookup of config entity.
   */
  public function testConfigEntityLookup(): void {
    $migration = \Drupal::service('plugin.manager.migration')
      ->createStubMigration([
        'id' => 'test',
        'source' => [],
        'process' => [],
        'destination' => [
          'plugin' => 'entity:node',
        ],
      ]);

    $configuration = [
      'entity_type' => 'filter_format',
      'value_key' => 'name',
    ];

    $plugin = \Drupal::service('plugin.manager.migrate.process')
      ->createInstance('entity_lookup', $configuration, $migration);
    $value = $plugin->transform('Plain text', $this->migrateExecutable, new Row(), 'destination_property');
    $this->assertEquals('plain_text', $value);
  }

  /**
   * Tests lookup with different operators.
   *
   * @covers ::transform
   * @dataProvider providerTestLookupOperators
   */
  public function testLookupOperators($configuration, $lookup_value, $expected_value): void {
    $migration = \Drupal::service('plugin.manager.migration')
      ->createStubMigration([
        'id' => 'test',
        'source' => [],
        'process' => [],
        'destination' => [
          'plugin' => 'entity:node',
        ],
      ]);

    $plugin = \Drupal::service('plugin.manager.migrate.process')
      ->createInstance('entity_lookup', $configuration, $migration);
    $value = $plugin->transform($lookup_value, $this->migrateExecutable, new Row(), 'destination_property');
    $this->assertEquals($expected_value, $value);
  }

  /**
   * Data provider for testLookupOperators test.
   *
   * @return array[]
   *   The test cases.
   */
  public function providerTestLookupOperators(): array {
    return [
      'Default operator' => [
        [
          'entity_type' => 'node',
          'value_key' => 'title',
        ],
        'foo 1',
        '1',
      ],
      'Multiple values' => [
        [
          'entity_type' => 'node',
          'value_key' => 'title',
        ],
        ['foo 1', 'foo 2'],
        ['2', '1'],
      ],
      'Starts with' => [
        [
          'entity_type' => 'node',
          'value_key' => 'title',
          'operator' => 'STARTS_WITH',
        ],
        'bar',
        '3',
      ],
    ];
  }

  /**
   * Tests a lookup with bundle conditions.
   */
  public function testEntityLookupWithBundles(): void {
    $migration = \Drupal::service('plugin.manager.migration')
      ->createStubMigration([
        'id' => 'test',
        'source' => [],
        'process' => [],
        'destination' => [
          'plugin' => 'entity:node',
        ],
      ]);

    // Create a node of article content type.
    $this->createNode([
      'title' => 'article 1',
      'type' => 'article',
    ]);

    $configuration = [
      'entity_type' => 'node',
      'value_key' => 'title',
      'bundle_key' => 'type',
      'bundle' => 'page',
    ];

    // The search is performed by one bundle - node is not found.
    $plugin = \Drupal::service('plugin.manager.migrate.process')
      ->createInstance('entity_lookup', $configuration, $migration);
    $value = $plugin->transform('article 1', $this->migrateExecutable, new Row(), 'destination_property');
    $this->assertEquals(NULL, $value);

    // Now include both bundles in the search - node is found.
    $configuration['bundle'] = ['page', 'article'];
    $plugin = \Drupal::service('plugin.manager.migrate.process')
      ->createInstance('entity_lookup', $configuration, $migration);
    $value = $plugin->transform('article 1', $this->migrateExecutable, new Row(), 'destination_property');
    $this->assertEquals('4', $value);
  }

}
