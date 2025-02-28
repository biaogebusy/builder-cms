<?php

namespace Drupal\Tests\entity_print\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Test the Entity Print action tests.
 *
 * @group entity_print
 */
class EntityPrintActionTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['node', 'entity_print_test', 'views'];

  /**
   * The node object to test against.
   *
   * @var \Drupal\node\Entity\Node
   */
  protected $node;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

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
      'access content overview',
      'administer nodes',
    ]);
    $this->drupalLogin($account);

    // Change to the test PDF implementation.
    $config = \Drupal::configFactory()->getEditable('entity_print.settings');
    $config
      ->set('print_engines.pdf_engine', 'testprintengine')
      ->save();
  }

  /**
   * Test that the download PDF action works as expected.
   */
  public function testDownloadPdfAction() {
    $this->drupalGet('/admin/content');
    $this->submitForm([
      'action' => 'entity_print_pdf_download_action',
      'node_bulk_form[0]' => 1,
    ], 'Apply to selected items');
    $this->assertSession()->pageTextContains('Using testprintengine');
  }

  /**
   * {@inheritdoc}
   */
  protected function checkForMetaRefresh() {
    // A meta refresh is inserted when using the test PDF engine, but this is
    // not present for real engines. So the test can assert the engine is
    // invoked, do not follow the meta refresh.
  }

}
