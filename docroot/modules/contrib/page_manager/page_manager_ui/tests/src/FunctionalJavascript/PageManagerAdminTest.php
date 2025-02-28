<?php

namespace Drupal\Tests\page_manager_ui\FunctionalJavascript;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\page_manager\Entity\Page;

/**
 * Tests the admin UI for page entities.
 *
 * @group page_manager_ui
 */
class PageManagerAdminTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'classy';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['block', 'page_manager_ui', 'page_manager_test'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalPlaceBlock('local_tasks_block');
    $this->drupalPlaceBlock('local_actions_block');
    $this->drupalPlaceBlock('system_branding_block');
    $this->drupalPlaceBlock('page_title_block');

    \Drupal::service('theme_installer')->install(['olivero']);
    $this->config('system.theme')->set('admin', 'classy')->save();

    $this->drupalLogin($this->drupalCreateUser(['administer pages', 'access administration pages', 'view the administration theme']));

    // Remove the default node_view page to start with a clean UI.
    Page::load('node_view')->delete();
  }

  /**
   * Tests the Page Manager admin UI.
   */
  public function testAdmin() {
    $this->doTestAddPage();
    $this->doTestSelectionCriteriaWithAjax();
  }

  /**
   * Tests adding a page.
   */
  protected function doTestAddPage() {
    $assert_session = $this->assertSession();

    $this->drupalGet('admin/structure');
    $this->clickLink('Pages');
    $assert_session->pageTextContains('Add a new page.');

    // Add a new page without a label.
    $this->clickLink('Add page');
    $this->getSession()->getPage()->fillField('label', 'Foo');
    $this->assertNotEmpty($assert_session->waitForText('Machine name: foo'));
    $edit = [
      'path' => 'admin/foo',
      'variant_plugin_id' => 'http_status_code',
      'use_admin_theme' => TRUE,
      'description' => 'This is our first test page.',
      // Go through all available steps (we skip them all in doTestSecondPage())
      'wizard_options[access]' => TRUE,
      'wizard_options[selection]' => TRUE,
    ];
    $this->submitForm($edit, 'Next');

    // Test the 'Page access' step.
    $this->titleEquals('Page access | Drupal');
    $access_path = 'admin/structure/page_manager/add/foo/access';
    $this->assertSession()->addressEquals($access_path . '?js=nojs');
    $this->submitForm([], 'Next');

    // Test the 'Selection criteria' step.
    $this->titleEquals('Selection criteria | Drupal');
    $selection_path = 'admin/structure/page_manager/add/foo/selection';
    $this->assertSession()->addressEquals($selection_path . '?js=nojs');
    $this->submitForm([], 'Next');

    // Configure the variant.
    $edit = [
      'page_variant_label' => 'Status Code',
      'variant_settings[status_code]' => 200,
    ];
    $this->submitForm($edit, 'Finish');
    $this->assertSession()->responseContains(new FormattableMarkup('The page %label has been added.', ['%label' => 'Foo']));
    // We've gone from the add wizard to the edit wizard.
    $this->drupalGet('admin/structure/page_manager/manage/foo/general');

    $this->drupalGet('admin/foo');
    $this->titleEquals('Foo | Drupal');

    // Change the status code to 403.
    $this->drupalGet('admin/structure/page_manager/manage/foo/page_variant__foo-http_status_code-0__general');
    $edit = [
      'variant_settings[status_code]' => 403,
    ];
    $this->submitForm($edit, 'Update and save');
  }

  /**
   * {@inheritdoc}
   */
  protected function titleEquals($expected_title) {
    $actual_title = $this->assertSession()->elementExists('css', 'title')->getHtml();
    $this->assertSame($expected_title, $actual_title);
  }

  /**
   * Tests the AJAX form for Selection Criteria.
   */
  protected function doTestSelectionCriteriaWithAjax() {
    $page = $this->getSession()->getPage();

    $this->drupalGet('admin/structure/page_manager/manage/foo/page_variant__foo-http_status_code-0__selection');
    $page->selectFieldOption('conditions', 'user_role');
    $page->pressButton('Add Condition');
    $this->assertNotEmpty($this->assertSession()->waitForText('Configure Required Context'));
  }

}
