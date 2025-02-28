<?php

namespace Drupal\Tests\panels\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\user\Entity\User;

/**
 * Tests using PanelsVariant with page_manager.
 *
 * @group panels
 */
class PanelsTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['block', 'page_manager', 'page_manager_ui', 'panels_test'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalPlaceBlock('local_tasks_block');
    $this->drupalPlaceBlock('local_actions_block');
    $this->drupalPlaceBlock('system_branding_block');
    $this->drupalPlaceBlock('page_title_block');

    $this->drupalLogin($this->drupalCreateUser(['administer pages', 'access administration pages', 'view the administration theme']));
  }

  /**
   * Tests adding a layout with settings.
   */
  public function testLayoutSettings() {
    // Create new page.
    $this->drupalGet('admin/structure/page_manager/add');
    $edit = [
      'id' => 'foo',
      'label' => 'foo',
      'path' => 'testing',
      'variant_plugin_id' => 'panels_variant',
    ];
    $this->submitForm($edit, 'Next');

    // Add variant with a layout that has settings.
    $edit = [
      'page_variant_label' => 'Default',
    ];
    $this->submitForm($edit, 'Next');

    // Choose a layout.
    $edit = [
      'layout' => 'layout_example_test',
    ];
    $this->submitForm($edit, 'Next');

    // Update the layout's settings.
    $this->assertSession()->fieldValueEquals('layout_settings_wrapper[layout_settings][setting_1]', 'Default');
    $edit = [
      'layout_settings_wrapper[layout_settings][setting_1]' => 'Abracadabra',
    ];
    $this->submitForm($edit, 'Next');

    // Add a block.
    $this->clickLink('Add new block');
    $this->clickLink('Powered by Drupal');
    $edit = [
      'region' => 'top',
    ];
    $this->submitForm($edit, 'Add block');

    // Finish the page add wizard.
    $this->submitForm([], 'Finish');

    // View the page and make sure the setting is present.
    $this->drupalGet('testing');
    $this->assertSession()->pageTextContains('Blah:');
    $this->assertSession()->pageTextContains('Abracadabra');
    $this->assertSession()->pageTextContains('Powered by Drupal');
  }

  /**
   * Tests that special characters are not escaped when using tokens in titles.
   */
  public function testPageTitle() {
    // Change the logged in user's name to include a special character.
    $user = User::load($this->loggedInUser->id());
    $user->setUsername("My User's Name");
    $user->save();

    // Create new page.
    $this->drupalGet('admin/structure/page_manager/add');
    $edit = [
      'id' => 'foo',
      'label' => 'foo',
      'path' => 'testing',
      'variant_plugin_id' => 'panels_variant',
    ];
    $this->submitForm($edit, 'Next');

    // Use default variant settings.
    $edit = [
      'page_variant_label' => 'Default',
    ];
    $this->submitForm($edit, 'Next');

    // Choose a simple layout.
    $edit = [
      'layout' => 'layout_onecol',
    ];
    $this->submitForm($edit, 'Next');

    // In Drupal 8.8 and later, the layout may have settings of its own. If
    // that's the case, submit the layout settings form without any changes.
    $form = $this->getSession()->getPage()->find('css', '#panels-layout-settings-form');
    if ($form) {
      $form->pressButton('Next');
    }

    // Set the title to a token value that includes an apostrophe.
    $edit = [
      'page_title' => '[user:name]',
    ];
    $this->submitForm($edit, 'Finish');

    // View the page and make sure the page title is valid.
    $this->drupalGet('testing');
    // We expect "'" to be escaped only once, which is why we're doing a raw
    // assertion here.
    $this->assertSession()->responseContains('<h1>My User&#039;s Name</h1>');
  }

}
