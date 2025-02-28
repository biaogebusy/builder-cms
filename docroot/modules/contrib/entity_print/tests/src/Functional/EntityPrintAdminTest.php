<?php

namespace Drupal\Tests\entity_print\Functional;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Tests\BrowserTestBase;

/**
 * Entity Print Admin tests.
 *
 * @group Entity Print
 */
class EntityPrintAdminTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['node', 'entity_print_test', 'field', 'field_ui'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The node object to test against.
   *
   * @var \Drupal\node\Entity\Node
   */
  protected $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create a content type and a dummy node.
    $this->drupalCreateContentType([
      'type' => 'page',
      'name' => 'Page',
    ]);
    $this->node = $this->drupalCreateNode();

    $account = $this->createUser([
      'bypass entity print access',
      'administer entity print',
      'access content',
      'administer content types',
      'administer node display',
      'administer user display',
    ]);
    $this->drupalLogin($account);
  }

  /**
   * Test the view PDF extra field and the configurable text.
   */
  public function testViewPdfLink() {
    $assert = $this->assertSession();

    // Run the module install actions as a workaround for the fact that the
    // page content type isn't created until setUp() here and therefore our PDF
    // view mode isn't added the first time. Note, this might causes issues if
    // we ever add to hook_install() actions that cannot run twice.
    \Drupal::moduleHandler()->loadInclude('entity_print', 'install');
    entity_print_install();

    // Ensure the link doesn't appear by default.
    $this->drupalGet($this->node->toUrl());
    $assert->pageTextNotContains('View PDF');
    $assert->linkByHrefNotExists('print/pdf/node/1');

    $full_view_mode = 'Full view mode';
    $pdf_view_mode = 'PDF view mode';
    $this->drupalGet('admin/structure/types/manage/page/display');
    $this->submitForm([
      'fields[entity_print_view_pdf][empty_cell]' => $full_view_mode,
      'fields[entity_print_view_pdf][region]' => 'content',
    ], 'Save');

    // Visit our page node and ensure the link is available.
    $this->drupalGet($this->node->toUrl());
    $assert->linkExists($full_view_mode);
    $assert->linkByHrefExists('/print/pdf/node/1');

    // Ensure we're using the full view mode and not the PDF view mode.
    $this->drupalGet('/print/pdf/node/1/debug');
    $assert->pageTextContains($full_view_mode);
    $assert->pageTextNotContains($pdf_view_mode);
    $this->drupalGet('admin/structure/types/manage/page/display');

    // Configure the PDF view mode.
    $this->submitForm([
      'display_modes_custom[pdf]' => 1,
    ], 'Save');
    $this->drupalGet('admin/structure/types/manage/page/display/pdf');
    $this->submitForm([
      'fields[entity_print_view_pdf][empty_cell]' => $pdf_view_mode,
      'fields[entity_print_view_pdf][region]' => 'content',
    ], 'Save');

    // Ensure the PDF view mode is now in use.
    $this->drupalGet('/print/pdf/node/1/debug');
    $assert->pageTextNotContains($full_view_mode);
    $assert->pageTextContains($pdf_view_mode);

    // Load the EntityViewDisplay and ensure the settings are in the correct
    // place.
    /** @var \Drupal\Core\Entity\Entity\EntityViewDisplay $display */
    $display = EntityViewDisplay::load('node.page.default');
    $this->assertSame($full_view_mode, $display->getThirdPartySetting('entity_print', 'pdf_label'));

    // Ensure the View PDF links appear on a entity type without a bundle.
    $this->drupalGet('/admin/config/people/accounts/display');
    $assert->pageTextContains('View PDF');
  }

}
