<?php

namespace Drupal\Tests\workspace\Functional;

use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Tests\BrowserTestBase;

/**
 * Test the workspace entity.
 *
 * @group workspace
 */
class EntityTypeAlterTest extends BrowserTestBase {
  use WorkspaceTestUtilities;

  public static $modules = [
    'node',
    'user',
    'block',
    'block_content',
    'workspace',
    'multiversion',
  ];

  public function testEntityTypeAlter() {
    $entity_types = \Drupal::service('entity_type.manager')->getDefinitions();

    /** @var EntityTypeInterface $workspace_type */
    $workspace_type = $entity_types['workspace_type'];
    $this->assertTrue($workspace_type->getHandlerClass('list_builder') !== null);
    $this->assertTrue($workspace_type->getHandlerClass('route_provider', 'html') !== null);
    $this->assertTrue($workspace_type->getHandlerClass('form', 'default') !== null);
    $this->assertTrue($workspace_type->getHandlerClass('form', 'add') !== null);
    $this->assertTrue($workspace_type->getHandlerClass('form', 'edit') !== null);
    $this->assertTrue($workspace_type->getHandlerClass('form', 'delete') !== null);
    $this->assertTrue($workspace_type->getLinkTemplate('collection') !== null);
    $this->assertTrue($workspace_type->getLinkTemplate('edit-form') !== null);
    $this->assertTrue($workspace_type->getLinkTemplate('delete-form') !== null);

    /** @var EntityTypeInterface $workspace */
    $workspace = $entity_types['workspace'];
    $this->assertTrue($workspace->getHandlerClass('list_builder') !== null);
    $this->assertTrue($workspace->getHandlerClass('route_provider', 'html') !== null);
    $this->assertTrue($workspace->getHandlerClass('form', 'default') !== null);
    $this->assertTrue($workspace->getHandlerClass('form', 'add') !== null);
    $this->assertTrue($workspace->getHandlerClass('form', 'edit') !== null);
    $this->assertTrue($workspace->getLinkTemplate('collection') !== null);
    $this->assertTrue($workspace->getLinkTemplate('edit-form') !== null);
    $this->assertTrue($workspace->getLinkTemplate('activate-form') !== null);
    $this->assertTrue($workspace->get('field_ui_base_route') !== null);

    foreach ($entity_types as $entity_type) {
      if (\Drupal::service('multiversion.manager')->isSupportedEntityType($entity_type)) {
        if ($entity_type->hasViewBuilderClass() && $entity_type->hasLinkTemplate('canonical')) {
          $this->assertTrue($entity_type->getLinkTemplate('version-tree') !== null);
          $this->assertTrue($entity_type->getLinkTemplate('revision') !== null);
        }
      }
    }
  }

  public function testTree() {
    $permissions = [
      'create_workspace',
      'edit_own_workspace',
      'view_own_workspace',
      'create test content',
      'view_revision_trees'
    ];
    $this->createNodeType('Test', 'test');
    $clapton = $this->drupalCreateUser($permissions);
    $this->drupalLogin($clapton);

    $layla_node = $this->createNodeThroughUI('Layla', 'test');
    $this->drupalGet('/node/' . $layla_node->id() . '/tree');
    $session = $this->getSession();
    $this->assertEquals(200, $session->getStatusCode());
    $page = $session->getPage();
    $page->findLink($this->truncateRev($layla_node->_rev->value));
  }

  /**
   * Tests revision tree of block content.
   */
  public function testBlockTree() {
    $permissions = [
      'administer blocks',
      'view_revision_trees'
    ];
    $user = $this->drupalCreateUser($permissions);
    $this->drupalLogin($user);

    // Create new block type.
    $block_type = BlockContentType::create([
      'id' => 'basic',
      'label' => 'basic',
    ]);
    $block_type->save();

    // Create new block.
    $block_content = BlockContent::create([
      'info' => $this->randomString(),
      'type' => 'basic',
      'langcode' => 'en'
    ]);
   $block_content->save();

    // Check for tree elements.
    $this->drupalGet('/block/' . $block_content->id() . '/tree');
    $this->assertSession()->pageTextContains('Status: available');

    // Update block.
    $edit['info[0][value]'] = $this->randomMachineName(16);
    $this->drupalPostForm('block/' . $block_content->id(), $edit, t('Save'));

    $this->drupalGet('/block/' . $block_content->id() . '/tree');
    $this->assertSession()->pageTextContains('Status: available');
  }

  protected function truncateRev($rev) {
    list($i) = explode('-', $rev);
    $length = strlen($i) + 9;
    return Unicode::truncate($rev, $length, FALSE, TRUE);
  }

}
