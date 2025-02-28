<?php

namespace Drupal\Tests\layout_library\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;

/**
 * Tests the functionality of the layout_selection field.
 *
 * @group layout_library
 */
class LayoutSelectionTest extends BrowserTestBase {

  use ContentTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'field_ui', 'layout_library', 'options'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalCreateContentType(['type' => 'alpha']);
    $this->drupalCreateContentType(['type' => 'beta']);

    $this->drupalPlaceBlock('local_tasks_block');
    $this->drupalPlaceBlock('local_actions_block');
  }

  /**
   * Tests that reference-able layouts are filtered by target bundle.
   */
  public function testReferenceableLayoutsFilteredByTargetBundle() {
    $assert = $this->assertSession();

    $account = $this->drupalCreateUser([
      'configure any layout',
      'create alpha content',
      'create beta content',
      'administer node display',
    ]);
    $this->drupalLogin($account);

    $this->createLayoutForNodeType('alpha');
    $this->createLayoutForNodeType('beta');

    $this->enableLayoutBuilderForNodeType('alpha');
    $this->enableLayoutBuilderForNodeType('beta');

    $this->drupalGet('/node/add/alpha');
    $assert->optionExists('Layout', 'alpha');
    $assert->optionNotExists('Layout', 'beta');

    $this->drupalGet('/node/add/beta');
    $assert->optionNotExists('Layout', 'alpha');
    $assert->optionExists('Layout', 'beta');
  }

  /**
   * Tests that deleted layouts fall back to the default layout.
   */
  public function testDeletedLayoutFallback() {
    $page = $this->getSession()->getPage();
    $assert = $this->assertSession();

    $account = $this->drupalCreateUser([
      'administer node display',
      'configure any layout',
      'create alpha content',
      'edit own alpha content',
    ]);
    $this->drupalLogin($account);

    $this->createLayoutForNodeType('alpha');
    $this->enableLayoutBuilderForNodeType('alpha');

    $node = $this->createNode([
      'type' => 'alpha',
    ]);

    $this->drupalGet($node->toUrl());
    $assert->pageTextContains('This is from defaults');
    $assert->pageTextNotContains('This is from the library');

    $page->clickLink('Edit');
    $page->selectFieldOption('Layout', 'alpha');
    $page->pressButton('Save');
    $assert->pageTextNotContains('This is from defaults');
    $assert->pageTextContains('This is from the library');

    $this->drupalGet('admin/structure/layouts/manage/alpha/delete');
    $page->pressButton('Delete');
    $assert->pageTextContains('Deleted layout alpha.');

    $this->drupalGet($node->toUrl());
    $assert->pageTextContains('This is from defaults');
    $assert->pageTextNotContains('This is from the library');
  }

  /**
   * Creates a stored layout for a node type.
   *
   * @param string $node_type
   *   The node type ID.
   */
  private function createLayoutForNodeType($node_type) {
    $page = $this->getSession()->getPage();
    $assert = $this->assertSession();

    $this->drupalGet('admin/structure/layouts');
    $page->clickLink('Add layout');
    $page->fillField('label', $node_type);
    $page->fillField('id', $node_type);
    $page->selectFieldOption('_entity_type', "node:$node_type");
    $page->pressButton('Save');
    $assert->pageTextContains("Edit layout for $node_type");

    $page->clickLink('Add section');
    $page->clickLink('One column');
    $page->fillField('Administrative label', "$node_type library section");
    $page->pressButton('Add section');
    $page->clickLink('Add block');
    $page->clickLink('Powered by Drupal');
    $page->fillField('settings[label]', 'This is from the library');
    $page->checkField('settings[label_display]');
    $page->pressButton('Add block');
    $page->pressButton('Save layout');
    $assert->pageTextContains('The layout has been saved.');
  }

  /**
   * Enables Layout Builder for the default display of a node type.
   *
   * @param string $node_type
   *   The node type ID.
   */
  private function enableLayoutBuilderForNodeType($node_type) {
    $page = $this->getSession()->getPage();
    $assert = $this->assertSession();

    $this->drupalGet("/admin/structure/types/manage/$node_type/display");
    $page->checkField('layout[enabled]');
    $page->checkField('layout[library]');
    $page->pressButton('Save');
    $assert->pageTextContains('Your settings have been saved.');

    $page->clickLink('Manage layout');
    $page->clickLink('Add section');
    $page->clickLink('One column');
    $page->fillField('Administrative label', "$node_type default section");
    $page->pressButton('Add section');
    $page->clickLink('Add block');
    $page->clickLink('Powered by Drupal');
    $page->fillField('settings[label]', 'This is from defaults');
    $page->checkField('settings[label_display]');
    $page->pressButton('Add block');
    $page->pressButton('Save layout');
    $assert->pageTextContains('The layout has been saved.');
  }

}
