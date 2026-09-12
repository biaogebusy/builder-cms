<?php

declare(strict_types=1);

namespace Drupal\Tests\message\Kernel\Plugin\migrate\source;

use Drupal\Core\Database\Database;
use Drupal\KernelTests\KernelTestBase;
use Drupal\message\Plugin\migrate\source\MessageTemplateSource;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Tests D7 message template source plugin.
 *
 * Uses the site database because the source query relies on MySQL's RIGHT().
 *
 * @coversDefaultClass \Drupal\message\Plugin\migrate\source\MessageTemplateSource
 *
 * @group Message
 */
class MessageTemplateSourceTest extends KernelTestBase {

  use ProphecyTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'message',
    'migrate',
    'migrate_drupal',
    'system',
    'user',
  ];

  /**
   * Source tables created for this test.
   *
   * @var string[]
   */
  protected array $createdTables = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system']);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    $schema = Database::getConnection()->schema();
    foreach ($this->createdTables as $table) {
      if ($schema->tableExists($table)) {
        $schema->dropTable($table);
      }
    }
    parent::tearDown();
  }

  /**
   * Tests fields and IDs metadata.
   *
   * @covers ::fields
   * @covers ::getIds
   */
  public function testFieldsAndIds(): void {
    $plugin = $this->createSourcePlugin();
    $fields = $plugin->fields();
    $this->assertArrayHasKey('id', $fields);
    $this->assertArrayHasKey('name', $fields);
    $this->assertArrayHasKey('message_text_value', $fields);
    $this->assertArrayHasKey('concat_id', $fields);

    $ids = $plugin->getIds();
    $this->assertEquals('integer', $ids['concat_id']['type']);
  }

  /**
   * Tests querying joined message type and text rows.
   *
   * @covers ::query
   */
  public function testQuery(): void {
    $this->createSourceTables();
    $connection = Database::getConnection();
    $connection->insert('message_type')->fields([
      'id' => 1,
      'name' => 'example_create_node',
      'category' => 'Create node',
      'description' => 'A node was created',
      'argument_keys' => 'a:0:{}',
      'language' => 'und',
      'status' => 1,
      'module' => 'message',
      'arguments' => 'a:0:{}',
      'data' => 'a:0:{}',
    ])->execute();
    $connection->insert('field_data_message_text')->fields([
      'entity_type' => 'message_type',
      'bundle' => 'message_type',
      'deleted' => 0,
      'entity_id' => 1,
      'revision_id' => 1,
      'language' => 'und',
      'delta' => 0,
      'message_text_value' => 'Node created.',
      'message_text_format' => 'filtered_html',
    ])->execute();
    $connection->insert('field_data_message_text')->fields([
      'entity_type' => 'message_type',
      'bundle' => 'message_type',
      'deleted' => 0,
      'entity_id' => 1,
      'revision_id' => 1,
      'language' => 'und',
      'delta' => 1,
      'message_text_value' => 'Second partial.',
      'message_text_format' => 'plain_text',
    ])->execute();
    $connection->insert('system')->fields([
      'name' => 'message',
      'type' => 'module',
      'status' => 1,
      'schema_version' => 7000,
    ])->execute();

    $plugin = $this->createSourcePlugin();
    $reflector = new \ReflectionObject($plugin);
    $property = $reflector->getProperty('database');
    $property->setValue($plugin, $connection);

    // Exercise query() directly against MySQL (RIGHT() is MySQL-specific).
    $result = $plugin->query()->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $this->assertCount(2, $result);
    $this->assertEquals('example_create_node', $result[0]['name']);
    $this->assertEquals('Node created.', $result[0]['message_text_value']);
    $this->assertEquals('0', (string) $result[0]['delta']);
    $this->assertEquals('Second partial.', $result[1]['message_text_value']);
    $this->assertEquals('1', (string) $result[1]['delta']);
    $this->assertArrayHasKey('concat_id', $result[0]);
  }

  /**
   * Creates the source plugin under test.
   */
  protected function createSourcePlugin(): MessageTemplateSource {
    $migration = $this->prophesize(MigrationInterface::class);
    $migration->id()->willReturn('d7_message_template');
    $migration->getIdMap()->willReturn(
      $this->prophesize(MigrateIdMapInterface::class)->reveal()
    );
    $migration->getDestinationIds()->willReturn([]);

    /** @var \Drupal\message\Plugin\migrate\source\MessageTemplateSource $plugin */
    $plugin = $this->container->get('plugin.manager.migrate.source')
      ->createInstance('d7_message_template_source', [], $migration->reveal());
    $migration->getSourcePlugin()->willReturn($plugin);
    return $plugin;
  }

  /**
   * Creates the D7-shaped source tables on the site database.
   */
  protected function createSourceTables(): void {
    $schema = Database::getConnection()->schema();
    $tables = [
      'message_type' => [
        'fields' => [
          'id' => ['type' => 'int', 'not null' => TRUE],
          'name' => ['type' => 'varchar', 'length' => 255],
          'category' => ['type' => 'varchar', 'length' => 255],
          'description' => ['type' => 'varchar', 'length' => 255],
          'argument_keys' => ['type' => 'text'],
          'language' => ['type' => 'varchar', 'length' => 12],
          'status' => ['type' => 'int'],
          'module' => ['type' => 'varchar', 'length' => 255],
          'arguments' => ['type' => 'text'],
          'data' => ['type' => 'text'],
        ],
      ],
      'field_data_message_text' => [
        'fields' => [
          'entity_type' => ['type' => 'varchar', 'length' => 128],
          'bundle' => ['type' => 'varchar', 'length' => 128],
          'deleted' => ['type' => 'int'],
          'entity_id' => ['type' => 'int'],
          'revision_id' => ['type' => 'int'],
          'language' => ['type' => 'varchar', 'length' => 32],
          'delta' => ['type' => 'int'],
          'message_text_value' => ['type' => 'text'],
          'message_text_format' => ['type' => 'varchar', 'length' => 255],
        ],
      ],
      'system' => [
        'fields' => [
          'name' => ['type' => 'varchar', 'length' => 255],
          'type' => ['type' => 'varchar', 'length' => 32],
          'status' => ['type' => 'int'],
          'schema_version' => ['type' => 'int'],
        ],
      ],
    ];

    foreach ($tables as $name => $definition) {
      if ($schema->tableExists($name)) {
        $schema->dropTable($name);
      }
      $schema->createTable($name, $definition);
      $this->createdTables[] = $name;
    }
  }

}
