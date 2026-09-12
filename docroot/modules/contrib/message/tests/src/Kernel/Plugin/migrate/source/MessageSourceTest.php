<?php

declare(strict_types=1);

namespace Drupal\Tests\message\Kernel\Plugin\migrate\source;

use Drupal\message\Plugin\migrate\source\MessageSource;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\Tests\migrate\Kernel\MigrateSqlSourceTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Tests D7 message source plugin.
 */
#[CoversClass(MessageSource::class)]
#[Group('Message')]
#[RunTestsInSeparateProcesses]
class MessageSourceTest extends MigrateSqlSourceTestBase {

  use ProphecyTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['message', 'migrate_drupal'];

  /**
   * Tests fields and IDs metadata.
   */
  public function testFieldsAndIds(): void {
    $migration = $this->prophesize(MigrationInterface::class);
    $migration->id()->willReturn('d7_message');
    $migration->getIdMap()->willReturn(
      $this->prophesize(MigrateIdMapInterface::class)->reveal()
    );
    $migration->getDestinationIds()->willReturn([]);

    /** @var \Drupal\message\Plugin\migrate\source\MessageSource $plugin */
    $plugin = $this->container->get('plugin.manager.migrate.source')
      ->createInstance('d7_message_source', [], $migration->reveal());
    $migration->getSourcePlugin()->willReturn($plugin);

    $fields = $plugin->fields();
    $this->assertArrayHasKey('mid', $fields);
    $this->assertArrayHasKey('type', $fields);
    $this->assertArrayHasKey('arguments', $fields);
    $this->assertArrayHasKey('uid', $fields);
    $this->assertArrayHasKey('timestamp', $fields);
    $this->assertArrayHasKey('language', $fields);

    $ids = $plugin->getIds();
    $this->assertEquals('integer', $ids['mid']['type']);
  }

  /**
   * {@inheritdoc}
   */
  public static function providerSource(): array {
    $tests = [];

    $tests[0]['source_data']['message'] = [
      [
        'mid' => '1',
        'type' => 'example_create_node',
        'arguments' => 'a:0:{}',
        'uid' => '1',
        'timestamp' => '1421727536',
        'language' => 'und',
      ],
      [
        'mid' => '2',
        'type' => 'example_user_register',
        'arguments' => 'a:0:{}',
        'uid' => '2',
        'timestamp' => '1421727600',
        'language' => 'en',
      ],
    ];
    $tests[0]['source_data']['field_config'] = [
      [
        'id' => '1',
        'field_name' => 'field_node_reference',
        'type' => 'entityreference',
        'module' => 'entityreference',
        'active' => '1',
        'storage_type' => 'field_sql_storage',
        'storage_module' => 'field_sql_storage',
        'storage_active' => '1',
        'locked' => '0',
        'data' => 'a:0:{}',
        'cardinality' => '1',
        'translatable' => '0',
        'deleted' => '0',
      ],
    ];
    $tests[0]['source_data']['field_config_instance'] = [
      [
        'id' => '1',
        'field_id' => '1',
        'field_name' => 'field_node_reference',
        'entity_type' => 'message',
        'bundle' => 'example_create_node',
        'data' => 'a:0:{}',
        'deleted' => '0',
      ],
    ];
    $tests[0]['source_data']['field_data_field_node_reference'] = [
      [
        'entity_type' => 'message',
        'bundle' => 'example_create_node',
        'deleted' => '0',
        'entity_id' => '1',
        'revision_id' => '1',
        'language' => 'und',
        'delta' => '0',
        'field_node_reference_target_id' => '10',
      ],
    ];
    $tests[0]['source_data']['system'] = [
      [
        'name' => 'message',
        'type' => 'module',
        'status' => 1,
        'schema_version' => 7000,
      ],
    ];

    $tests[0]['expected_data'] = [
      [
        'mid' => '1',
        'type' => 'example_create_node',
        'arguments' => 'a:0:{}',
        'uid' => '1',
        'timestamp' => '1421727536',
        'language' => 'und',
        'field_node_reference' => [
          [
            'target_id' => '10',
          ],
        ],
      ],
      [
        'mid' => '2',
        'type' => 'example_user_register',
        'arguments' => 'a:0:{}',
        'uid' => '2',
        'timestamp' => '1421727600',
        'language' => 'en',
      ],
    ];
    $tests[0]['expected_count'] = NULL;
    $tests[0]['configuration'] = [];

    // Filter by bundle.
    $tests[1] = $tests[0];
    $tests[1]['configuration'] = [
      'bundle' => ['example_create_node'],
    ];
    $tests[1]['expected_data'] = [
      $tests[0]['expected_data'][0],
    ];

    return $tests;
  }

}
