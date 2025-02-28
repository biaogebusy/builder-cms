<?php

namespace Drupal\Tests\workspace\Functional;

use Drupal\multiversion\Entity\Workspace;
use Drupal\replication\ReplicationTask\ReplicationTask;
use Drupal\Tests\BrowserTestBase;
use Drupal\workspace\Entity\Replication;
use Drupal\workspace\Entity\WorkspacePointer;

/**
 * Verifies Replication settings page.
 *
 * That page is provided by Replication module, but Workspace alters it, it
 * adds two new forms: the button for replication unblocking and the button for
 * replication queue clearing.
 *
 * @group replication
 */
class ReplicationSettingsPageTest extends BrowserTestBase {

  use WorkspaceTestUtilities;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'multiversion',
    'user',
    'replication',
    'workspace',
    'node',
  ];

  /**
   * User that can access replication settings page.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->createNodeType('Test', 'test');
    $permissions = [
      'access administration pages',
      'create_workspace',
      'edit_own_workspace',
      'view_own_workspace',
      'view_any_workspace',
      'create test content',
    ];
    $this->user = $this->drupalCreateUser($permissions);
    $this->entityTypeManager = \Drupal::entityTypeManager();
  }

  /**
   * Test the forms.
   */
  public function testReplicationConfigurationForms() {
    $this->drupalLogin($this->user);
    $this->drupalGet('admin/config/replication/settings');
    $this->assertText("Unblock replication");
    // Ensure Unblock replication button for
    // Drupal\workspace\Form\UnblockReplicationForm is again.
    $submit_is_disabled = $this->cssSelect('form.unblock-replication-form input[type="submit"]:disabled');
    $this->assertTrue(count($submit_is_disabled) === 1, 'The Unblock replication button is disabled.');
    $this->assertText('Clear replication queue');
    $this->assertText('Replication settings');
    $this->assertText('Replication configuration');
    $this->assertFieldByName('mapping_type', 'uid_1');
    $this->assertFieldByName('uid', '');
    $this->assertFieldByName('changes_limit', 100);
    $this->assertFieldByName('bulk_docs_limit', 100);
    $this->assertFieldByName('replication_execution_limit', 1);
    $this->assertFieldByName('verbose_logging', FALSE);

    // Edit config and save.
    $edit = [
      'mapping_type' => 'uid',
      'uid' => $this->user->id(),
      'changes_limit' => 200,
      'bulk_docs_limit' => 200,
      'replication_execution_limit' => 4,
      'verbose_logging' => TRUE,
    ];
    $this->drupalPostForm(NULL, $edit, 'Save configuration');
    // Check field values after form save.
    $this->assertText('The configuration options have been saved.');
    $this->assertText('Replication settings');
    $this->assertText('Replication configuration');
    $this->assertFieldByName('mapping_type', 'uid');
    $this->assertFieldByName('uid', $this->user->id());
    $this->assertFieldByName('changes_limit', 200);
    $this->assertFieldByName('bulk_docs_limit', 200);
    $this->assertFieldByName('replication_execution_limit', 4);
    $this->assertFieldByName('verbose_logging', TRUE);

    \Drupal::state()->set('workspace.last_replication_failed', TRUE);
    $this->drupalGet('admin/config/replication/settings');
    // Unblock replication button should be enabled now.
    $submit_is_disabled = $this->cssSelect('form.unblock-replication-form input[type="submit"]:disabled');
    $this->assertTrue(count($submit_is_disabled) === 0, 'The Unblock replication button is disabled.');
    //Test unblocking.
    $this->drupalPostForm(NULL, [], 'Unblock replication');
    $this->assertText('Replications have been unblocked.');
    // Unblock replication button should be disabled.
    $submit_is_disabled = $this->cssSelect('form.unblock-replication-form input[type="submit"]:disabled');
    $this->assertTrue(count($submit_is_disabled) === 1, 'The Unblock replication button is disabled.');

    // Test Clear queue button.
    $this->drupalPostForm(NULL, [], 'Clear queue');
    $this->assertText('Are you sure you want to clear the replication queue?');
    $this->assertText('All replications will be marked as failed and removed from the cron queue, except those that are in progress. This action cannot be undone.');
    $this->drupalPostForm(NULL, [], 'Clear queue');
    $this->assertText('There were not any queued deployments in the replication queue.');
    $this->assertText("Unblock replication");

    // Test clearing the queue when there are queued replications.
    $earth = Workspace::create(['label' => 'Earth', 'machine_name' => 'earth', 'type' => 'basic']);
    $earth->save();
    $mars = Workspace::create(['label' => 'Mars', 'machine_name' => 'mars', 'type' => 'basic']);
    $mars->save();
    // Set the Mars workspace as upstream for Earth.
    $earth->set('upstream', $mars->id())->save();

    /** @var \Drupal\multiversion\Workspace\WorkspaceManagerInterface $workspace_manager */
    $workspace_manager = \Drupal::service('workspace.manager');

    // Switch to Earth.
    $workspace_manager->setActiveWorkspace($earth);
    // Create first entity on Earth.
    $this->drupalCreateNode(['type' => 'test', 'title' => 'Elon']);
    // Create the second entity on Earth.
    $this->drupalCreateNode(['type' => 'test', 'title' => 'Claire']);
    // Create a deployment from Earth to Mars.
    // We want Elon and Claire to colonize the Mars.
    /** @var \Drupal\workspace\ReplicatorManager $rm */
    $big_falcon = \Drupal::service('workspace.replicator_manager');
    $colonize_mars_task = new ReplicationTask();
    $big_falcon->replicate(WorkspacePointer::loadFromWorkspace($earth), WorkspacePointer::loadFromWorkspace($mars), $colonize_mars_task);

    // Now in the replication queue we should have two replications, one pull
    // replication from Mars to Earth and one push from Earth to Mars.
    $missions = $this->entityTypeManager
      ->getStorage('replication')
      ->loadMultiple();
    $this->assertTrue(count($missions) === 2);
    // Loop through them and check if the status is correct.
    /** @var Replication $mission */
    foreach ($missions as $mission) {
      // The status should be failed for this mission.
      $this->assertEquals(Replication::QUEUED, $mission->getReplicationStatus());
    }

    // Something went wrong and on Earth (or Mars) ¯\_(ツ)_/¯ and the user with
    // the right permissions decides to cancel the mission.
    $this->drupalGet('admin/config/replication/settings');
    $this->drupalPostForm(NULL, [], 'Clear queue');
    // The user is asked for confirmation.
    $this->assertText('Are you sure you want to clear the replication queue?');
    // Here is the confirmation.
    $this->drupalPostForm(NULL, [], 'Clear queue');
    // There should be also a message about successfully executing the operation.
    $this->assertText('All the queued deployments have been marked as failed and have been removed from the replication queue.');

    // Load again the missions.
    $missions = $this->entityTypeManager
      ->getStorage('replication')
      ->loadMultiple();
    $this->assertTrue(count($missions) === 2);

    // Loop through them and check if the status is correct.
    /** @var Replication $mission */
    foreach ($missions as $mission) {
      // The status should be failed for this mission.
      $this->assertEquals(Replication::FAILED, $mission->getReplicationStatus());
      // There should also be some info about this fail.
      $this->assertEquals('This deployment has been marked as failed manually, when clearing the replication queue.', $mission->getReplicationFailInfo());
    }

    // Run cron, it shouldn't execute any deployments.
    \Drupal::service('cron')->run();

    // Switch to Mars.
    $workspace_manager->setActiveWorkspace($mars);
    // Look there for Elon and Claire.
    $entities = $this->entityTypeManager
      ->getStorage('node')
      ->loadMultiple();
    // Nothing on Mars, not this time, maybe next time.
    $this->assertEmpty($entities);
  }

}
