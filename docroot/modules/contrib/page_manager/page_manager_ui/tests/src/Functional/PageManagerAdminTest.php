<?php

namespace Drupal\Tests\page_manager_ui\Functional;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Url;
use Drupal\page_manager\Entity\Page;
use Drupal\page_manager\Entity\PageVariant;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the admin UI for page entities.
 *
 * @group page_manager_ui
 */
class PageManagerAdminTest extends BrowserTestBase {

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
  }

  /**
   * Tests the Page Manager admin UI.
   */
  public function testAdmin() {
    // Remove the default node_view page to start with a clean UI.
    Page::load('node_view')->delete();

    $this->doTestAddPage();
    $this->doTestAccessConditions();
    $this->doTestSelectionCriteria();
    $this->doTestDisablePage();
    $this->doTestAddVariant();
    $this->doTestAddBlock();
    $this->doTestSecondPage();
    $this->doTestEditBlock();
    $this->doTestEditVariant();
    $this->doTestReorderVariants();
    $this->doTestAddPageWithDuplicatePath();
    $this->doTestAdminPath();
    $this->doTestRemoveVariant();
    $this->doTestRemoveBlock();
    $this->doTestAddBlockWithAjax();
    $this->doTestEditBlock();
    $this->doTestExistingPathWithoutParameters();
    $this->doTestUpdateSubmit();
    $this->doTestDeletePage();
  }

  /**
   * Tests the Page Manager List Links.
   */
  public function testListLinks() {
    $this->doTestAddPage(FALSE);
    $this->doTestListLink();
  }

  /**
   * Tests adding a page.
   *
   * @param bool $empty
   *   Boolean indicating whether we must expect the empty list message.
   */
  protected function doTestAddPage($empty = TRUE) {
    $this->drupalGet('admin/structure');
    $this->clickLink('Pages');
    if ($empty) {
      $this->assertSession()->pageTextContains('Add a new page.');
    }

    // Add a new page without a label.
    $this->clickLink('Add page');
    $edit = [
      'id' => 'foo',
      'path' => 'admin/foo',
      'variant_plugin_id' => 'http_status_code',
      'use_admin_theme' => TRUE,
      'description' => 'This is our first test page.',
      // Go through all available steps (we skip them all in doTestSecondPage())
      'wizard_options[access]' => TRUE,
      'wizard_options[selection]' => TRUE,
    ];
    $this->submitForm($edit, 'Next');
    $this->assertSession()->pageTextContains('Administrative title field is required.');

    // Add a new page with a label.
    $edit += ['label' => 'Foo'];
    $this->submitForm($edit, 'Next');

    // Test the 'Page access' step.
    $this->assertSession()->titleEquals('Page access | Drupal');
    $access_path = 'admin/structure/page_manager/add/foo/access';
    $this->assertSession()->addressEquals($access_path . '?js=nojs');
    $this->doTestAccessConditions($access_path, FALSE);
    $this->submitForm([], 'Next');

    // Test the 'Selection criteria' step.
    $this->assertSession()->titleEquals('Selection criteria | Drupal');
    $selection_path = 'admin/structure/page_manager/add/foo/selection';
    $this->assertSession()->addressEquals($selection_path . '?js=nojs');
    $this->doTestSelectionCriteria($selection_path, FALSE);
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
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->titleEquals('Foo | Drupal');

    // Change the status code to 403.
    $this->drupalGet('admin/structure/page_manager/manage/foo/page_variant__foo-http_status_code-0__general');
    $edit = [
      'variant_settings[status_code]' => 403,
    ];
    $this->submitForm($edit, 'Update');

    // Set the weight of the 'Status Code' variant to 10.
    $this->drupalGet('admin/structure/page_manager/manage/foo/reorder_variants');
    $edit = [
      'variants[foo-http_status_code-0][weight]' => 10,
    ];
    $this->submitForm($edit, 'Update');
    $this->submitForm([], 'Update and save');
  }

  /**
   * Tests links behavior in list page.
   */
  protected function doTestListLink() {
    $this->drupalGet('/admin/structure/page_manager');
    $this->assertSession()->linkNotExists('/node/{node}');
    $this->assertSession()->responseContains('/node/{node}');
    $this->assertSession()->linkExists('/admin/foo');
  }

  /**
   * Tests access conditions step on both add and edit wizard.
   *
   * @param string $path
   *   The path this step is supposed to be at.
   * @param bool|true $redirect
   *   Whether or not to redirect to the path.
   */
  protected function doTestAccessConditions($path = 'admin/structure/page_manager/manage/foo/access', $redirect = TRUE) {
    if ($this->getUrl() !== $path && $redirect) {
      $this->drupalGet($path);
    }

    $this->assertSession()->responseContains('No required conditions have been configured.');

    // Configure a new condition.
    $edit = [
      'conditions' => 'user_role',
    ];
    $this->submitForm($edit, 'Add Condition');
    $this->assertSession()->titleEquals('Add access condition | Drupal');
    $edit = [
      'roles[authenticated]' => TRUE,
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->responseContains('The user is a member of Authenticated user');
    // Make sure we're still on the same wizard.
    $this->assertSession()->addressEquals($path);

    // Edit the condition.
    $this->clickLink('Edit');
    $this->assertSession()->titleEquals('Edit access condition | Drupal');
    $edit = [
      'roles[anonymous]' => TRUE,
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->responseContains('The user is a member of Anonymous user, Authenticated user');
    $this->assertSession()->addressEquals($path);

    // Delete the condition.
    $this->clickLink('Delete');
    $this->assertSession()->titleEquals('Are you sure you want to delete the user_role condition? | Drupal');
    $this->submitForm([], 'Delete');
    $this->assertSession()->responseContains('No required conditions have been configured.');
    $this->assertSession()->addressEquals($path);
  }

  /**
   * Tests selection criteria step on both add and edit wizard.
   *
   * @param string $path
   *   The path this step is supposed to be at.
   * @param bool|true $redirect
   *   Whether or not to redirect to the path.
   */
  protected function doTestSelectionCriteria($path = 'admin/structure/page_manager/manage/foo/page_variant__foo-http_status_code-0__selection', $redirect = TRUE) {
    if ($this->getUrl() !== $path && $redirect) {
      $this->drupalGet($path);
    }
    $this->assertSession()->responseContains('No required conditions have been configured.');

    // Configure a new condition.
    $edit = [
      'conditions' => 'user_role',
    ];
    $this->submitForm($edit, 'Add Condition');
    $this->assertSession()->titleEquals('Add new selection condition | Drupal');
    $edit = [
      'roles[authenticated]' => TRUE,
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->responseContains('The user is a member of Authenticated user');
    // Make sure we're still on the add wizard (not the edit wizard).
    $this->assertSession()->addressEquals($path);

    // Edit the condition.
    $this->clickLink('Edit');
    $this->assertSession()->titleEquals('Edit selection condition | Drupal');
    $edit = [
      'roles[anonymous]' => TRUE,
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->responseContains('The user is a member of Anonymous user, Authenticated user');
    $this->assertSession()->addressEquals($path);

    // Delete the condition.
    $this->clickLink('Delete');
    $this->assertSession()->titleEquals('Are you sure you want to delete the user_role condition? | Drupal');
    $this->submitForm([], 'Delete');
    $this->assertSession()->responseContains('No required conditions have been configured.');
    $this->assertSession()->addressEquals($path);
  }

  /**
   * Tests disabling a page.
   */
  protected function doTestDisablePage() {
    $this->drupalGet('admin/foo');
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalGet('admin/structure/page_manager');
    $this->clickLink('Disable');
    $this->drupalGet('admin/foo');
    // The page should not be found if the page is enabled.
    $this->assertSession()->statusCodeEquals(404);

    $this->drupalGet('admin/structure/page_manager');
    $this->clickLink('Enable');
    $this->drupalGet('admin/foo');
    // Re-enabling the page should make this path available.
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests adding a variant.
   */
  protected function doTestAddVariant() {
    $this->drupalGet('admin/structure/page_manager/manage/foo/general');

    // Add a new variant.
    $this->clickLink('Add variant');
    $edit = [
      'variant_plugin_id' => 'block_display',
      'label' => 'First',
    ];
    $this->submitForm($edit, 'Next');

    // Set the page title.
    $edit = [
      'variant_settings[page_title]' => 'Example title',
    ];
    $this->submitForm($edit, 'Next');

    // Finish variant wizard without adding blocks.
    $this->submitForm([], 'Finish');

    // Save page to apply variant changes.
    $this->submitForm([], 'Update and save');

    // Test that the variant is still used but empty.
    $this->drupalGet('admin/foo');
    $this->assertSession()->statusCodeEquals(200);
    // Tests that the content region has no content at all.
    $elements = $this->getSession()->getPage()->findAll('css', 'div.region.region-content *');
    // From Drupal 9.4 the system-branding-block and the site-logo have
    // been placed in the region-content element. Before that, it had only the
    // drupal fallback messages element.
    $expected_count = version_compare(\Drupal::VERSION, '9.4', '<') ? 1 : 8;
    $this->assertCount($expected_count, $elements);
    $this->assertTrue($elements[0]->hasAttribute('data-drupal-messages-fallback'));
  }

  /**
   * Tests adding a block to a variant.
   */
  protected function doTestAddBlock() {
    $this->drupalGet('admin/structure/page_manager/manage/foo/page_variant__foo-block_display-0__content');
    // Add a block to the variant.
    $this->clickLink('Add new block');

    // Assert that the broken/missing block is not visible.
    $this->assertSession()->pageTextNotContains('Broken/Missing');

    $this->clickLink('User account menu');
    $edit = [
      'region' => 'top',
    ];
    $this->submitForm($edit, 'Add block');
    $this->submitForm([], 'Update and save');

    // Test that the block is displayed.
    $this->drupalGet('admin/foo');
    $this->assertSession()->statusCodeEquals(200);
    $elements = $this->xpath('//div[@class="block-region-top"]/nav/ul[@class="menu"]/li/a');
    $this->assertSession()->titleEquals('Example title | Drupal');
    $expected = ['My account', 'Log out'];
    $links = [];
    foreach ($elements as $element) {
      $links[] = $element->getText();
    }
    $this->assertEquals($expected, $links);
    // Check the block label.
    $this->assertSession()->responseContains('User account menu');
  }

  /**
   * Creates a second page with another block display.
   */
  protected function doTestSecondPage() {
    $this->drupalGet('admin/structure/page_manager');

    // Add a new page.
    $this->clickLink('Add page');
    $edit = [
      'id' => 'second',
      'label' => 'Second',
      'path' => 'second',
      'variant_plugin_id' => 'block_display',
    ];
    $this->submitForm($edit, 'Next');

    // Configure the variant.
    $edit = [
      'page_variant_label' => 'Second variant',
      'variant_settings[page_title]' => 'Second title',
    ];
    $this->submitForm($edit, 'Next');

    // We're now on the content step, but we don't need to add any blocks.
    $this->submitForm([], 'Finish');
    $this->assertSession()->responseContains(new FormattableMarkup('The page %label has been added.', ['%label' => 'Second']));

    // Visit both pages, make sure that they do not interfere with each other.
    $this->drupalGet('admin/foo');
    $this->assertSession()->titleEquals('Example title | Drupal');
    $this->drupalGet('second');
    $this->assertSession()->titleEquals('Second title | Drupal');
  }

  /**
   * Tests editing a block.
   */
  protected function doTestEditBlock() {
    $this->drupalGet('admin/structure/page_manager/manage/foo/page_variant__foo-block_display-0__general');
    $edit = [
      'variant_settings[page_title]' => 'Updated block label',
      'page_variant_label' => 'Updated block label',
    ];
    $this->submitForm($edit, 'Update and save');
    // Test that the block is displayed.
    $this->drupalGet('admin/foo');
    $this->assertSession()->statusCodeEquals(200);
    // Check the block label.
    $this->assertSession()->responseContains($edit['variant_settings[page_title]']);
  }

  /**
   * Tests editing a variant.
   */
  protected function doTestEditVariant() {
    if (!$block = $this->findBlockByLabel('foo-block_display-0', 'User account menu')) {
      $this->fail('Block not found');
      return;
    }

    $block_config = $block->getConfiguration();
    $this->drupalGet('admin/structure/page_manager/manage/foo/page_variant__foo-block_display-0__content');

    $this->assertTrue($this->assertSession()->optionExists('edit-blocks-' . $block_config['uuid'] . '-region', 'top')->hasAttribute('selected'));
    $this->assertTrue($this->assertSession()->optionExists('edit-blocks-' . $block_config['uuid'] . '-weight', 0)->hasAttribute('selected'));

    $form_name = 'blocks[' . $block_config['uuid'] . ']';
    $edit = [
      $form_name . '[region]' => 'bottom',
      $form_name . '[weight]' => -10,
    ];
    $this->submitForm($edit, 'Update');
    $this->assertTrue($this->assertSession()->optionExists('edit-blocks-' . $block_config['uuid'] . '-region', 'bottom')->hasAttribute('selected'));
    $this->assertTrue($this->assertSession()->optionExists('edit-blocks-' . $block_config['uuid'] . '-weight', -10)->hasAttribute('selected'));
    $this->submitForm([], 'Update and save');
  }

  /**
   * Tests reordering variants.
   */
  protected function doTestReorderVariants() {
    $this->drupalGet('admin/foo');
    $this->assertSession()->statusCodeEquals(200);
    $elements = $this->xpath('//div[@class="block-region-bottom"]/nav/ul[@class="menu"]/li/a');
    $expected = ['My account', 'Log out'];
    $links = [];
    foreach ($elements as $element) {
      $links[] = $element->getText();
    }
    $this->assertEquals($expected, $links);

    $this->drupalGet('admin/structure/page_manager/manage/foo/general');
    $this->clickLink('Reorder variants');

    $edit = [
      'variants[foo-http_status_code-0][weight]' => -10,
    ];
    $this->submitForm($edit, 'Update');
    $this->submitForm([], 'Update and save');
    $this->drupalGet('admin/foo');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests adding a page with a duplicate path.
   */
  protected function doTestAddPageWithDuplicatePath() {
    // Try to add a second page with the same path.
    $edit = [
      'label' => 'Bar',
      'id' => 'bar',
      'path' => 'admin/foo',
    ];
    $this->drupalGet('admin/structure/page_manager/add');
    $this->submitForm($edit, 'Next');
    $this->assertSession()->pageTextContains('The page path must be unique.');
    $this->drupalGet('admin/structure/page_manager');
    $this->assertSession()->pageTextNotContains('Bar');
  }

  /**
   * Tests changing the admin theme of a page.
   */
  protected function doTestAdminPath() {
    $this->config('system.theme')->set('default', 'olivero')->save();
    $this->drupalGet('admin/foo');
    $this->assertTheme('classy');

    $this->drupalGet('admin/structure/page_manager/manage/foo/general');
    $edit = [
      'use_admin_theme' => FALSE,
    ];
    $this->submitForm($edit, 'Update and save');
    $this->drupalGet('admin/foo');
    $this->assertTheme('olivero');

    // Reset theme.
    $this->config('system.theme')->set('default', 'classy')->save();
  }

  /**
   * Tests removing a variant.
   */
  protected function doTestRemoveVariant() {
    $this->drupalGet('admin/structure/page_manager/manage/foo/page_variant__foo-http_status_code-0__general');
    $this->clickLink('Delete this variant');
    $this->assertSession()->responseContains('Are you sure you want to delete this variant?');
    $this->submitForm([], 'Delete');
    $this->assertSession()->responseContains(new FormattableMarkup('The variant %label has been removed.', ['%label' => 'Status Code']));
    $this->submitForm([], 'Update and save');
  }

  /**
   * Tests removing a block.
   */
  protected function doTestRemoveBlock() {
    // Assert that the block is displayed.
    $this->drupalGet('admin/foo');
    $this->assertSession()->statusCodeEquals(200);
    $elements = $this->xpath('//div[@class="block-region-bottom"]/nav/ul[@class="menu"]/li/a');
    $expected = ['My account', 'Log out'];
    $links = [];
    foreach ($elements as $element) {
      $links[] = $element->getText();
    }
    $this->assertEquals($expected, $links);

    $this->drupalGet('admin/structure/page_manager/manage/foo/page_variant__foo-block_display-0__content');
    $this->clickLink('Delete');
    $this->assertSession()->responseContains(new FormattableMarkup('Are you sure you want to delete the block %label?', ['%label' => 'User account menu']));
    $this->submitForm([], 'Delete');
    $this->assertSession()->responseContains(new FormattableMarkup('The block %label has been removed.', ['%label' => 'User account menu']));
    $this->submitForm([], 'Update and save');

    // Assert that the block is now gone.
    $this->drupalGet('admin/foo');
    $this->assertSession()->statusCodeEquals(200);
    $elements = $this->xpath('//div[@class="block-region-bottom"]/nav/ul[@class="menu"]/li/a');
    $this->assertTrue(empty($elements));
  }

  /**
   * Tests adding a block with #ajax to a variant.
   */
  protected function doTestAddBlockWithAjax() {
    $this->drupalGet('admin/structure/page_manager/manage/foo/page_variant__foo-block_display-0__content');
    // Add a block to the variant.
    $this->clickLink('Add new block');
    $this->clickLink('Page Manager Test Block');
    $edit = [
      'region' => 'top',
    ];
    $this->submitForm($edit, 'Add block');
    $this->submitForm([], 'Update and save');

    // Test that the block is displayed.
    $this->drupalGet('admin/foo');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains(t('Example output'));
    // Check the block label.
    $this->assertSession()->responseContains('Page Manager Test Block');
  }

  /**
   * Tests adding a page with an existing path with no route parameters.
   */
  protected function doTestExistingPathWithoutParameters() {
    // Test an existing path.
    $this->drupalGet('admin');
    $this->assertSession()->statusCodeEquals(200);

    $this->drupalGet('admin/structure/page_manager');
    // Add a new page with existing path 'admin'.
    $this->clickLink('Add page');
    $edit = [
      'label' => 'existing',
      'id' => 'existing',
      'path' => 'admin',
      'variant_plugin_id' => 'http_status_code',
    ];
    $this->submitForm($edit, 'Next');

    // Configure the variant.
    $edit = [
      'page_variant_label' => 'Status Code',
      'variant_settings[status_code]' => 404,
    ];
    $this->submitForm($edit, 'Finish');

    // Ensure the existing path leads to the new page.
    $this->drupalGet('admin');
    $this->assertSession()->statusCodeEquals(404);
  }

  /**
   * Tests the Update button on Variant forms.
   */
  protected function doTestUpdateSubmit() {
    // Add a block variant.
    $this->drupalGet('admin/structure/page_manager/manage/foo/general');

    // Add a new variant.
    $this->clickLink('Add variant');
    $edit = [
      'variant_plugin_id' => 'block_display',
      'label' => 'First',
    ];
    $this->submitForm($edit, 'Next');

    // Set the page title.
    $edit = [
      'variant_settings[page_title]' => 'Example title',
    ];
    $this->submitForm($edit, 'Next');

    // Finish variant wizard without adding blocks.
    $this->submitForm([], 'Finish');

    // Update the description and click on Update.
    $edit = [
      'page_variant_label' => 'First updated',
      'variant_settings[page_title]' => 'Example title updated',
    ];
    $this->drupalGet('admin/structure/page_manager/manage/foo/page_variant__foo-block_display-0__general');
    $this->submitForm($edit, 'Update');
    $this->assertSession()->fieldValueEquals('page_variant_label', 'First updated');
    $this->assertSession()->fieldValueEquals('variant_settings[page_title]', 'Example title updated');
    $this->drupalGet('admin/structure/page_manager/manage/foo/page_variant__foo-block_display-0__contexts');

    // Click on Update at Contexts. Nothing should happen.
    $this->submitForm([], 'Update');
    $this->assertSession()->addressEquals('admin/structure/page_manager/manage/foo/page_variant__foo-block_display-0__contexts');
    $this->drupalGet('admin/structure/page_manager/manage/foo/page_variant__foo-block_display-0__selection');

    // Click on Update at Selection criteria. Nothing should happen.
    $this->submitForm([], 'Update');
    $this->assertSession()->addressEquals('admin/structure/page_manager/manage/foo/page_variant__foo-block_display-0__selection');
    $this->drupalGet('admin/structure/page_manager/manage/foo/page_variant__foo-block_display-0__content');

    // Click on Update at Content. Nothing should happen.
    $this->submitForm([], 'Update');
    $this->assertSession()->addressEquals('admin/structure/page_manager/manage/foo/page_variant__foo-block_display-0__content');
  }

  /**
   * Tests deleting a page.
   */
  protected function doTestDeletePage() {
    $this->drupalGet('admin/structure/page_manager');
    $this->clickLink('Delete');
    $this->submitForm([], 'Delete');
    $this->assertSession()->responseContains(new FormattableMarkup('The page %name has been removed.', ['%name' => 'existing']));
    $this->drupalGet('admin');
    // The overridden page is back to its default.
    $this->assertSession()->statusCodeEquals(200);

    $this->drupalGet('admin/structure/page_manager');
    $this->clickLink('Delete');
    $this->submitForm([], 'Delete');
    $this->assertSession()->responseContains(new FormattableMarkup('The page %name has been removed.', ['%name' => 'Foo']));
    $this->drupalGet('admin/foo');
    // The custom page is no longer found.
    $this->assertSession()->statusCodeEquals(404);
  }

  /**
   * Tests that default arguments are not removed from existing routes.
   */
  public function testExistingRoutes() {
    // Test that the page without placeholder is accessible.
    $this->drupalGet('admin/structure/page_manager/add');
    $edit = [
      'label' => 'Placeholder test 2',
      'id' => 'placeholder2',
      'path' => '/page-manager-test',
      'variant_plugin_id' => 'http_status_code',
    ];
    $this->submitForm($edit, 'Next');

    $edit = [
      'variant_settings[status_code]' => 418,
    ];
    $this->submitForm($edit, 'Finish');
    $this->drupalGet('page-manager-test');
    $this->assertSession()->statusCodeEquals(418);

    // Test that the page test is accessible.
    $page_string = 'test-page';
    $this->drupalGet('page-manager-test/' . $page_string);
    $this->assertSession()->statusCodeEquals(200);

    // Without a single variant, it will fall through to the original.
    $this->drupalGet('admin/structure/page_manager/manage/placeholder2/page_variant__placeholder2-http_status_code-0__general');
    $this->clickLink('Delete this variant');
    $this->submitForm([], 'Delete');
    $this->submitForm([], 'Update and save');
    $this->drupalGet('page-manager-test');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Asserts that a theme was used for the page.
   *
   * @param string $theme_name
   *   The theme name.
   */
  protected function assertTheme($theme_name) {
    //$logo_url = Url::fromUri('base:core/themes/' . $theme_name . '/logo.svg')->toString();
    $elements = $this->xpath('//link[contains(@href, :url)]', [':url' => $theme_name]);
    $this->assertGreaterThanOrEqual(1, count($elements), new FormattableMarkup('Page is rendered in @theme', ['@theme' => $theme_name]));
  }

  /**
   * Finds a block based on its variant and block label.
   *
   * @param string $page_variant_id
   *   The ID of the page variant entity.
   * @param string $block_label
   *   The label of the block.
   *
   * @return \Drupal\Core\Block\BlockPluginInterface|null
   *   Either a block plugin, or NULL.
   */
  protected function findBlockByLabel($page_variant_id, $block_label) {
    /** @var \Drupal\page_manager\Entity\PageVariant $page_variant */
    if ($page_variant = PageVariant::load($page_variant_id)) {
      /** @var \Drupal\ctools\Plugin\BlockVariantInterface $variant_plugin */
      $variant_plugin = $page_variant->getVariantPlugin();
      foreach ($variant_plugin->getRegionAssignments() as $blocks) {
        /** @var \Drupal\Core\Block\BlockPluginInterface[] $blocks */
        foreach ($blocks as $block) {
          if ($block->label() == $block_label) {
            return $block;
          }
        }
      }
    }
    return NULL;
  }

}
