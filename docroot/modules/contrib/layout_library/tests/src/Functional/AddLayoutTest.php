<?php

namespace Drupal\Tests\layout_library\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;

/**
 * Tests adding a layout to the library.
 *
 * @group layout_library
 */
class AddLayoutTest extends BrowserTestBase {

  use ContentTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'layout_library',
    'field_ui',
    'block',
    'node',
    'options',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalPlaceBlock('local_actions_block');

    $this->createContentType([
      'type' => 'my_little_dinosaur',
      'name' => 'My Little Dinosaur',
    ]);
    $this->drupalLogin($this->drupalCreateUser([
      'configure any layout',
      'create my_little_dinosaur content',
      'administer node display',
    ]));
  }

  /**
   * Tests adding a layout to the library.
   */
  public function testAddLayout() {
    $session = $this->assertSession();
    $page = $this->getSession()->getPage();

    $this->drupalGet('admin/structure/layouts');
    $this->clickLink('Add layout');

    $page->fillField('label', 'Archaeopteryx');
    $page->fillField('id', 'archaeopteryx');
    $page->selectFieldOption('_entity_type', 'node:my_little_dinosaur');
    $page->pressButton('Save');

    $session->pageTextContains('Edit layout for Archaeopteryx');

    // Add three sections: a header, a two-column content area, and a footer.
    $page->clickLink('Add section');
    $page->clickLink('One column');
    $page->fillField('Administrative label', 'Header');
    $page->pressButton('Add section');
    $session->linkExists('Configure Header');
    $this->addSectionAfter(1);
    $page->clickLink('Two column');
    $page->selectFieldOption('Column widths', '67%/33%');
    $page->fillField('Administrative label', 'Main content');
    $page->pressButton('Add section');
    $session->linkExists('Configure Main content');
    $this->addSectionAfter(2);
    $page->clickLink('One column');
    $page->fillField('Administrative label', 'Footer');
    $page->pressButton('Add section');
    $session->linkExists('Configure Footer');

    // Try to remove the Header section.
    $page->clickLink('Remove Header');
    $page->pressButton('Remove');
    $session->statusCodeEquals(200);
    $session->linkNotExists('Configure Header');

    $this->drupalGet('admin/structure/types/manage/my_little_dinosaur/display');
    $page->checkField('layout[enabled]');
    $page->checkField('layout[library]');
    $page->pressButton('Save');
    $page->checkField('layout[allow_custom]');
    $page->pressButton('Save');

    $this->drupalGet('node/add/my_little_dinosaur');
    $session->optionExists('Layout', 'Archaeopteryx');

    $this->drupalGet('admin/structure/types/manage/my_little_dinosaur/display');
    $page->uncheckField('layout[allow_custom]');
    $page->uncheckField('layout[library]');
    $page->pressButton('Save');

    $this->drupalGet('node/add/my_little_dinosaur');
    $session->fieldNotExists('Layout');
  }

  /**
   * Clicks the "Add section" button after an existing section.
   *
   * @param int $index
   *   (optional) The index of the existing session. Defaults to 0.
   */
  private function addSectionAfter($index = 0) {
    $add_links = $this->getSession()->getPage()->findAll('named', ['link', 'Add section']);
    $this->assertGreaterThan($index, count($add_links));
    $add_links[$index]->click();
  }

}
