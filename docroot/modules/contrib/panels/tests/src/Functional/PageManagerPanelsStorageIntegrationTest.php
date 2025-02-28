<?php

namespace Drupal\Tests\panels\Functional;

use Drupal\page_manager\Entity\PageVariant;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests integration between Page Manager and Panels Storage.
 *
 * @group panels
 */
class PageManagerPanelsStorageIntegrationTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['block', 'page_manager', 'page_manager_ui', 'panels_test', 'panels_ipe'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalPlaceBlock('local_tasks_block');
    $this->drupalPlaceBlock('local_actions_block');
    $this->drupalPlaceBlock('system_branding_block');
    $this->drupalPlaceBlock('page_title_block');

    \Drupal::service('theme_installer')->install(['olivero', 'claro']);
    $this->config('system.theme')->set('admin', 'claro')->save();

    $this->drupalLogin($this->drupalCreateUser(['administer pages', 'access administration pages', 'view the administration theme']));
  }

  /**
   * Tests creating a Panels variant with the IPE.
   */
  public function testPanelsIPE() {
    // Create new page.
    $this->drupalGet('admin/structure/page_manager/add');
    $edit = [
      'id' => 'foo',
      'label' => 'foo',
      'path' => 'testing',
      'variant_plugin_id' => 'panels_variant',
    ];
    $this->submitForm($edit, 'Next');

    // Add a Panels variant which uses the IPE.
    $edit = [
      // This option won't be present at all if our integration isn't working!
      'variant_settings[builder]' => 'ipe',
    ];
    $this->submitForm($edit, 'Next');

    // Choose a layout.
    $edit = [
      'layout' => 'layout_twocol',
    ];
    $this->submitForm($edit, 'Next');

    // In Drupal 8.8 and later, the layout may have settings of its own. If
    // that's the case, submit the layout settings form without any changes.
    $form = $this->getSession()->getPage()->find('css', '#panels-layout-settings-form');
    if ($form) {
      $form->pressButton('Next');
    }

    // Finish without adding any blocks.
    $this->submitForm([], 'Finish');

    /** @var \Drupal\page_manager\PageVariantInterface $page_variant */
    $page_variant = PageVariant::load('foo-panels_variant-0');
    /** @var \Drupal\panels\Plugin\DisplayVariant\PanelsDisplayVariant $panels_display */
    $panels_display = $page_variant->getVariantPlugin();

    // Make sure the storage type and id were set to the right value.
    $this->assertEquals($panels_display->getStorageType(), 'page_manager');
    $this->assertEquals($panels_display->getStorageId(), 'foo-panels_variant-0');
  }

}
