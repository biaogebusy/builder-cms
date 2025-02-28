<?php

namespace Drupal\Tests\workspace\Functional;

use Drupal\simpletest\BlockCreationTrait;
use Drupal\Tests\BrowserTestBase;

/**
 * Test replication settings on replicate.
 *
 * @group workspace
 */
class ReplicationSettingsTest extends BrowserTestBase {

  use WorkspaceTestUtilities;
  use BlockCreationTrait {
    placeBlock as drupalPlaceBlock;
  }

  /**
   * The replicator manager service used to trigger replication.
   *
   * @var \Drupal\workspace\ReplicatorManager
   */
  protected $replicatorManager;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'system',
    'node',
    'user',
    'block',
    'block_content',
    'workspace',
    'multiversion',
    'entity_reference',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->replicatorManager = $this->container->get('workspace.replicator_manager');
  }

  /**
   * Verify pull replication settings using the published filter as an example.
   */
  public function testPullReplicationSettings() {
    $permissions = [
      'create_workspace',
      'edit_own_workspace',
      'view_own_workspace',
      'create test content',
      'access content overview',
      'administer content types',
      'administer nodes',
      'access content overview',
      'access replication settings fields',
    ];

    $this->createNodeType('Test', 'test');
    $this->setupWorkspaceSwitcherBlock();
    $this->drupalPlaceBlock('local_actions_block', ['id' => 'actions_block']);
    $test_user = $this->drupalCreateUser($permissions);
    $this->drupalLogin($test_user);
    $session = $this->getSession();

    // Create a published node.
    $this->drupalGet('/node/add/test');
    // For Drupal 8.4.x.
    if ($this->xpath('//input[@id="edit-status-value"]')) {
      $this->drupalPostForm(NULL, [
        'title[0][value]' => 'Published node',
      ], t('Save'));
    }
    // For Drupal 8.3.x.
    else {
      $this->drupalPostForm(NULL, [
        'title[0][value]' => 'Published node',
      ], t('Save and publish'));
    }
    $page = $session->getPage();
    $page->hasContent('Published node has been created');
    $this->assertTrue($this->isLabelInContentOverview('Published node'));

    // Create an unpublished node.
    $this->drupalGet('/node/add/test');
    // For Drupal 8.4.x.
    if ($this->xpath('//input[@id="edit-status-value"]')) {
      $this->drupalPostForm(NULL, [
        'status[value]' => FALSE,
        'title[0][value]' => 'Unpublished node',
      ], t('Save'));
    }
    // For Drupal 8.3.x.
    else {
      $this->drupalPostForm(NULL, [
        'title[0][value]' => 'Unpublished node',
      ], t('Save as unpublished'));
    }
    $page = $session->getPage();
    $page->hasContent('Unpublished node has been created');
    $this->assertTrue($this->isLabelInContentOverview('Unpublished node'));

    // Create a target workspace with replication settings.
    $this->drupalGet('/admin/structure/workspace/add');
    $session = $this->getSession();
    $this->assertSession()->statusCodeEquals(200);
    $page = $session->getPage();
    $page->fillField('label', 'Target');
    $page->fillField('machine_name', 'target');
    $page->selectFieldOption('upstream', '1');
    $page->selectFieldOption('edit-pull-replication-settings', 'published');
    $page->findButton(t('Save'))->click();
    $session->getPage()->hasContent("'Target (target)");

    $live = $this->getOneEntityByLabel('workspace', 'Live');
    $target = $this->getOneWorkspaceByLabel('Target');
    $source_pointer = $this->getPointerToWorkspace($live);
    $target_pointer = $this->getPointerToWorkspace($target);

    // Derive a replication task from the target Workspace.
    $task = $this->replicatorManager->getTask($target, 'pull_replication_settings');

    // Replicate from Live to Target.
    $this->replicatorManager->replicate($source_pointer, $target_pointer, $task);
    \Drupal::service('cron')->run();

    // Verify the correct nodes were replicated.
    $this->switchToWorkspace($target);
    $this->assertTrue($this->isLabelInContentOverview('Published node'));
    $this->assertFalse($this->isLabelInContentOverview('Unpublished node'));
  }

  /**
   * Verify push replication settings using the published filter as an example.
   */
  public function testPushReplicationSettings() {
    $permissions = [
      'create_workspace',
      'edit_own_workspace',
      'view_own_workspace',
      'create test content',
      'access content overview',
      'administer content types',
      'administer nodes',
      'access content overview',
      'access replication settings fields',
    ];

    $this->createNodeType('Test', 'test');
    $this->setupWorkspaceSwitcherBlock();
    $this->drupalPlaceBlock('local_actions_block', ['id' => 'actions_block']);
    $test_user = $this->drupalCreateUser($permissions);
    $this->drupalLogin($test_user);
    $session = $this->getSession();

    // Create a target workspace with replication settings.
    $this->drupalGet('/admin/structure/workspace/add');
    $session = $this->getSession();
    $this->assertSession()->statusCodeEquals(200);
    $page = $session->getPage();
    $page->fillField('label', 'Target');
    $page->fillField('machine_name', 'target');
    $page->selectFieldOption('upstream', '1');
    $page->selectFieldOption('edit-push-replication-settings', 'published');
    $page->findButton(t('Save'))->click();
    $session->getPage()->hasContent("'Target (target)");

    $live = $this->getOneEntityByLabel('workspace', 'Live');
    $target = $this->getOneWorkspaceByLabel('Target');
    $source_pointer = $this->getPointerToWorkspace($live);
    $target_pointer = $this->getPointerToWorkspace($target);

    // Switch to the target workspace.
    $this->switchToWorkspace($target);

    // Create a published node.
    $this->drupalGet('/node/add/test');
    // For Drupal 8.4.x.
    if ($this->xpath('//input[@id="edit-status-value"]')) {
      $this->drupalPostForm(NULL, [
        'title[0][value]' => 'Published node',
      ], t('Save'));
    }
    // For Drupal 8.3.x.
    else {
      $this->drupalPostForm(NULL, [
        'title[0][value]' => 'Published node',
      ], t('Save and publish'));
    }
    $page = $session->getPage();
    $page->hasContent('Published node has been created');
    $this->assertTrue($this->isLabelInContentOverview('Published node'));

    // Create an unpublished node.
    $this->drupalGet('/node/add/test');
    // For Drupal 8.4.x.
    if ($this->xpath('//input[@id="edit-status-value"]')) {
      $this->drupalPostForm(NULL, [
        'status[value]' => FALSE,
        'title[0][value]' => 'Unpublished node',
      ], t('Save'));
    }
    // For Drupal 8.3.x.
    else {
      $this->drupalPostForm(NULL, [
        'title[0][value]' => 'Unpublished node',
      ], t('Save as unpublished'));
    }
    $page = $session->getPage();
    $page->hasContent('Unpublished node has been created');
    $this->assertTrue($this->isLabelInContentOverview('Unpublished node'));

    // Derive a replication task from the target Workspace.
    $task = $this->replicatorManager->getTask($target, 'push_replication_settings');

    // Replicate from Target to Live.
    $this->replicatorManager->replicate($target_pointer, $source_pointer, $task);
    \Drupal::service('cron')->run();

    // Verify the correct nodes were replicated.
    $this->switchToWorkspace($live);
    $this->assertTrue($this->isLabelInContentOverview('Published node'));
    $this->assertFalse($this->isLabelInContentOverview('Unpublished node'));
  }

}
