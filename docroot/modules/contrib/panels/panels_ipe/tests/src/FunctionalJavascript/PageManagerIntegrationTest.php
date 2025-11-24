<?php

namespace Drupal\Tests\panels_ipe\FunctionalJavascript;

/**
 * Tests the JavaScript functionality of Panels IPE with PageManager.
 *
 * @group panels
 */
class PageManagerIntegrationTest extends PanelsIPETestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'panels',
    'panels_ipe',
    'page_manager',
    'panels_ipe_page_manager_test_config',
    'system',
  ];

  /**
   * @var \Drupal\user\UserInterface
   */
  protected $user1;

  /**
   * @var \Drupal\user\UserInterface
   */
  protected $user2;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create a user with appropriate permissions to use Panels IPE.
    $this->user1 = $this->drupalCreateUser([
      'access panels in-place editing',
      'administer blocks',
      'administer pages',
    ]);
    $this->user2 = $this->drupalCreateUser([
      'access panels in-place editing',
      'administer blocks',
      'administer pages',
    ]);

    $this->drupalLogin($this->user1);

    $this->test_route = 'test-page';
  }

  /**
   * Tests that the IPE editing session is specific to a user.
   */
  public function testUserEditSession() {
    $this->visitIPERoute();
    $this->assertSession()->elementExists('css', '.layout--onecol');

    // Change the layout to lock the IPE.
    $this->changeLayout('Columns: 2', 'layout_twocol');
    $this->assertSession()->elementExists('css', '.layout--twocol');
    $this->assertSession()->elementNotExists('css', '.layout--onecol');
    $this->assertSession()->elementExists('css', '[data-tab-id="save"]');

    // Ensure the second user does not see the session of the other user.
    $this->drupalLogin($this->user2);
    $this->visitIPERoute();
    $this->assertSession()->elementExists('css', '.layout--onecol');
    $this->assertSession()->elementNotExists('css', '.layout--twocol');
    // Ensure the IPE is locked.
    $this->assertSession()->elementNotExists('css', '[data-tab-id="edit"]');
    $this->assertSession()->elementExists('css', '[data-tab-id="locked"]');

    // Click the break lock button.
    $this->breakLock();
    $this->assertSession()->waitForElementVisible('css', '[data-tab-id="edit"]');

    // Log back in as the first user to find the edits gone.
    $this->drupalLogin($this->user1);
    $this->visitIPERoute();
    $this->assertSession()->elementExists('css', '[data-tab-id="edit"]');
    $this->assertSession()->elementNotExists('css', '[data-tab-id="save"]');
    $this->assertSession()->elementExists('css', '.layout--onecol');
  }

  /**
   * Test IPE with Panels block preview and custom CSS properties.
   */
  public function testBlockPreviewAndCustomCssProperties() {
    $page = $this->getSession()->getPage();
    $this->visitIPERoute();
    // Visit IPE page and add block. (@see PanelsIPETestTrait addBlock() method)
    $this->clickAndWait('[data-tab-id="manage_content"]');
    $this->waitUntilNotPresent('.ipe-icon-loading');
    $this->clickAndWait('[data-category="System"]');
    $this->getSession()->executeScript("jQuery('" . '[data-plugin-id="system_powered_by_block"]' . "')[0].click()");
    $this->waitUntilNotPresent('.ipe-icon-loading');
    $this->waitUntilVisible('.ipe-form form');
    $this->clickAndWait('[data-drupal-selector="edit-settings-style-settings"]');
    // Generate random HTML Id., CSS classes and styles.
    $css = $this->generateCssProperties();
    // Fill settings fields.
    $page->fillField('settings[style_settings][html_id]', $css['html_id']);
    $page->fillField('settings[style_settings][css_classes]', $css['css_classes']);
    $page->fillField('settings[style_settings][css_styles]', $css['css_style']);
    // Click preview button and check result.
    $this->clickAndWait('[data-drupal-selector="edit-preview"]');
    $this->assertSession()->elementExists('css', '#' . $css['html_id']);
    $this->assertSession()->elementExists('css', '.' . str_replace(' ', '.', $css['css_classes']));
    $this->assertSession()->elementExists('css', '[style*="' . $css['css_style'] . '"]');
    // Click preview button and re-fill settings form with new values.
    $this->clickAndWait('[data-drupal-selector="edit-preview"]');
    $this->clickAndWait('[data-drupal-selector="edit-settings-style-settings"]');
    $css = $this->generateCssProperties();
    $page->fillField('settings[style_settings][html_id]', $css['html_id']);
    $page->fillField('settings[style_settings][css_classes]', $css['css_classes']);
    $page->fillField('settings[style_settings][css_styles]', $css['css_style']);
    $this->saveBlockConfigurationForm();
    $this->waitUntilNotPresent('.ipe-icon-loading');
    $this->clickAndWait('[data-tab-id="save"]');
    $this->assertSession()->elementExists('css', '#' . $css['html_id']);
    $this->assertSession()->elementExists('css', '.' . str_replace(' ', '.', $css['css_classes']));
    $this->assertSession()->elementExists('css', '[style*="' . $css['css_style'] . '"]');
  }

  /**
   * Test IPE with Panels block edit and custom CSS properties.
   */
  public function testBlockEditAndCustomCssProperties() {
    $page = $this->getSession()->getPage();
    $this->visitIPERoute();
    $this->addBlock('System', 'system_breadcrumb_block');
    // Edit block settings and fill style-settings with random values.
    $this->clickAndWait('[data-block-edit-id="system_breadcrumb_block"] [data-action-id="configure"]');
    // Wait for the Block form to finish loading/opening.
    $this->waitUntilNotPresent('.ipe-icon-loading');
    $this->waitUntilVisible('.ipe-form form');
    $this->clickAndWait('[data-drupal-selector="edit-settings-style-settings"]');
    // Generate random HTML Id., CSS classes and styles.
    $css = $this->generateCssProperties();
    $page->fillField('settings[style_settings][html_id]', $css['html_id']);
    $page->fillField('settings[style_settings][css_classes]', $css['css_classes']);
    $page->fillField('settings[style_settings][css_styles]', $css['css_style']);
    $this->saveBlockConfigurationForm();
    $this->clickAndWait('[data-tab-id="save"]');
    $this->assertSession()->elementExists('css', '#' . $css['html_id']);
    $this->assertSession()->elementExists('css', '.' . str_replace(' ', '.', $css['css_classes']));
    $this->assertSession()->elementExists('css', '[style*="' . $css['css_style'] . '"]');
  }

}
