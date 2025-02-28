<?php

namespace Drupal\Tests\page_manager_ui\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests static context for pages.
 *
 * @group page_manager
 */
class StaticContextTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   *
   * @todo Remove dependency on the UI module or move to the UI module tests,
   *   in https://www.drupal.org/node/2659638.
   */
  protected static $modules = ['page_manager', 'page_manager_ui', 'node'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);
    $this->drupalLogin($this->drupalCreateUser(['administer pages', 'create article content']));

    $this->drupalPlaceBlock('page_title_block');
  }

  /**
   * Tests that a node bundle condition controls the node view page.
   */
  public function testStaticContext() {
    // Create a node, and check its page.
    $node = $this->drupalCreateNode(['type' => 'article']);
    $node2 = $this->drupalCreateNode(['type' => 'article']);
    $this->drupalGet('node/' . $node->id());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains($node->label());
    $this->assertSession()->titleEquals($node->label() . ' | Drupal');

    // Create a new page entity.
    $edit_page = [
      'label' => 'Static node context',
      'id' => 'static_node_context',
      'path' => 'static-context',
      'variant_plugin_id' => 'block_display',
      'wizard_options[contexts]' => TRUE,
    ];
    $this->drupalGet('admin/structure/page_manager/add');
    $this->submitForm($edit_page, 'Next');

    // Add a static context for each node to the page variant.
    $contexts = [
      [
        'title' => 'Static Node',
        'machine_name' => 'static_node',
        'description' => 'Static node 1',
        'node' => $node,
      ],
      [
        'title' => 'Static Node 2',
        'machine_name' => 'static_node_2',
        'description' => 'Static node 2',
        'node' => $node2,
      ],
    ];
    foreach ($contexts as $context) {
      $edit = [
        'context' => 'entity:node',
      ];
      $this->submitForm($edit, 'Add new context');
      $edit = [
        'label' => $context['title'],
        'machine_name' => $context['machine_name'],
        'description' => $context['description'],
        'context_value' => $context['node']->getTitle() . ' (' . $context['node']->id() . ')',
      ];
      $this->submitForm($edit, 'Save');
      $this->assertSession()->pageTextContains($context['title']);
    }
    $this->submitForm([], 'Next');

    // Add a new variant.
    $variant_edit = [
      'variant_settings[page_title]' => 'Static context test page',
    ];
    $this->submitForm($variant_edit, 'Next');

    // Add a block that renders the node from the first static context.
    $this->clickLink('Add new block');
    $this->clickLink('Entity view (Content)');
    $edit = [
      'settings[label]' => 'Static node view',
      'settings[label_display]' => 1,
      'settings[view_mode]' => 'default',
      'region' => 'top',
    ];
    $this->submitForm($edit, 'Add block');
    $this->assertSession()->pageTextContains($edit['settings[label]']);

    // Add a block that renders the node from the second static context.
    $this->clickLink('Add new block');
    $this->clickLink('Entity view (Content)');
    $edit = [
      'settings[label]' => 'Static node 2 view',
      'settings[label_display]' => 1,
      'settings[view_mode]' => 'default',
      'region' => 'bottom',
      'context_mapping[entity]' => $contexts[1]['machine_name'],
    ];
    $this->submitForm($edit, 'Add block');
    $this->assertSession()->pageTextContains($edit['settings[label]']);
    $this->submitForm([], 'Finish');

    // Open the page and verify that the node from the static context is there.
    $this->drupalGet($edit_page['path']);
    $this->assertSession()->pageTextContains($node->label());
    $this->assertSession()->pageTextContains($node->get('body')->getValue()[0]['value']);
    $this->assertSession()->pageTextContains($node2->label());
    $this->assertSession()->pageTextContains($node2->get('body')->getValue()[0]['value']);

    // Change the second static context to the first node.
    $this->drupalGet('admin/structure/page_manager/manage/static_node_context/page_variant__static_node_context-block_display-0__contexts');
    $this->clickLink('Edit', 1);
    $edit = [
      'label' => 'Static Node 2 edited',
      'context_value' => $node->getTitle(),
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->pageTextContains("Static Node 2 edited");
    $this->submitForm([], 'Update and save');

    // Open the page and verify that the node from the static context is there.
    $this->drupalGet($edit_page['path']);
    $this->assertSession()->pageTextContains($node->label());
    $this->assertSession()->pageTextContains($node->get('body')->getValue()[0]['value']);
    // Also make sure the second node is NOT there.
    $this->assertSession()->pageTextNotContains($node2->label());
    $this->assertSession()->pageTextNotContains($node2->get('body')->getValue()[0]['value']);

    // Change the first static context to the second node.
    $this->drupalGet('admin/structure/page_manager/manage/static_node_context/page_variant__static_node_context-block_display-0__contexts');
    $this->clickLink('Edit');
    $edit = [
      'label' => 'Static Node edited',
      'context_value' => $node2->getTitle(),
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->pageTextContains("Static Node 2 edited");

    // Remove the second static context view block from the variant.
    $this->drupalGet('admin/structure/page_manager/manage/static_node_context/page_variant__static_node_context-block_display-0__content');
    $this->clickLink('Delete', 1);
    $this->submitForm([], t('Delete'));
    $this->submitForm([], 'Update and save');

    // Make sure only the second static context's node is rendered on the page.
    $this->drupalGet($edit_page['path']);
    $this->assertSession()->pageTextNotContains($node->label());
    $this->assertSession()->pageTextNotContains($node->get('body')->getValue()[0]['value']);
    $this->assertSession()->pageTextContains($node2->label());
    $this->assertSession()->pageTextContains($node2->get('body')->getValue()[0]['value']);

    // Delete a static context and verify that it was deleted.
    $this->drupalGet('admin/structure/page_manager/manage/static_node_context/page_variant__static_node_context-block_display-0__contexts');
    $this->clickLink('Delete');
    $this->submitForm([], t('Delete'));
    $this->assertSession()->pageTextContains("The static context Static Node edited has been removed.");
    // Reload the page to clear the message.
    $this->drupalGet($this->getUrl());
    $this->assertSession()->pageTextNotContains($edit['label']);

    // Test contexts in a new variant.
    $this->drupalGet('admin/structure/page_manager/manage/static_node_context/general');
    $this->clickLink('Add variant');
    $edit = [
      'label' => 'Variant two',
      'variant_plugin_id' => 'block_display',
      'wizard_options[contexts]' => TRUE,
    ];
    $this->submitForm($edit, 'Next');
    foreach ($contexts as $context) {
      $edit = [
        'context' => 'entity:node',
      ];
      $this->submitForm($edit, 'Add new context');
      $edit = [
        'label' => $context['title'],
        'machine_name' => $context['machine_name'],
        'description' => $context['description'],
        'context_value' => $context['node']->getTitle() . ' (' . $context['node']->id() . ')',
      ];
      $this->submitForm($edit, 'Save');
      $this->assertSession()->pageTextContains($context['title']);
    }
    $this->submitForm([], 'Next');

    // Configure the new variant.
    $variant_edit = [
      'variant_settings[page_title]' => 'Variant two static context test',
    ];
    $this->submitForm($variant_edit, 'Next');

    // Add a block that renders the node from the first static context.
    $this->clickLink('Add new block');
    $this->clickLink('Entity view (Content)');
    $edit = [
      'settings[label]' => 'Static node view',
      'settings[label_display]' => 1,
      'settings[view_mode]' => 'default',
      'region' => 'top',
    ];
    $this->submitForm($edit, 'Add block');
    $this->assertSession()->pageTextContains($edit['settings[label]']);

    // Add a block that renders the node from the second static context.
    $this->clickLink('Add new block');
    $this->clickLink('Entity view (Content)');
    $edit = [
      'settings[label]' => 'Static node 2 view',
      'settings[label_display]' => 1,
      'settings[view_mode]' => 'default',
      'region' => 'bottom',
      'context_mapping[entity]' => $contexts[1]['machine_name'],
    ];
    $this->submitForm($edit, 'Add block');
    $this->assertSession()->pageTextContains($edit['settings[label]']);
    $this->submitForm([], 'Finish');
  }

}
