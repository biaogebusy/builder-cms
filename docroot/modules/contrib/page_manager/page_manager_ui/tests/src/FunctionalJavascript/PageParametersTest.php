<?php

namespace Drupal\Tests\page_manager_ui\Functional;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Tests the page parameters capabilities.
 *
 * @group page_manager_ui
 */
class PageParametersTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['block', 'page_manager_ui', 'node'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);

    $this->drupalPlaceBlock('local_tasks_block');
    $this->drupalPlaceBlock('local_actions_block');
    $this->drupalPlaceBlock('system_branding_block');
    $this->drupalPlaceBlock('page_title_block');

    $this->drupalLogin($this->drupalCreateUser([
      'administer pages',
      'access administration pages',
      'view the administration theme',
      'create article content',
    ]));
  }

  /**
   * Tests page parameters when adding a page and when editing it.
   */
  public function testParameters() {
    $this->doTestAddParameter();
    $this->doTestOptionalParameters();
  }

  /**
   * Tests page parameters when adding a page and when editing it.
   */
  public function doTestAddParameter() {
    $node = $this->drupalCreateNode(['type' => 'article']);

    // Create a page.
    $this->drupalGet('admin/structure');
    $this->clickLink('Pages');
    $this->clickLink('Add page');
    $this->getSession()->getPage()->fillField('label', 'Foo');
    $this->assertNotEmpty($this->assertSession()->waitForText('Machine name: foo'));

    $edit = [
      'path' => 'admin/foo/{node}',
      'variant_plugin_id' => 'block_display',
      'use_admin_theme' => TRUE,
      'description' => 'Sample test page.',
    ];
    $this->submitForm($edit, 'Next');

    // Test the 'Parameters' step.
    $this->titleEquals('Page parameters | Drupal');
    $access_path = 'admin/structure/page_manager/add/foo/parameters';
    $this->assertSession()->addressEquals($access_path . '?js=nojs');
    $this->assertSession()->pageTextNotContains('There are no parameters defined for this page.');

    // Edit the node parameter.
    $this->clickLink('Edit');
    // Note, this has two spaces because of the missing @label in the test.
    $this->titleEquals('Edit  parameter | Drupal');
    $edit = [
      'type' => 'entity:node',
    ];
    $this->submitForm($edit, 'Update parameter');
    $this->assertSession()->pageTextContains('The node parameter has been updated.');

    // Skip the variant General configuration step.
    $this->submitForm([], 'Next');

    // Add the Node block to the top region.
    $this->submitForm([], 'Next');
    $this->clickLink('Add new block');
    $this->assertNotEmpty($this->assertSession()->waitForText('Select block'));
    $this->clickLink('Entity view (Content)');
    $this->assertNotEmpty($this->assertSession()->waitForText('Add block'));

    $edit = [
      'region' => 'top',
    ];
    $this->submitForm($edit, 'Add block');

    // Finish the wizard.
    $this->submitForm([], 'Finish');
    $this->assertSession()->responseContains(new FormattableMarkup('The page %label has been added.', ['%label' => 'Foo']));

    // Check that the node's title is visible at the page.
    $this->drupalGet('admin/foo/' . $node->id());
    $this->assertSession()->pageTextContains($node->getTitle());
  }

  /**
   * Tests optional parameters.
   *
   * @param string $path
   *   The path this step is supposed to be at.
   * @param bool|true $redirect
   *   Whether or not to redirect to the path.
   */
  protected function doTestOptionalParameters($path = 'admin/structure/page_manager/manage/foo/general', $redirect = TRUE) {
    if ($this->getUrl() !== $path && $redirect) {
      $this->drupalGet($path);
    }

    $this->titleEquals('Page information | Drupal');
    $node = $this->drupalCreateNode(['type' => 'article']);

    // Add extra parameter.
    $edit = [
      'path' => 'admin/foo/{node}/{extra}',
    ];
    $this->submitForm($edit, 'Update and save');
    $this->assertSession()->pageTextContains('The page Foo has been updated.');

    $this->drupalGet('admin/structure/page_manager/manage/foo/parameter/edit/extra');
    $this->getSession()->getPage()->fillField('type', 'string');
    $this->assertNotEmpty($this->assertSession()->waitForText('Label'));

    $edit = [
      'label' => 'Extra',
      'optional' => FALSE,
    ];
    $this->submitForm($edit, 'Update parameter');
    $this->assertSession()->pageTextContains('The Extra parameter has been updated.');
    $this->submitForm([], 'Update and save');
    $this->assertSession()->pageTextContains('The page Foo has been updated.');

    // Check the required extra parameter.
    $this->drupalGet('admin/foo/' . $node->id());
    $this->titleEquals('Page not found | Drupal');
    $this->drupalGet('admin/foo/' . $node->id() . '/' . $this->randomMachineName());
    $this->assertSession()->pageTextContains($node->getTitle());

    // Set the extra parameter as optional.
    $this->drupalGet('admin/structure/page_manager/manage/foo/parameter/edit/extra');
    $edit = [
      'optional' => TRUE,
    ];
    $this->submitForm($edit, 'Update parameter');
    $this->assertSession()->pageTextContains('The Extra parameter has been updated.');
    $this->submitForm([], 'Update and save');
    $this->assertSession()->pageTextContains('The page Foo has been updated.');

    // Check the extra parameter is optional.
    $this->drupalGet('admin/foo/' . $node->id());
    $this->assertSession()->pageTextContains($node->getTitle());
    $this->drupalGet('admin/foo/' . $node->id() . '/' . $this->randomMachineName());
    $this->assertSession()->pageTextContains($node->getTitle());
  }

  /**
   * {@inheritdoc}
   */
  protected function titleEquals($expected_title) {
    $actual_title = $this->assertSession()->elementExists('css', 'title')->getHtml();
    $this->assertSame($expected_title, $actual_title);
  }

}
