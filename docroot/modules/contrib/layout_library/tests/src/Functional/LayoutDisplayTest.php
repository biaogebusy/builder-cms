<?php

namespace Drupal\Tests\layout_library\Functional;

use Drupal\Component\Utility\DeprecationHelper;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Group;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;

/**
 * Test using a layout from the library on a specific entity view display.
 *
 * @group layout_library
 */
#[Group('layout_library')]
#[RunTestsInSeparateProcesses]
class LayoutDisplayTest extends BrowserTestBase {

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
    $this->drupalPlaceBlock('local_tasks_block');

    $this->createContentType([
      'type' => 'cats',
    ]);
    $this->drupalLogin($this->drupalCreateUser([
      'configure any layout',
      'create cats content',
      'edit own cats content',
      'administer node display',
    ]));
  }

  /**
   * Tests using the layout on a specific view display.
   */
  public function testUseLayoutOnDifferentViewDisplay() {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    $node = $this->createNode(['type' => 'cats']);

    // Add a layout to the library.
    $this->drupalGet('admin/structure/layouts');
    $this->clickLink('Add layout');
    $page->fillField('label', 'Lion');
    $page->fillField('id', 'lion');
    $page->selectFieldOption('_entity_type', 'node:cats');
    $page->pressButton('Save');
    $assert_session->pageTextContains('Edit layout for Lion');

    $page->clickLink('Add section');
    $page->clickLink('One column');
    $page->fillField('layout_settings[label]', 'Lion Section');
    $page->pressButton('Add section');
    $page->clickLink('Add block');
    $page->clickLink('Powered by Drupal');
    $page->fillField('settings[label]', 'This is from the library');
    $page->checkField('settings[label_display]');
    $page->pressButton('Add block');
    $page->pressButton('Save layout');

    // Enable full content display, then enable layout builder and library on
    // the default view display.
    \Drupal::service(EntityDisplayRepositoryInterface::class)->getViewDisplay('node', 'cats', 'full')->enable()->save();
    DeprecationHelper::backwardsCompatibleCall(
      \Drupal::VERSION,
      '11.4',
      fn() => $this->drupalGet('admin/structure/types/manage/cats/display/default'),
      fn() => $this->drupalGet('admin/structure/types/manage/cats/display'),
    );
    $page->checkField('layout[enabled]');
    $page->checkField('layout[library]');
    $page->pressButton('Save');

    $this->drupalGet($node->toUrl());

    // Edit the page to use the layout from the library.
    $page->clickLink('Edit');
    $page->selectFieldOption('Layout', 'lion');
    $page->pressButton('Save');
    $assert_session->pageTextNotContains('This is from the library');

    // Disable the full content display.
    \Drupal::service(EntityDisplayRepositoryInterface::class)->getViewDisplay('node', 'cats', 'full')->disable()->save();

    $this->drupalGet($node->toUrl());
    $assert_session->pageTextContains('This is from the library');

    // Only available in >= D9.1, see
    // https://www.drupal.org/project/layout_library/issues/3082434.
    if (class_exists('Drupal\layout_builder\Event\PrepareLayoutEvent')) {
      // Enable layout overrides.
      DeprecationHelper::backwardsCompatibleCall(
        \Drupal::VERSION,
        '11.4',
        fn() => $this->drupalGet('admin/structure/types/manage/cats/display/default'),
        fn() => $this->drupalGet('admin/structure/types/manage/cats/display'),
      );
      $page->checkField('layout[allow_custom]');
      $page->pressButton('Save');

      // Load the page and override the layout.
      $this->drupalGet($node->toUrl());
      $this->clickLink('Layout');
      $assert_session->pageTextContains('This is from the library');
    }
  }

}
