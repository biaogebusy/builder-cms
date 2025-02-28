<?php

namespace Drupal\Tests\workspace\Functional;

use Drupal\multiversion\Entity\Workspace;
use Drupal\multiversion\Entity\WorkspaceType;
use Drupal\Tests\BrowserTestBase;
use Drupal\workspace\Controller\WorkspaceController;
use Drupal\workspace\Entity\WorkspacePointer;

/**
 * Test the WorkspaceController.
 *
 * @group workspace
 */
class WorkspaceControllerTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['workspace', 'multiversion'];

  /**
   * WorkspaceController.
   *
   * @var \Drupal\workspace\Controller\WorkspaceController
   */
  private $controller;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $permissions = [
      'create_workspace',
      'edit_own_workspace',
      'view_own_workspace',
      'access administration pages',
      'administer site configuration',
    ];

    $test_user = $this->drupalCreateUser($permissions);
    $this->drupalLogin($test_user);

    $this->controller = new WorkspaceController();
  }

  /**
   * {@inheritdoc}
   */
  public function testAddForm() {
    $types = WorkspaceType::loadMultiple();
    $type = reset($types);
    $this->assertInstanceOf(WorkspaceType::class, $type);

    $workspace_id = $this->controller->getDefaultWorkspaceId();
    $workspace = Workspace::load($workspace_id);
    $workspace_pointer_id = WorkspacePointer::loadFromWorkspace($workspace)->id();

    $form = $this->controller->addForm($type);
    $this->assertEquals($form['upstream']['widget']['#value'], $workspace_pointer_id);

    $this->config('workspace.settings')->set('upstream', 0)->save();
    $form = $this->controller->addForm($type);
    $this->assertEquals($form['upstream']['widget']['#value'], $workspace_pointer_id);

    $this->config('workspace.settings')->set('upstream', $workspace_pointer_id)->save();
    $form = $this->controller->addForm($type);
    $this->assertEquals($form['upstream']['widget']['#value'], $workspace_pointer_id);

    $this->config('workspace.settings')->set('upstream', 12345)->save();
    $form = $this->controller->addForm($type);
    $this->assertNull($form['upstream']['widget']['#value']);

    $test_workspace = Workspace::create([
      'type' => 'test',
      'machine_name' => 'test',
      'label' => 'Test',
    ]);
    $test_workspace->save();
    $test_workspace_pointer_id = WorkspacePointer::loadFromWorkspace($test_workspace)->id();

    $this->config('workspace.settings')->set('upstream', $test_workspace_pointer_id)->save();
    $form = $this->controller->addForm($type);
    $this->assertEquals($form['upstream']['widget']['#value'], $test_workspace_pointer_id);
  }

}
