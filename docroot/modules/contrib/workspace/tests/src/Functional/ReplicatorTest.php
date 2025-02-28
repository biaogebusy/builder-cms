<?php

namespace Drupal\Tests\workspace\Functional;

use Drupal\Component\Utility\Crypt;
use Drupal\multiversion\Entity\MenuLinkContent;
use Drupal\Tests\block\Traits\BlockCreationTrait;
use Drupal\replication\ReplicationTask\ReplicationTask;
use Drupal\Tests\BrowserTestBase;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\workspace\Entity\Replication;
use Drupal\workspace\Entity\WorkspacePointer;
use Drupal\workspace\Event\ReplicationEvent;
use Drupal\workspace\Event\ReplicationEvents;
use Prophecy\Argument;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Test the workspace entity.
 *
 * @group workspace
 */
class ReplicatorTest extends BrowserTestBase {

  use WorkspaceTestUtilities;
  use BlockCreationTrait {
    placeBlock as drupalPlaceBlock;
  }

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'system',
    'node',
    'user',
    'block',
    'workspace',
    'multiversion',
    'taxonomy',
    'entity_reference',
    'field',
    'field_ui',
    'menu_link_content',
    'menu_ui',
  ];

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   *
   */
  public function setUp() {
    parent::setUp();
    $permissions = [
      'create_workspace',
      'edit_own_workspace',
      'view_own_workspace',
      'view_any_workspace',
      'create test content',
      'access administration pages',
      'administer taxonomy',
      'administer menu',
      'access content overview',
      'administer content types',
      'administer node display',
      'administer node fields',
      'administer node form display',
    ];

    $this->createNodeType('Test', 'test');
    $vocabulary = Vocabulary::create(['name' => 'Tags', 'vid' => 'tags', 'hierarchy' => 0]);
    $vocabulary->save();

    $test_user = $this->drupalCreateUser($permissions);
    $this->drupalLogin($test_user);
    $this->setupWorkspaceSwitcherBlock();

    $this->entityTypeManager = \Drupal::entityTypeManager();
  }

  /**
   * Test replication.
   */
  public function testReplication() {
    $live = $this->getOneEntityByLabel('workspace', 'Live');
    $this->drupalGet('/node/add/test');
    $session = $this->getSession();
    $this->assertEquals(200, $session->getStatusCode());
    $page = $session->getPage();
    $page->fillField('Title', 'Test node');
    $page->fillField('Provide a menu link', 1);
    $page->fillField('Menu link title', 'Test node link');
    $page->findButton(t('Save'))->click();
    $page = $session->getPage();
    $this->assertTrue($page->hasContent("Test node has been created"));

    $test_node_live = $this->getOneEntityByLabel('node', 'Test node');
    $this->assertEquals($live->id(), $test_node_live->get('workspace')->entity->id());
    $this->drupalGet('/admin/content');
    $session = $this->getSession();
    $this->assertEquals(200, $session->getStatusCode());
    $page = $session->getPage();
    $this->assertTrue($page->hasContent($test_node_live->label()));

    $menu_link_live = $this->getOneEntityByLabel('menu_link_content', 'Test node link');
    $this->assertEquals($live->id(), $menu_link_live->get('workspace')->entity->id());
    $this->drupalGet('/admin/structure/menu/manage/main');
    $session = $this->getSession();
    $this->assertEquals(200, $session->getStatusCode());
    $page = $session->getPage();
    $this->assertTrue($page->hasContent('Test node link'));

    // Imitate an event subscriber to check that events are dispatched.
    // As we are triggering "Deploy" action it will automatically
    // create "Update" replication so events should be dispatched twice.
    $event_dispatcher = $this->prophesize(EventDispatcherInterface::class);
    $event_dispatcher
      ->dispatch(ReplicationEvents::QUEUED_REPLICATION, Argument::type(ReplicationEvent::class))
      ->shouldBeCalledTimes(2);
    $event_dispatcher
      ->dispatch(ReplicationEvents::PRE_REPLICATION, Argument::type(ReplicationEvent::class))
      ->shouldBeCalledTimes(2);
    $event_dispatcher
      ->dispatch(ReplicationEvents::POST_REPLICATION, Argument::type(ReplicationEvent::class))
      ->shouldBeCalledTimes(2);
    $container = \Drupal::getContainer();
    $container->set('event_dispatcher', $event_dispatcher->reveal());
    \Drupal::setContainer($container);

    $target = $this->createWorkspaceThroughUI('Target', 'target');
    /** @var \Drupal\workspace\ReplicatorManager $rm */
    $rm = \Drupal::service('workspace.replicator_manager');
    $task = new ReplicationTask();
    $rm->replicate($this->getPointerToWorkspace($live), $this->getPointerToWorkspace($target), $task);
    \Drupal::service('cron')->run();

    $replication_log_id = $this->getPointerToWorkspace($live)->generateReplicationId($this->getPointerToWorkspace($target), $task);
    $replication_logs = $this->entityTypeManager->getStorage('replication_log')->getQuery()->allRevisions()->condition('uuid', $replication_log_id)->execute();
    $this->assertEquals(2, count($replication_logs));
    $i = 1;
    foreach ($replication_logs as $revision_id => $id) {
      $this->assertEquals(2, $id);
      $this->assertEquals($i * 2, $revision_id);
      /** @var \Drupal\replication\Entity\ReplicationLogInterface $revision */
      $revision = $this->entityTypeManager->getStorage('replication_log')->loadRevision($revision_id);
      $this->assertTrue($revision->ok->value);
      if ($i == 1) {
        $this->assertNull($revision->getHistory()[0]['docs_written']);
      }
      else {
        $this->assertEquals(2, $revision->getHistory()[0]['docs_written']);
      }
      $i++;
    }

    $this->switchToWorkspace($target);

    $test_node_target = $this->getOneEntityByLabel('node', 'Test node');
    $this->assertEquals($target->id(), $test_node_target->get('workspace')->entity->id());
    $this->drupalGet('/admin/content');
    $session = $this->getSession();
    $this->assertEquals(200, $session->getStatusCode());
    $page = $session->getPage();
    $this->assertTrue($page->hasContent($test_node_target->label()));

    $menu_link_target = $this->getOneEntityByLabel('menu_link_content', 'Test node link');
    $this->assertEquals($target->id(), $menu_link_target->get('workspace')->entity->id());
    $this->drupalGet('/admin/structure/menu/manage/main');
    $session = $this->getSession();
    $this->assertEquals(200, $session->getStatusCode());
    $page = $session->getPage();
    $this->assertTrue($page->hasContent('Test node link'));
  }

  /**
   * Test selective content replication.
   */
  public function testSelectiveContentReplication() {
    $live = $this->getOneEntityByLabel('workspace', 'Live');

    // Create 4 nodes and 4 menu links on Live workspace.

    $node1 = $this->drupalCreateNode(['type' => 'test', 'title' => 'Node 1']);
    $node2 = $this->drupalCreateNode(['type' => 'test', 'title' => 'Node 2']);
    $node3 = $this->drupalCreateNode(['type' => 'test', 'title' => 'Node 3']);
    $node4 = $this->drupalCreateNode(['type' => 'test', 'title' => 'Node 4']);

    $this->drupalGet('/admin/content');
    $session = $this->getSession();
    $this->assertEquals(200, $session->getStatusCode());
    $page = $session->getPage();
    $this->assertTrue($page->hasContent($node1->label()));
    $this->assertTrue($page->hasContent($node2->label()));
    $this->assertTrue($page->hasContent($node3->label()));
    $this->assertTrue($page->hasContent($node4->label()));

    $menu_link_content1 = MenuLinkContent::create([
      'link' => ['uri' => 'entity:node/' . $node1->id()],
      'menu_name' => 'main',
      'title' => 'Test link 1',

    ]);
    $menu_link_content1->save();
    $menu_link_content2 = MenuLinkContent::create([
      'link' => ['uri' => 'entity:node/' . $node2->id()],
      'menu_name' => 'main',
      'title' => 'Test link 2'
    ]);
    $menu_link_content2->save();
    $menu_link_content3 = MenuLinkContent::create([
      'link' => ['uri' => 'entity:node/' . $node3->id()],
      'menu_name' => 'main',
      'title' => 'Test link 3'
    ]);
    $menu_link_content3->save();
    $menu_link_content4 = MenuLinkContent::create([
      'link' => ['uri' => 'entity:node/' . $node4->id()],
      'menu_name' => 'main',
      'title' => 'Test link 4'
    ]);
    $menu_link_content4->save();

    $this->drupalGet('/admin/structure/menu/manage/main');
    $session = $this->getSession();
    $this->assertEquals(200, $session->getStatusCode());
    $page = $session->getPage();
    $this->assertTrue($page->hasContent($menu_link_content1->label()));
    $this->assertTrue($page->hasContent($menu_link_content2->label()));
    $this->assertTrue($page->hasContent($menu_link_content3->label()));
    $this->assertTrue($page->hasContent($menu_link_content4->label()));

    $target = $this->createWorkspaceThroughUI('Target', 'target');

    // Set the Target workspace as upstream for Live.
    $live->set('upstream', $target->id());
    $live->save();

    // Make sure we have correct changes on Changes page.
    $this->drupalGet("admin/structure/workspace/{$live->id()}/changes");
    $session = $this->getSession();
    $this->assertEquals(200, $session->getStatusCode());
    $page = $session->getPage();
    $this->assertTrue($page->hasContent($node1->label()));
    $this->assertTrue($page->hasContent($node2->label()));
    $this->assertTrue($page->hasContent($node3->label()));
    $this->assertTrue($page->hasContent($node4->label()));
    $this->assertTrue($page->hasContent($menu_link_content1->label()));
    $this->assertTrue($page->hasContent($menu_link_content2->label()));
    $this->assertTrue($page->hasContent($menu_link_content3->label()));
    $this->assertTrue($page->hasContent($menu_link_content4->label()));

    // Switch to Target workspace and make sure 4 nodes and 4 menu links
    // from Live workspace don't exist there yet.
    $this->switchToWorkspace($target);

    $this->drupalGet('/admin/content');
    $session = $this->getSession();
    $this->assertEquals(200, $session->getStatusCode());
    $page = $session->getPage();
    $this->assertFalse($page->hasContent($node1->label()));
    $this->assertFalse($page->hasContent($node2->label()));
    $this->assertFalse($page->hasContent($node3->label()));
    $this->assertFalse($page->hasContent($node4->label()));

    $this->drupalGet('/admin/structure/menu/manage/main');
    $session = $this->getSession();
    $this->assertEquals(200, $session->getStatusCode());
    $page = $session->getPage();
    $this->assertFalse($page->hasContent($menu_link_content1->label()));
    $this->assertFalse($page->hasContent($menu_link_content2->label()));
    $this->assertFalse($page->hasContent($menu_link_content3->label()));
    $this->assertFalse($page->hasContent($menu_link_content4->label()));

    // Switch back to Live.
    $this->switchToWorkspace($live);

    /** @var \Drupal\workspace\ReplicatorManager $rm */
    $rm = \Drupal::service('workspace.replicator_manager');
    $task = new ReplicationTask();
    // Select to be deployed only Node 1 and Test link 1 on next replication.
    $task->setDocIds([$node1->uuid(), $menu_link_content1->uuid()]);
    $rm->replicate(WorkspacePointer::loadFromWorkspace($live), WorkspacePointer::loadFromWorkspace($target), $task);
    \Drupal::service('cron')->run();

    // Make sure we have correct changes on Changes page.
    $this->drupalGet("admin/structure/workspace/{$live->id()}/changes");
    $session = $this->getSession();
    $this->assertEquals(200, $session->getStatusCode());
    $page = $session->getPage();
    $this->assertFalse($page->hasContent($node1->label()));
    $this->assertTrue($page->hasContent($node2->label()));
    $this->assertTrue($page->hasContent($node3->label()));
    $this->assertTrue($page->hasContent($node4->label()));
    $this->assertFalse($page->hasContent($menu_link_content1->label()));
    $this->assertTrue($page->hasContent($menu_link_content2->label()));
    $this->assertTrue($page->hasContent($menu_link_content3->label()));
    $this->assertTrue($page->hasContent($menu_link_content4->label()));

    // Switch back to Target and check if Node 1 and Menu link 1 have been
    // replicated.
    $this->switchToWorkspace($target);

    $this->drupalGet('/admin/content');
    $session = $this->getSession();
    $this->assertEquals(200, $session->getStatusCode());
    $page = $session->getPage();
    $this->assertTrue($page->hasContent($node1->label()));
    $this->assertFalse($page->hasContent($node2->label()));
    $this->assertFalse($page->hasContent($node3->label()));
    $this->assertFalse($page->hasContent($node4->label()));

    $this->drupalGet('/admin/structure/menu/manage/main');
    $session = $this->getSession();
    $this->assertEquals(200, $session->getStatusCode());
    $page = $session->getPage();
    $this->assertTrue($page->hasContent($menu_link_content1->label()));
    $this->assertFalse($page->hasContent($menu_link_content2->label()));
    $this->assertFalse($page->hasContent($menu_link_content3->label()));
    $this->assertFalse($page->hasContent($menu_link_content4->label()));

    // Switch back to Live.
    $this->switchToWorkspace($live);

    $task = new ReplicationTask();
    // Select to be deployed only Node 2, Node 3, Test link 2 and Test link 3 on
    // next replication.
    $task->setDocIds([$node2->uuid(), $node3->uuid(), $menu_link_content2->uuid(), $menu_link_content3->uuid()]);
    $rm->replicate(WorkspacePointer::loadFromWorkspace($live), WorkspacePointer::loadFromWorkspace($target), $task);
    \Drupal::service('cron')->run();

    // Make sure we have correct changes on Changes page.
    $this->drupalGet("admin/structure/workspace/{$live->id()}/changes");
    $session = $this->getSession();
    $this->assertEquals(200, $session->getStatusCode());
    $page = $session->getPage();
    $this->assertFalse($page->hasContent($node1->label()));
    $this->assertFalse($page->hasContent($node2->label()));
    $this->assertFalse($page->hasContent($node3->label()));
    $this->assertTrue($page->hasContent($node4->label()));
    $this->assertFalse($page->hasContent($menu_link_content1->label()));
    $this->assertFalse($page->hasContent($menu_link_content2->label()));
    $this->assertFalse($page->hasContent($menu_link_content3->label()));
    $this->assertTrue($page->hasContent($menu_link_content4->label()));

    // Switch back to Target and check if Node 1 and Menu link 1 have been
    // replicated.
    $this->switchToWorkspace($target);

    $this->drupalGet('/admin/content');
    $session = $this->getSession();
    $this->assertEquals(200, $session->getStatusCode());
    $page = $session->getPage();
    $this->assertTrue($page->hasContent($node1->label()));
    $this->assertTrue($page->hasContent($node2->label()));
    $this->assertTrue($page->hasContent($node3->label()));
    $this->assertFalse($page->hasContent($node4->label()));

    $this->drupalGet('/admin/structure/menu/manage/main');
    $session = $this->getSession();
    $this->assertEquals(200, $session->getStatusCode());
    $page = $session->getPage();
    $this->assertTrue($page->hasContent($menu_link_content1->label()));
    $this->assertTrue($page->hasContent($menu_link_content2->label()));
    $this->assertTrue($page->hasContent($menu_link_content3->label()));
    $this->assertFalse($page->hasContent($menu_link_content4->label()));

    // Switch back to Live.
    $this->switchToWorkspace($live);

    $task = new ReplicationTask();
    // Do normal replication, without selecting content, this will deploy
    // remaining Node 4 and Menu link 4.
    $rm->replicate(WorkspacePointer::loadFromWorkspace($live), WorkspacePointer::loadFromWorkspace($target), $task);
    \Drupal::service('cron')->run();

    // Make sure we have correct changes on Changes page.
    $this->drupalGet("admin/structure/workspace/{$live->id()}/changes");
    $session = $this->getSession();
    $this->assertEquals(200, $session->getStatusCode());
    $page = $session->getPage();
    $this->assertFalse($page->hasContent($node1->label()));
    $this->assertFalse($page->hasContent($node2->label()));
    $this->assertFalse($page->hasContent($node3->label()));
    $this->assertFalse($page->hasContent($node4->label()));
    $this->assertFalse($page->hasContent($menu_link_content1->label()));
    $this->assertFalse($page->hasContent($menu_link_content2->label()));
    $this->assertFalse($page->hasContent($menu_link_content3->label()));
    $this->assertFalse($page->hasContent($menu_link_content4->label()));

    // Switch back to Target and check if Node 1 and Menu link 1 have been
    // replicated.
    $this->switchToWorkspace($target);

    $this->drupalGet('/admin/content');
    $session = $this->getSession();
    $this->assertEquals(200, $session->getStatusCode());
    $page = $session->getPage();
    $this->assertTrue($page->hasContent($node1->label()));
    $this->assertTrue($page->hasContent($node2->label()));
    $this->assertTrue($page->hasContent($node3->label()));
    $this->assertTrue($page->hasContent($node4->label()));

    $this->drupalGet('/admin/structure/menu/manage/main');
    $session = $this->getSession();
    $this->assertEquals(200, $session->getStatusCode());
    $page = $session->getPage();
    $this->assertTrue($page->hasContent($menu_link_content1->label()));
    $this->assertTrue($page->hasContent($menu_link_content2->label()));
    $this->assertTrue($page->hasContent($menu_link_content3->label()));
    $this->assertTrue($page->hasContent($menu_link_content4->label()));
  }


  function testReplicationBlocker() {
    $test_user = $this->drupalCreateUser(['access administration pages']);
    $this->drupalLogin($test_user);

    $this->drupalGet('admin/config/replication/settings');
    // Ensure Unblock replication button for
    // Drupal\workspace\Form\UnblockReplicationForm is disabled.
    $submit_is_disabled = $this->cssSelect('form.unblock-replication-form input[type="submit"]:disabled');
    $this->assertTrue(count($submit_is_disabled) === 1, 'The Unblock replication button is disabled.');

    $state = \Drupal::state();
    $state->set('workspace.last_replication_failed', TRUE);
    $this->drupalGet('admin/config/replication/settings');
    // Now the Unblock replication button for
    // Drupal\workspace\Form\UnblockReplicationForm should be enabled.
    $submit_is_disabled = $this->cssSelect('form.unblock-replication-form input[type="submit"]:disabled');
    $this->assertTrue(count($submit_is_disabled) === 0, 'The Unblock replication button is disabled.');
    $this->assertSession()->buttonExists('Unblock replication');
    $this->drupalPostForm(NULL, [], 'Unblock replication');
    $session = $this->getSession();
    $this->assertEquals(200, $session->getStatusCode());
    $this->assertFalse($state->get('workspace.last_replication_failed'));
    // Ensure Unblock replication button for
    // Drupal\workspace\Form\UnblockReplicationForm is disabled again.
    $submit_is_disabled = $this->cssSelect('form.unblock-replication-form input[type="submit"]:disabled');
    $this->assertTrue(count($submit_is_disabled) === 1, 'The Unblock replication button is disabled.');
  }

}
