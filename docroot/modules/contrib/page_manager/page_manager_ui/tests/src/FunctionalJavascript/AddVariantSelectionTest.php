<?php

namespace Drupal\Tests\page_manager_ui\Functional;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Tests selection criteria for page variants.
 *
 * @group page_manager_ui
 */
class AddVariantSelectionTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['page_manager', 'page_manager_ui', 'node'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);
    $this->drupalCreateContentType(['type' => 'page', 'name' => 'Page']);
    $this->drupalLogin($this->drupalCreateUser(['administer pages', 'create article content']));

    $this->drupalPlaceBlock('page_title_block');
  }

  /**
   * Tests configuration of the selection criteria wizard step.
   */
  public function testSelectionCriteria() {
    $assert_session = $this->assertSession();

    // Create a node, and check its page.
    $node = $this->drupalCreateNode(['type' => 'article']);
    $node2 = $this->drupalCreateNode(['type' => 'article']);
    $this->drupalGet('node/' . $node->id());
    $assert_session->pageTextContains($node->label());

    // Create a new page entity.
    $this->drupalGet('admin/structure/page_manager/add');
    $this->getSession()->getPage()->fillField('label', 'Selection criteria');
    $this->assertNotEmpty($assert_session->waitForText('Machine name: selection_criteria'));

    $edit = [
      'path' => 'selection-criteria',
      'variant_plugin_id' => 'block_display',
    ];

    $this->submitForm($edit, 'Next');
    $this->submitForm([], 'Next');
    $this->submitForm([], 'Finish');
    $this->clickLink('Add variant');
    $edit = [
      'label' => 'Variant two',
      'variant_plugin_id' => 'block_display',
      'wizard_options[contexts]' => TRUE,
      'wizard_options[selection]' => TRUE,
    ];
    $this->submitForm($edit, 'Next');
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
      $this->assertNotEmpty($this->assertSession()->waitForText('Add new context'));
      $this->getSession()->getPage()->fillField('label', $context['title']);
      $this->assertNotEmpty($assert_session->waitForText('Machine name: ' . $context['machine_name']));
      $edit = [
        'description' => $context['description'],
        'context_value' => $context['node']->getTitle() . ' (' . $context['node']->id() . ')',
      ];
      $this->submitForm($edit, 'Save');
      $assert_session->pageTextContains($assert_session->waitForText($context['title']));
    }
    $this->submitForm([], 'Next');

    // Configure selection criteria.
    $edit = [
      'conditions' => 'entity_bundle:node',
    ];
    $this->submitForm($edit, 'Add Condition');
    $this->assertNotEmpty($this->assertSession()->waitForText('Configure Required Context'));
    $edit = [
      'bundles[article]' => TRUE,
      'bundles[page]' => TRUE,
      'context_mapping[node]' => 'static_node_2',
    ];
    $this->submitForm($edit, 'Save');
    $this->assertNotEmpty($this->assertSession()->waitForText('Content type is article or page'));
    $this->clickLink('Edit');
    $this->assertNotEmpty($this->assertSession()->waitForText('Add new selection condition'));
    $edit = [
      'bundles[article]' => TRUE,
      'context_mapping[node]' => 'static_node_2',
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->waitForElementRemoved('css', '.ui-dialog');
    $this->assertNotEmpty($this->assertSession()->waitForText('Content type is article'));
    $this->toggleDropbutton('Edit');
    $this->clickLink('Delete');
    $this->assertNotEmpty($this->assertSession()->waitForText('This action cannot be undone.'));
    $this->getSession()->getPage()->find("css", ".ui-dialog-buttonset")->pressButton("Delete");
    $this->assertSession()->waitForElementRemoved('css', '.ui-dialog');
    $this->assertSession()->pageTextContains('No required conditions have been configured.');
    $this->submitForm([], 'Next');

    // Configure the new variant.
    $variant_edit = [
      'variant_settings[page_title]' => 'Variant two criteria test',
    ];
    $this->submitForm($variant_edit, 'Next');

    // Add a block that renders the node from the first static context.
    $this->clickLink('Add new block');
    $this->assertNotEmpty($this->assertSession()->waitForText('Select block'));
    $this->clickLink('Entity view (Content)');
    $this->assertNotEmpty($this->assertSession()->waitForText('Add block'));
    $edit = [
      'settings[label]' => 'Static node view',
      'settings[label_display]' => 1,
      'settings[view_mode]' => 'default',
      'region' => 'top',
    ];
    $this->submitForm($edit, 'Add block');
    $this->assertSession()->waitForElementRemoved('css', '.ui-dialog');
    $this->assertSession()->pageTextContains($edit['settings[label]']);

    // Add a block that renders the node from the second static context.
    $this->clickLink('Add new block');
    $this->assertNotEmpty($this->assertSession()->waitForText('Select block'));
    $this->clickLink('Entity view (Content)');
    $this->assertNotEmpty($this->assertSession()->waitForText('Add block'));
    $edit = [
      'settings[label]' => 'Static node 2 view',
      'settings[label_display]' => 1,
      'settings[view_mode]' => 'default',
      'region' => 'bottom',
      'context_mapping[entity]' => $contexts[1]['machine_name'],
    ];
    $this->submitForm($edit, 'Add block');
    $this->assertSession()->waitForElementRemoved('css', '.ui-dialog');
    $this->assertSession()->pageTextContains($edit['settings[label]']);
    $this->submitForm([], 'Finish');
  }

  /**
   * Toggles the Drop Button widget.
   *
   * @param string $primary_action
   *   Primary button to search for action.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   */
  protected function toggleDropbutton(string $primary_action): void {
    $link = $this->assertSession()->elementExists('named', ['link', $primary_action]);
    $dropbutton = $link->getParent()->getParent()->getParent();
    self::assertEquals('div', $dropbutton->getTagName());
    self::assertTrue($dropbutton->hasClass('dropbutton-widget'), $dropbutton->getHtml());
    $dropbutton->find('css', 'li.dropbutton-toggle')->click();
  }

}
