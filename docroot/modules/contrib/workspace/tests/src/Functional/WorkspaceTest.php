<?php

namespace Drupal\Tests\workspace\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Test the workspace entity.
 *
 * @group workspace
 */
class WorkspaceTest extends BrowserTestBase {
  use WorkspaceTestUtilities;

  public static $modules = ['workspace'];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $permissions = [
      'access administration pages',
      'administer site configuration',
      'administer workspaces',
      'create_workspace',
      'edit_own_workspace',
    ];

    $editor1 = $this->drupalCreateUser($permissions);
    $this->drupalLogin($editor1);
  }

  /**
   * Tests machine names allow the same characters as CouchDB does.
   */
  public function testSpecialCharacters() {
    // Test a valid workspace name
    $this->createWorkspaceThroughUI('Workspace 1', 'a0_b1_');

    // Test and invaid workspace name
    $this->drupalGet('/admin/structure/workspace/add');
    $session = $this->getSession();
    $this->assertEquals(200, $session->getStatusCode());
    $page = $session->getPage();
    $page->fillField('label', 'workspace2');
    $page->fillField('machine_name', 'A!"£%^&*{}#~@?');
    $page->findButton(t('Save'))->click();
    $session->getPage()->hasContent("This value is not valid");
  }

  /**
   * Tests the creation of workspaces.
   */
  public function testWorkspaceCreation() {
    $web_assert = $this->assertSession();
    // Create two workspaces.
    $workspace_1 = $this->createWorkspaceThroughUI('Workspace 1', 'workspace_1');
    $workspace_2 = $this->createWorkspaceThroughUI('Workspace 2', 'workspace_2');

    // Check they appear on the listing.
    $this->drupalGet('/admin/structure/workspace');
    $web_assert->pageTextContains($workspace_1->label());
    $web_assert->pageTextContains($workspace_2->label());

    // Check they both appear as possible upstreams for each other.
    $this->drupalGet($workspace_1->toUrl('edit-form')->toString());
    $web_assert->fieldNotExists($workspace_1->label());
    $web_assert->fieldExists($workspace_2->label());
    $this->drupalGet($workspace_2->toUrl('edit-form')->toString());
    $web_assert->fieldExists($workspace_1->label());
    $web_assert->fieldNotExists($workspace_2->label());

    // Unpublish the first workspace.
    $workspace_1->setUnpublished()->save();

    // Check it doesn't appear in the listing anymore.
    $this->drupalGet('/admin/structure/workspace');
    $web_assert->pageTextNotContains($workspace_1->label());
    $web_assert->pageTextContains($workspace_2->label());

    // Check it doesn't appear as a possible upstream anymore.
    $this->drupalGet($workspace_1->toUrl('edit-form')->toString());
    $web_assert->fieldNotExists($workspace_1->label());
    $web_assert->fieldExists($workspace_2->label());
    $this->drupalGet($workspace_2->toUrl('edit-form')->toString());
    $web_assert->fieldNotExists($workspace_1->label());
    $web_assert->fieldNotExists($workspace_2->label());

  }
  
}
