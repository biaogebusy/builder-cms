<?php

namespace Drupal\Tests\replication\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\multiversion\Entity\Workspace;
use Drupal\node\Entity\NodeType;

/**
 * Tests link stub replication.
 *
 * @group replication
 */
class LinkStubReplicationTest extends KernelTestBase {

  /**
   * @var \Symfony\Component\Serializer\SerializerInterface
   */
  private $serializer;

  public static $modules = [
    'serialization',
    'system',
    'multiversion',
    'key_value',
    'field',
    'replication',
    'text',
    'user',
    'link',
    'node',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('replication_log');
    $this->installEntitySchema('workspace');
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installSchema('key_value', ['key_value_sorted']);
    $this->installSchema('system', ['sequences']);
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['multiversion', 'replication']);
    Workspace::create(['machine_name' => 'live', 'label' => 'Live', 'type' => 'basic'])->save();
    $this->container->get('multiversion.manager')->enableEntityTypes();
    $this->serializer = $this->container->get('serializer');
    NodeType::create(['type' => 'article_with_link', 'name' => 'article_with_link'])->save();
    NodeType::create(['type' => 'article', 'name' => 'article'])->save();
    $this->createLinkField('node', 'article_with_link', 'field_link');
    $this->createTextField('node', 'article', 'field_test');
  }

  /**
   * Tests replication of link stubs.
   */
  public function testLinkStubReplication() {
    $workspace = Workspace::load(1);

    $referenced_node = [
      '@context' => [
        '_id' => '@id',
        '@language' => 'en',
      ],
      '@type' => 'node',
      '_id' => 'c0b478b6-3a86-4571-8a83-a32c2f18ecd7',
      '_rev' => '1-9340f2de88f2a238083c7afd92c5651a',
      'en' => [
        '@context' => [
          '@language' => 'en',
        ],
        'langcode' => [
          ['value' => 'en'],
        ],
        'type' => [
          ['target_id' => 'article'],
        ],
        'title' => [
          ['value' => 'Article node'],
        ],
        'uid' => [
          ['target_id' => 0],
        ],
        'status' => [
          ['value' => TRUE],
        ],
        'created' => [
          ['value' => 1492122137],
        ],
        'changed' => [
          ['value' => 1492122137],
        ],
        '_rev' =>
          [
            ['value' => '1-9340f2de88f2a238083c7afd92c5651a'],
          ],
        'field_test' =>
          [
            ['value' => 'Test value'],
          ],
      ],
    ];

    $node_with_reference = [
      '@context' => [
        '_id' => '@id',
        '@language' => 'en',
      ],
      '@type' => 'node',
      '_id' => 'e3bb9038-157b-4822-8fe0-ed4a9f414a10',
      '_rev' => '1-b939e3b3c3fac61095cd8cb75e5855e6',
      'en' => [
        '@context' =>
          ['@language' => 'en'],
        'langcode' =>
          [
            ['value' => 'en',],
          ],
        'type' => [
          ['target_id' => 'article_with_link'],
        ],
        'title' => [
          ['value' => 'rc2zpt2S'],
        ],
        'uid' => [
          ['target_id' => 0],
        ],
        'status' => [
          ['value' => TRUE],
        ],
        'created' => [
          ['value' => 1492120498],
        ],
        'changed' => [
          ['value' => 1492120498],
        ],

        'default_langcode' => [
          ['value' => TRUE],
        ],
        '_rev' => [
          ['value' => '1-b939e3b3c3fac61095cd8cb75e5855e6'],
        ],
        'field_link' => [
          [
            'uri' => 'entity:node/1',
            'entity_type_id' => 'node',
            'target_uuid' => 'c0b478b6-3a86-4571-8a83-a32c2f18ecd7',
            'type' => 'article',
          ],
        ],
      ],
    ];

    $data = [
      'docs' => [$node_with_reference, $referenced_node],
      'new_edits' => FALSE,
    ];

    /** @var \Drupal\replication\BulkDocs\BulkDocs $bulk_docs */
    $bulk_docs = $this->serializer->denormalize($data, 'Drupal\replication\BulkDocs\BulkDocs', 'json', ['workspace' => $workspace]);
    $bulk_docs->save();

    $node = $this->container->get('entity.repository')->loadEntityByUuid('node', 'c0b478b6-3a86-4571-8a83-a32c2f18ecd7');
    $this->assertEquals('Test value', $node->field_test->value);

  }

  /**
   * Create text field for entity type.
   *
   * @param string $entity_type
   *   Entity type.
   * @param string $bundle
   *   Entity type bundle.
   * @param $field_name
   *   Name of the text field to create.
   */
  protected function createTextField($entity_type, $bundle, $field_name) {
    FieldStorageConfig::create([
      'field_name' => $field_name,
      'type' => 'string',
      'entity_type' => $entity_type,
      'cardinality' => 1,
    ])->save();

    FieldConfig::create([
      'field_name' => $field_name,
      'entity_type' => $entity_type,
      'bundle' => $bundle,
      'label' => 'Test text-field',
    ])->save();
  }

  /**
   * Create link field for entity type.
   *
   * @param string $entity_type
   *   Entity type.
   * @param string $bundle
   *   Entity type bundle.
   * @param $field_name
   *   Name of the link field to create.
   */
  protected function createLinkField($entity_type, $bundle, $field_name) {
    FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => $entity_type,
      'type' => 'link',
      'cardinality' => 2,
      'translatable' => FALSE,
    ])->save();
    FieldConfig::create([
      'entity_type' => $entity_type,
      'field_name' => $field_name,
      'bundle' => $bundle,
      'label' => 'Test link-field',
      'widget' => [
        'type' => 'link',
        'weight' => 0,
      ],
    ])->save();
  }

}
