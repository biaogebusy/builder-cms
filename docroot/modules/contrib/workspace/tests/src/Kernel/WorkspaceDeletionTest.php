<?php

namespace Drupal\Tests\workspace\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\multiversion\Entity\Workspace;
use Drupal\multiversion\Entity\WorkspaceType;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Tests for deleting workspaces.
 *
 * @requires key_value
 * @requires multiversion
 *
 * @group workspace
 */
class WorkspaceDeletionTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'datetime',
    'node',
    'workspace',
    'replication',
    'multiversion',
    'key_value',
    'serialization',
    'user',
    'system'
  ];

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\Core\Cron
   */
  protected $cron;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installSchema('node', 'node_access');
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('workspace');
    $this->installEntitySchema('replication');
    $this->installEntitySchema('workspace_pointer');
    $this->installEntitySchema('replication_log');
    $this->installSchema('key_value', 'key_value_sorted');
    $this->installSchema('system', ['key_value_expire', 'sequences']);
    $this->installConfig('multiversion');
    $this->entityTypeManager = \Drupal::entityTypeManager();
    $this->cron = \Drupal::service('cron');
    \Drupal::service('multiversion.manager')->enableEntityTypes();
  }

  public function testDeletingWorkspace() {
    $workspace_type = WorkspaceType::create([
      'id' => 'test',
      'label' => 'Workspace bundle',
    ]);
    $workspace_type->save();

    $workspace = Workspace::create([
      'type' => 'test',
      'machine_name' => 'le_workspace',
      'label' => 'Le Workspace',
    ]);
    $workspace->save();

    $node_type = NodeType::create([
      'name' => 'le content type',
      'type' => 'le_content_type',
    ]);
    $node_type->save();
    $node = Node::create([
      'type' => 'le_content_type',
      'workspace' => [
        'target_id' => $workspace->id(),
      ],
      'title' => 'le content',
    ]);
    $node->save();
    $node2 = Node::create([
      'type' => 'le_content_type',
      'workspace' => [
        'target_id' => $workspace->id(),
      ],
      'title' => 'le content deux',
    ]);
    $node2->save();

    $node3 = Node::create([
      'type' => 'le_content_type',
      'workspace' => [
        'target_id' => $workspace->id(),
      ],
      'title' => 'le content trois',
    ]);
    $node3->save();
    $node3->delete();

    $workspace->delete();
    $this->cron->run();

    $workspaces = $this->entityTypeManager->getStorage('workspace')->getQuery()->execute();
    $nodes = \Drupal::entityTypeManager()->getStorage('node')->getOriginalStorage()->getQuery()->execute();
    $this->assertEmpty($workspaces, 'No workspaces');
    $this->assertEmpty($nodes, 'No nodes');
  }

}
