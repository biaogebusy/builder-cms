<?php

namespace Drupal\Tests\workspace\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests permission controls on workspaces.
 *
 * @group workspace
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 */
class WorkspaceViewTest extends BrowserTestBase {
  use WorkspaceTestUtilities;

  public static $modules = ['workspace', 'multiversion'];

  /**
   * Verifies that a user can view their own workspace.
   */
  public function testViewOwnWorkspace() {
    $permissions = [
      'access administration pages',
      'administer site configuration',
      'create_workspace',
      'edit_own_workspace',
      'view_own_workspace',
    ];

    $editor1 = $this->drupalCreateUser($permissions);

    // Login as a limited-access user and create a workspace.
    $this->drupalLogin($editor1);

    $this->createWorkspaceThroughUI('Bears', 'bears');

    $bears = $this->getOneWorkspaceByLabel('Bears');

    // Now login as a different user and create a workspace.

    $editor2 = $this->drupalCreateUser($permissions);

    $this->drupalLogin($editor2);
    $session = $this->getSession();

    $this->createWorkspaceThroughUI('Packers', 'packers');

    $packers = $this->getOneWorkspaceByLabel('Packers');

    // Load the activate form for the Bears workspace. It should fail because
    // the workspace belongs to someone else.
    $this->drupalGet("admin/structure/workspace/{$bears->id()}/activate");
    $this->assertEquals(403, $session->getStatusCode());

    // But editor 2 should be able to activate the Packers workspace.
    $this->drupalGet("admin/structure/workspace/{$packers->id()}/activate");
    $this->assertEquals(200, $session->getStatusCode());
  }

  /**
   * Verifies that a user can view any workspace.
   */
  public function testViewAnyWorkspace() {
    $permissions = [
      'access administration pages',
      'administer site configuration',
      'create_workspace',
      'edit_own_workspace',
      'view_any_workspace',
    ];

    $editor1 = $this->drupalCreateUser($permissions);

    // Login as a limited-access user and create a workspace.
    $this->drupalLogin($editor1);

    $this->createWorkspaceThroughUI('Bears', 'bears');

    $bears = $this->getOneWorkspaceByLabel('Bears');

    // Now login as a different user and create a workspace.

    $editor2 = $this->drupalCreateUser($permissions);

    $this->drupalLogin($editor2);
    $session = $this->getSession();

    $this->createWorkspaceThroughUI('Packers', 'packers');

    $packers = $this->getOneWorkspaceByLabel('Packers');

    // Load the activate form for the Bears workspace. This user should be
    // able to see both workspaces because of the "view any" permission.
    $this->drupalGet("admin/structure/workspace/{$bears->id()}/activate");
    $this->assertEquals(200, $session->getStatusCode());

    // But editor 2 should be able to activate the Packers workspace.
    $this->drupalGet("admin/structure/workspace/{$packers->id()}/activate");
    $this->assertEquals(200, $session->getStatusCode());
  }
  
}
