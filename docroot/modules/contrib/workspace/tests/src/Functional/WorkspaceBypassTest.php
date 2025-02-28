<?php

namespace Drupal\Tests\workspace\Functional;

use Drupal\simpletest\BlockCreationTrait;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests access bypass permission controls on workspaces.
 *
 * @group workspace
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class WorkspaceBypassTest extends BrowserTestBase {
  use WorkspaceTestUtilities;
  use BlockCreationTrait {
    placeBlock as drupalPlaceBlock;
  }

  public static $modules = ['node', 'user', 'block', 'workspace', 'multiversion'];

  /**
   * Verifies that a user can edit anything in a workspace with a specific perm.
   */
  public function testBypassSpecificWorkspace() {
    $permissions = [
      'create_workspace',
      'edit_own_workspace',
      'view_own_workspace',
    ];

    $this->createNodeType('Test', 'test');
    $this->setupWorkspaceSwitcherBlock();

    $ditka = $this->drupalCreateUser(array_merge($permissions, ['create test content']));

    // Login as a limited-access user and create a workspace.
    $this->drupalLogin($ditka);

    $vanilla_node = $this->createNodeThroughUI('Vanilla node', 'test');

    $bears = $this->createWorkspaceThroughUI('Bears', 'bears');

    // Replicate all content from the default workspace to Bears.
    $live = $this->getOneEntityByLabel('workspace', 'Live');
    /** @var \Drupal\workspace\ReplicatorManager $rm */
    $rm = \Drupal::service('workspace.replicator_manager');
    $rm->replicate($this->getPointerToWorkspace($live), $this->getPointerToWorkspace($bears));
    \Drupal::service('cron')->run();

    $this->switchToWorkspace($bears);

    // Now create a node in the Bears workspace, as the owner of that workspace.
    $ditka_bears_node = $this->createNodeThroughUI('Ditka Bears node', 'test');
    $ditka_bears_node_id = $ditka_bears_node->id();

    // Create a new user that should be able to edit anything in the Bears workspace.
    $lombardi = $this->drupalCreateUser(array_merge($permissions, ['view_workspace_' . $bears->id(), 'bypass_entity_access_workspace_' . $bears->id()]));
    $this->drupalLogin($lombardi);
    $this->switchToWorkspace($bears);

    // Because Lombardi has the bypass permission, he should be able to
    // create and edit any node.
    $this->drupalGet('/node/' . $ditka_bears_node_id . '/edit');
    $session = $this->getSession();
    $this->assertEquals(200, $session->getStatusCode());

    $bears_vanilla_node = $this->getOneEntityByLabel('node', 'Vanilla node');
    $this->drupalGet('/node/' . $bears_vanilla_node->id() . '/edit');
    $session = $this->getSession();
    $this->assertEquals(200, $session->getStatusCode());

    $lombardi_bears_node = $this->createNodeThroughUI('Lombardi Bears node', 'test');
    $lombardi_bears_node_id = $lombardi_bears_node->id();

    $this->drupalLogin($ditka);
    $this->switchToWorkspace($bears);

    $this->drupalGet('/node/' . $lombardi_bears_node_id . '/edit');
    $session = $this->getSession();
    $this->assertEquals(403, $session->getStatusCode());

    // Create a new user that should NOT be able to edit anything in the Bears workspace.
    $belichick = $this->drupalCreateUser(array_merge($permissions, ['view_workspace_' . $bears->id()]));
    $this->drupalLogin($belichick);
    $this->switchToWorkspace($bears);

    $this->drupalGet('/node/' . $ditka_bears_node_id . '/edit');
    $session = $this->getSession();
    $this->assertEquals(403, $session->getStatusCode());

    $this->drupalGet('/node/' . $bears_vanilla_node->id() . '/edit');
    $session = $this->getSession();
    $this->assertEquals(403, $session->getStatusCode());
  }

  /**
   * Verifies that a user can edit anything in a workspace they own.
   */
  public function testBypassOwnWorkspace() {
    $permissions = [
      'create_workspace',
      'edit_own_workspace',
      'view_own_workspace',
      'bypass_entity_access_own_workspace',
    ];

    $this->createNodeType('Test', 'test');
    $this->setupWorkspaceSwitcherBlock();

    $ditka = $this->drupalCreateUser(array_merge($permissions, ['create test content']));

    // Login as a limited-access user and create a workspace.
    $this->drupalLogin($ditka);

    $vanilla_node = $this->createNodeThroughUI('Vanilla node', 'test');

    $bears = $this->createWorkspaceThroughUI('Bears', 'bears');

    // Replicate all content from the default workspace to Bears.
    $live = $this->getOneEntityByLabel('workspace', 'Live');
    /** @var \Drupal\workspace\ReplicatorManager $rm */
    $rm = \Drupal::service('workspace.replicator_manager');
    $rm->replicate($this->getPointerToWorkspace($live), $this->getPointerToWorkspace($bears));
    \Drupal::service('cron')->run();

    $this->switchToWorkspace($bears);

    // Now create a node in the Bears workspace, as the owner of that workspace.
    $ditka_bears_node = $this->createNodeThroughUI('Ditka Bears node', 'test');
    $ditka_bears_node_id = $ditka_bears_node->id();

    // Editing both nodes should be possible.
    $this->drupalGet('/node/' . $ditka_bears_node_id . '/edit');
    $session = $this->getSession();
    $this->assertEquals(200, $session->getStatusCode());

    $bears_vanilla_node = $this->getOneEntityByLabel('node', 'Vanilla node');
    $this->drupalGet('/node/' . $bears_vanilla_node->id() . '/edit');
    $session = $this->getSession();
    $this->assertEquals(200, $session->getStatusCode());

    // Create a new user that should be able to edit anything in the Bears workspace.
    $lombardi = $this->drupalCreateUser(array_merge($permissions, ['view_workspace_' . $bears->id()]));
    $this->drupalLogin($lombardi);
    $this->switchToWorkspace($bears);

    // Because editor 2 has the bypass permission, he should be able to
    // create and edit any node.
    $this->drupalGet('/node/' . $ditka_bears_node_id . '/edit');
    $session = $this->getSession();
    $this->assertEquals(403, $session->getStatusCode());

    $this->drupalGet('/node/' . $bears_vanilla_node->id() . '/edit');
    $session = $this->getSession();
    $this->assertEquals(403, $session->getStatusCode());
  }

}
