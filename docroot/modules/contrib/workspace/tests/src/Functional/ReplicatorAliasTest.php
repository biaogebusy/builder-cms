<?php

namespace Drupal\Tests\workspace\Functional;

use Drupal\pathauto\Tests\PathautoTestHelperTrait;
use Drupal\Tests\block\Traits\BlockCreationTrait;
use Drupal\replication\ReplicationTask\ReplicationTask;
use Drupal\Tests\BrowserTestBase;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Test the workspace entity with EventSubscriber alias change.
 *
 * @group workspace
 */
class ReplicatorAliasTest extends BrowserTestBase {

  use WorkspaceTestUtilities;
  use BlockCreationTrait {
    placeBlock as drupalPlaceBlock;
  }
  use PathautoTestHelperTrait;

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
    'pathauto',
  ];

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The AliasType manager.
   *
   * @var \Drupal\Core\Path\AliasManager
   */
  protected $aliasManager;

  /**
   * The Pathauto generator.
   *
   * @var \Drupal\pathauto\PathautoGeneratorInterface
   */
  protected $pathAutoGenerator;

  /**
   *
   */
  public function setUp() {
    parent::setUp();
    $permissions = [
      'create_workspace',
      'edit_own_workspace',
      'view_own_workspace',
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
    $this->aliasManager = \Drupal::service('path.alias_manager');
    $this->pathAutoGenerator = \Drupal::service('pathauto.generator');
  }

  /**
   * Verifies that a user can edit anything in a workspace with a specific perm.
   */
  public function testReplicationAlias() {
    $this->createPattern('node', '[node:title]');

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
    $page->hasContent("Test node has been created");

    $test_node_live = $this->getOneEntityByLabel('node', 'Test node');

    $this->pathAutoGenerator->createEntityAlias($test_node_live, 'insert');
    $test_node_live_alias = $this->aliasManager->getAliasByPath('/node/' . $test_node_live->id());

    $this->assertEquals($live->id(), $test_node_live->get('workspace')->entity->id());
    $this->drupalGet('/admin/content');
    $session = $this->getSession();
    $this->assertEquals(200, $session->getStatusCode());
    $page = $session->getPage();
    $page->hasContent($test_node_live->label());

    $menu_link_live = $this->getOneEntityByLabel('menu_link_content', 'Test node link');
    $this->assertEquals($live->id(), $menu_link_live->get('workspace')->entity->id());
    $this->drupalGet('/admin/structure/menu/manage/main');
    $session = $this->getSession();
    $this->assertEquals(200, $session->getStatusCode());
    $page = $session->getPage();
    $page->hasContent('Test node link');

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
    $page->hasContent($test_node_target->label());

    $menu_link_target = $this->getOneEntityByLabel('menu_link_content', 'Test node link');
    $this->assertEquals($target->id(), $menu_link_target->get('workspace')->entity->id());
    $this->drupalGet('/admin/structure/menu/manage/main');
    $session = $this->getSession();
    $this->assertEquals(200, $session->getStatusCode());
    $page = $session->getPage();
    $page->hasContent('Test node link');

    $this->assertEntityAlias($test_node_target, $test_node_live_alias);
  }

}
