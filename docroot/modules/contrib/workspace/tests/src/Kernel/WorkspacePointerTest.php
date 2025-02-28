<?php

namespace Drupal\Tests\workspace\Kernel;

use Drupal\multiversion\Entity\Workspace;
use Drupal\multiversion\Entity\WorkspaceInterface;
use Drupal\multiversion\Entity\WorkspaceType;
use Drupal\workspace\Entity\WorkspacePointer;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests for the WorkspacePointer entity.
 *
 * @requires key_value
 * @requires multiversion
 *
 * @group workspace
 */
class WorkspacePointerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['workspace', 'replication', 'multiversion', 'key_value', 'serialization', 'user', 'system'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installEntitySchema('workspace');
    $this->installEntitySchema('workspace_pointer');
    $this->installEntitySchema('user');
  }

  /**
   * Creates a workspace type for testing purposes.
   *
   * @return WorkspaceType
   */
  protected function createWorkspaceType() {
    $workspace_type = WorkspaceType::create([
      'id' => 'test',
      'label' => 'Workspace bundle',
    ]);
    $workspace_type->save();

    return $workspace_type;
  }

  /**
   * Creates a workspace for testing purposes.
   *
   * @return WorkspaceInterface
   */
  protected function createWorkspace() {
    $workspace = Workspace::create([
      'type' => 'test',
      'machine_name' => 'le_workspace',
      'label' => 'Le Workspace',
    ]);
    $workspace->save();

    return $workspace;
  }

  /**
   * Verifies pointers can be created using an object as the reference.
   */
  public function testCreationWithObject() {
    $this->createWorkspaceType();

    $workspace = $this->createWorkspace();

    /** @var WorkspacePointer $pointer */
    $pointer = WorkspacePointer::create();
    $pointer->setWorkspace($workspace);
    $pointer->save();

    $id = $pointer->id();

    $pointer = WorkspacePointer::load($id);

    $this->assertEquals($workspace->id(), $pointer->getWorkspaceId());
    $this->assertEquals($workspace->id(), $pointer->getWorkspace()->id());
  }

  /**
   * Verifies pointers can be created using an ID as the reference.
   */
  public function testCreationWithId() {
    $this->createWorkspaceType();

    $workspace = $this->createWorkspace();

    /** @var WorkspacePointer $pointer */
    $pointer = WorkspacePointer::create();
    $pointer->setWorkspaceId($workspace->id());
    $pointer->save();

    $id = $pointer->id();

    $pointer = WorkspacePointer::load($id);

    $this->assertEquals($workspace->id(), $pointer->getWorkspaceId());
    $this->assertEquals($workspace->id(), $pointer->getWorkspace()->id());
  }
}
