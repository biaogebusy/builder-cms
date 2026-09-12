<?php

namespace Drupal\Tests\ctools\Kernel;

use Drupal\ctools\Testing\EntityCreationTrait;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;

/**
 * Base class for relationship tests.
 */
abstract class RelationshipsTestBase extends KernelTestBase {
  use EntityCreationTrait;

  /**
   * The relationship manager.
   *
   * @var \Drupal\ctools\Plugin\RelationshipManagerInterface
   */
  protected $relationshipManager;

  /**
   * The entities used by tests.
   *
   * @var \Drupal\Core\Entity\EntityInterface[]
   */
  protected $entities = [];

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'user',
    'system',
    'node',
    'field',
    'text',
    'filter',
    'ctools',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node_type');
    $this->installEntitySchema('node');
    $this->installConfig(['node']);
    $page = $this->createEntity('node_type', [
      'type' => 'page',
      'name' => 'Page',
    ]);
    $this->addBodyField($page->id());
    $article = $this->createEntity('node_type', [
      'type' => 'article',
      'name' => 'Article',
    ]);
    // Not adding the body field the articles so that we can perform a test.
    $foo = $this->createEntity('node_type', [
      'type' => 'foo',
      'name' => 'Foo',
    ]);
    $this->addBodyField($foo->id());
    $this->relationshipManager = $this->container->get('plugin.manager.ctools.relationship');

    $user = $this->createEntity('user', [
      'name' => 'test_user',
      'password' => 'password',
      'mail' => 'mail@test.com',
      'status' => 1,
    ]);
    $node1 = $this->createEntity('node', [
      'title' => 'Node 1',
      'type' => 'page',
      'uid' => $user->id(),
      'body' => 'This is a test',
    ]);
    $node2 = $this->createEntity('node', [
      'title' => 'Node 2',
      'type' => 'article',
      'uid' => $user->id(),
    ]);
    $node3 = $this->createEntity('node', [
      'title' => 'Node 3',
      'type' => 'foo',
      'uid' => $user->id(),
    ]);
    $node4 = $this->createEntity('node', [
      'title' => 'Node 4',
      'type' => 'foo',
    ])->set('uid', NULL);

    $this->entities = [
      'user' => $user,
      'node1' => $node1,
      'node2' => $node2,
      'node3' => $node3,
      'node4' => $node4,
    ];
  }

  /**
   * Adds a body field to the given node type.
   *
   * The test fixture owns this field rather than relying on core: the node
   * body field storage moved to the optional node_storage_body_field module
   * in Drupal 11.4, and node_add_body_field() is removed in Drupal 12.
   *
   * @param string $node_type
   *   The node type to attach the body field to.
   */
  protected function addBodyField($node_type) {
    if (!FieldStorageConfig::loadByName('node', 'body')) {
      FieldStorageConfig::create([
        'field_name' => 'body',
        'entity_type' => 'node',
        'type' => 'text_with_summary',
      ])->save();
    }
    FieldConfig::create([
      'field_name' => 'body',
      'entity_type' => 'node',
      'bundle' => $node_type,
      'label' => 'Body',
    ])->save();
  }

}
