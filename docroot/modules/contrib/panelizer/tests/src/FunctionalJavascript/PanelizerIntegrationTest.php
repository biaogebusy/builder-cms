<?php

namespace Drupal\Tests\panelizer\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\panels_ipe\FunctionalJavascript\PanelsIPETestTrait;

/**
 * Tests the JavaScript functionality of Panels IPE with Panelizer.
 *
 * @group panelizer
 */
class PanelizerIntegrationTest extends WebDriverTestBase {

  use PanelsIPETestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field_ui',
    'node',
    'panels_ipe',
    'panelizer',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create the "Basic Page" content type.
    $this->createContentType([
      'type' => 'page',
      'name' => 'Basic Page',
    ]);
    // Create a user with appropriate permissions to use Panels IPE.
    $admin_user = $this->drupalCreateUser([
      'access content',
      'access panels in-place editing',
      'administer blocks',
      'administer content types',
      'administer nodes',
      'administer node display',
      'administer panelizer',
      'create page content',
    ]);
    $this->drupalLogin($admin_user);

    // Enable Panelizer for the "Basic Page" content type.
    $this->container->get('panelizer')
      ->setPanelizerSettings('node', 'page', 'default', [
        'enable' => TRUE,
        'allow' => FALSE,
        'custom' => FALSE,
        'default' => 'default',
      ]);

    // Set the window size to ensure that IPE elements are visible.
    $this->getSession()->resizeWindow(1024, 768);

    // Create a new Basic Page.
    $this->drupalGet('node/add/page');
    $this->submitForm(['title[0][value]' => 'Test Node'], t('Save'));
  }

  /**
   * Tests that the IPE editing session is specific to a user.
   */
  public function testUserEditSession() {
    $assert_session = $this->assertSession();

    $this->drupalGet('/node/1');
    $this->assertIPELoaded();
    $assert_session->elementExists('css', '.layout--onecol');

    // Change the layout to lock the IPE.
    $this->changeLayout('Columns: 2', 'layout_twocol');
    $assert_session->elementExists('css', '.layout--twocol');
    $assert_session->elementNotExists('css', '.layout--onecol');

    // Create a second node.
    $this->drupalGet('node/add/page');
    $this->submitForm(['title[0][value]' => 'Test Node 2'], t('Save'));

    // Ensure the second node does not use the session of the other node.
    $this->drupalGet('/node/2');
    $assert_session->elementExists('css', '.layout--onecol');
    $assert_session->elementNotExists('css', '.layout--twocol');
  }

  /**
   * Tests that adding a block with default configuration works.
   */
  public function testIPEAddBlock() {
    $this->drupalGet('/node/1');
    $this->addBlock('System', 'system_breadcrumb_block');
  }

  /**
   * Tests that changing layout from one (default) to two columns works.
   */
  public function testIPEChangeLayout() {
    $this->drupalGet('/node/1');
    // Change the layout to two columns.
    $this->changeLayout('Columns: 2', 'layout_twocol');
    $this->waitUntilVisible('.layout--twocol', 10000, 'Layout changed to two column.');
  }

  /**
   * Changes the IPE layout.
   *
   * This function assumes you're using Panels layouts and as a result expects
   * the PanelsIPELayoutForm to auto-submit.
   *
   * @param string $category
   *   The name of the category, i.e. "One Column".
   * @param string $layout_id
   *   The ID of the layout, i.e. "layout_onecol".
   */
  protected function changeLayout($category, $layout_id) {
    // Open the "Change Layout" tab.
    $this->clickAndWait('[data-tab-id="change_layout"]');

    // Wait for layouts to be pulled into our collection.
    $this->waitUntilNotPresent('.ipe-icon-loading');

    // Select the target category.
    $this->clickAndWait('[data-category="' . $category . '"]');

    // Select the target layout.
    $this->clickAndWait('[data-layout-id="' . $layout_id . '"]');

    // Wait for the form to load/submit.
    $this->waitUntilNotPresent('.ipe-icon-loading');

    // Layouts can carry administrative labels, so enter one if needed.
    $page = $this->getSession()->getPage();
    $label_field = $page->findField('Administrative label');
    if ($label_field) {
      $label_field->setValue($this->randomString());
      $page->pressButton('Change Layout');
    }

    // Wait for the edit tab to become active (happens automatically after
    // form submit).
    $this->waitUntilVisible('[data-tab-id="edit"].active');
  }

}
