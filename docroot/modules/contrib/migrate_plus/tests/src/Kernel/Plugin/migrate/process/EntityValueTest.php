<?php

namespace Drupal\Tests\migrate_plus\Kernel\Plugin\migrate\process;

use Drupal\KernelTests\KernelTestBase;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\node\Entity\Node;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\NodeType;

/**
 * Tests the entity_value plugin.
 *
 * @coversDefaultClass \Drupal\migrate_plus\Plugin\migrate\process\EntityValue
 * @group migrate_drupal
 */
class EntityValueTest extends KernelTestBase {

  /**
   * The generated title.
   *
   * @var string
   */
  protected $title;

  /**
   * The generated Spanish title.
   *
   * @var string
   */
  protected $titleSpanish;

  /**
   * The generated node ID.
   *
   * @var int
   */
  protected $uid;

  /**
   * The plugin to test.
   *
   * @var \Drupal\migrate_plus\Plugin\migrate\process\EntityValue
   */
  protected $plugin;


  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'migrate',
    'migrate_plus',
    'system',
    'node',
    'user',
    'language',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    ConfigurableLanguage::createFromLangcode('es')->save();
    $this->installSchema('system', ['sequences']);
    $this->installSchema('node', 'node_access');
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');

    $this->title = $this->randomString();
    $this->titleSpanish = $this->randomString();

    $node = Node::create([
      'type' => 'page',
      'title' => $this->title,
      'langcode' => 'en',
    ]);
    $node->save();

    $node_es = $node->addTranslation('es');
    $node_es->setTitle($this->titleSpanish);
    $node_es->save();

    $this->uid = $node->id();
  }

  /**
   * Test the EntityLoad plugin succeeding.
   *
   * @covers ::transform
   */
  public function testEntityValueSuccess() {
    $this->plugin = \Drupal::service('plugin.manager.migrate.process')
      ->createInstance('entity_value', [
        'entity_type' => 'node',
        'field_name' => 'title',
      ]);
    $executable = $this->prophesize(MigrateExecutableInterface::class)
      ->reveal();
    $row = new Row();

    // Ensure that the entity is returned if it really exists.
    $value = $this->plugin->transform($this->uid, $executable, $row, 'dummmy');
    $this->assertSame($this->title, $value[0]['value']);
    $this->assertFalse($this->plugin->multiple());

    // Ensure that an array of entities is returned.
    $value = $this->plugin->transform([$this->uid], $executable, $row,
      'dummmy');
    $this->assertSame($this->title, $value[0][0]['value']);
    $this->assertTrue($this->plugin->multiple());

    // Ensure that the plugin returns [] if the entity doesn't exist.
    $value = $this->plugin->transform(9999999, $executable, $row, 'dummmy');
    $this->assertSame([], $value);
    $this->assertFalse($this->plugin->multiple());

    // Ensure that the plugin returns [] if NULL is passed.
    $value = $this->plugin->transform(NULL, $executable, $row, 'dummmy');
    $this->assertSame([], $value);
    $this->assertFalse($this->plugin->multiple());
  }

  /**
   * Test the EntityLoad plugin succeeding.
   *
   * @covers ::transform
   */
  public function testEntityValueLangSuccess() {
    $this->plugin = \Drupal::service('plugin.manager.migrate.process')
      ->createInstance('entity_value', [
        'entity_type' => 'node',
        'langcode' => 'es',
        'field_name' => 'title',
      ]);
    $executable = $this->prophesize(MigrateExecutableInterface::class)
      ->reveal();
    $row = new Row();

    // Ensure that the entity is returned if it really exists.
    $value = $this->plugin->transform($this->uid, $executable, $row, 'dummmy');
    $this->assertSame($this->titleSpanish, $value[0]['value']);
    $this->assertFalse($this->plugin->multiple());

    // Ensure that an array of entities is returned.
    $value = $this->plugin->transform([$this->uid], $executable, $row,
      'dummmy');
    $this->assertSame($this->titleSpanish, $value[0][0]['value']);
    $this->assertTrue($this->plugin->multiple());
  }

  /**
   * Test the EntityLoad plugin failure.
   *
   * @covers ::transform
   */
  public function testEntityValueLangException() {
    $config_entity = NodeType::create(['type' => 'page', 'name' => 'page']);
    $config_entity->save();
    $this->plugin = \Drupal::service('plugin.manager.migrate.process')
      ->createInstance('entity_value', [
        'entity_type' => 'node_type',
        'langcode' => 'es',
        'field_name' => 'type',
      ]);

    $executable = $this->prophesize(MigrateExecutableInterface::class)
      ->reveal();
    $row = new Row();

    // Ensure that the entity is returned if it really exists.
    $this->expectException(MigrateException::class);
    $this->expectExceptionMessage('Langcode can only be used with content entities currently.');
    $this->plugin->transform([$config_entity->id()], $executable, $row, 'dummmy');
  }

  /**
   * Test the EntityLoad plugin throwing.
   *
   * @param mixed $config
   *   the Plugin Config.
   *
   * @covers ::__construct
   * @dataProvider entityValueFailureConfigData
   */
  public function testEntityValueConfig($config) {
    $this->expectException(\InvalidArgumentException::class);
    \Drupal::service('plugin.manager.migrate.process')
      ->createInstance('entity_value', $config);
  }

  /**
   * Provides data for entityLoadFailureConfigData.
   *
   * @return array
   *   The data.
   */
  public function entityValueFailureConfigData() {
    return [
      [
        [
          'entity_type' => '',
        ],
      ],
      [
        [
          'entity_type' => NULL,
        ],
      ],
      [
        [
          'entity_type' => 'node',
          'source' => '',
        ],
      ],
      [
        [
          'entity_type' => 'node',
          'source' => NULL,
        ],
      ],
      [
        [
          'entity_type' => 'node',
          'source' => 'test',
          'field_name' => '',
        ],
      ],
      [
        [
          'entity_type' => 'node',
          'source' => 'test',
          'field_name' => NULL,
        ],
      ],
    ];
  }

}
