<?php

namespace Drupal\Tests\workspace\Functional;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\multiversion\Entity\WorkspaceInterface;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests permission controls on workspaces.
 *
 * @group workspace
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class WorkspacePermissionsTest extends BrowserTestBase {
  use WorkspaceTestUtilities;

  public static $modules = ['workspace', 'multiversion'];

  /**
   * Verifies that a user can create but not edit a workspace.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   */
  public function testCreateWorkspace() {
    $editor = $this->drupalCreateUser([
      'access administration pages',
      'administer site configuration',
      'create_workspace',
    ]);

    // Login as a limited-access user and create a workspace.
    $this->drupalLogin($editor);
    $session = $this->getSession();

    $this->drupalGet('/admin/structure/workspace/add');

    $this->assertEquals(200, $session->getStatusCode());

    $page = $session->getPage();
    $page->fillField('label', 'Bears');
    $page->fillField('machine_name', 'bears');
    $page->findButton(t('Save'))->click();

    $session->getPage()->hasContent('Bears (bears)');

    // Now edit that same workspace; We shouldn't be able to do so, since
    // we don't have edit permissions.

    /** @var EntityTypeManagerInterface $etm */
    $etm = \Drupal::service('entity_type.manager');
    /** @var WorkspaceInterface $bears */
    $entity_list = $etm->getStorage('workspace')->loadByProperties(['label' => 'Bears']);
    $bears = current($entity_list);

    $this->drupalGet("/admin/structure/workspace/{$bears->id()}/edit");
    $this->assertEquals(403, $session->getStatusCode());

    // @todo add Deletion checks once there's a UI for deletion.
  }

  /**
   * Verifies that a user can create and edit only their own workspace.
   */
  public function testEditOwnWorkspace() {
    $permissions = [
      'access administration pages',
      'administer site configuration',
      'create_workspace',
      'edit_own_workspace',
    ];

    $editor1 = $this->drupalCreateUser($permissions);

    // Login as a limited-access user and create a workspace.
    $this->drupalLogin($editor1);

    $this->createWorkspaceThroughUI('Bears', 'bears');

    // Now edit that same workspace; We should be able to do so.

    $bears = $this->getOneWorkspaceByLabel('Bears');

    $session = $this->getSession();

    $this->drupalGet("/admin/structure/workspace/{$bears->id()}/edit");
    $this->assertEquals(200, $session->getStatusCode());

    $page = $session->getPage();
    $page->fillField('label', 'Bears again');
    $page->fillField('machine_name', 'bears');
    $page->findButton(t('Save'))->click();
    $session->getPage()->hasContent('Bears again (bears)');

    // Now login as a different user and ensure they don't have edit access,
    // and vice versa.

    $editor2 = $this->drupalCreateUser($permissions);

    $this->drupalLogin($editor2);
    $session = $this->getSession();

    $this->createWorkspaceThroughUI('Packers', 'packers');

    $packers = $this->getOneWorkspaceByLabel('Packers');

    $this->drupalGet("/admin/structure/workspace/{$packers->id()}/edit");
    $this->assertEquals(200, $session->getStatusCode());

    $this->drupalGet("/admin/structure/workspace/{$bears->id()}/edit");
    $this->assertEquals(403, $session->getStatusCode());
  }

  /**
   * Verifies that a user can edit any workspace.
   */
  public function testEditAnyWorkspace() {
    $permissions = [
      'access administration pages',
      'administer site configuration',
      'create_workspace',
      'edit_own_workspace',
    ];

    $editor1 = $this->drupalCreateUser($permissions);

    // Login as a limited-access user and create a workspace.
    $this->drupalLogin($editor1);

    $this->createWorkspaceThroughUI('Bears', 'bears');

    // Now edit that same workspace; We should be able to do so.

    $bears = $this->getOneWorkspaceByLabel('Bears');

    $session = $this->getSession();

    $this->drupalGet("/admin/structure/workspace/{$bears->id()}/edit");
    $this->assertEquals(200, $session->getStatusCode());

    $page = $session->getPage();
    $page->fillField('label', 'Bears again');
    $page->fillField('machine_name', 'bears');
    $page->findButton(t('Save'))->click();
    $session->getPage()->hasContent('Bears again (bears)');

    // Now login as a different user and ensure they don't have edit access,
    // and vice versa.

    $admin = $this->drupalCreateUser(array_merge($permissions, ['edit_any_workspace']));

    $this->drupalLogin($admin);
    $session = $this->getSession();

    $this->createWorkspaceThroughUI('Packers', 'packers');

    $packers = $this->getOneWorkspaceByLabel('Packers');

    $this->drupalGet("/admin/structure/workspace/{$packers->id()}/edit");
    $this->assertEquals(200, $session->getStatusCode());

    $this->drupalGet("/admin/structure/workspace/{$bears->id()}/edit");
    $this->assertEquals(200, $session->getStatusCode());
  }

}
