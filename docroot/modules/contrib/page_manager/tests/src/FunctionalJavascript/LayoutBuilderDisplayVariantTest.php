<?php

namespace Drupal\Tests\page_manager\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\contextual\FunctionalJavascript\ContextualLinkClickTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Test the layout_builder variant plugin.
 *
 * @group page_manager
 */
class LayoutBuilderDisplayVariantTest extends WebDriverTestBase {

  use UserCreationTrait;
  use ContextualLinkClickTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'classy';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'layout_builder',
    'page_manager_ui',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalLogin($this->createUser(array_keys($this->container->get('user.permissions')
      ->getPermissions())));
  }

  /**
   * Tests the layout builder variant.
   */
  public function testLayoutBuilderVariant() {
    $this->drupalGet('admin/structure/page_manager/add');
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    $page->fillField('label', 'Example');
    $assert_session->waitForElementVisible('css', '.machine-name-value');
    $this->submitForm([
      'path' => '/test-page',
      'variant_plugin_id' => 'layout_builder',
    ], t('Next'));

    $this->submitForm([
      'page_variant_label' => 'Layout Builder',
    ], t('Next'));

    // Add a two column section.
    $page->find('css', '.layout-builder__link--add')->click();
    $assert_session->waitForElementVisible('named', ['link', 'One column']);
    $this->clickLink('Two column');
    $assert_session->assertWaitOnAjaxRequest();
    $page->pressButton('Add section');
    $assert_session->assertWaitOnAjaxRequest();

    // Place a block in the first region.
    $page->find('css', '.layout__region--first .layout-builder__link--add')
      ->click();
    $assert_session->assertWaitOnAjaxRequest();
    $this->clickLink('Powered by Drupal');
    $assert_session->assertWaitOnAjaxRequest();
    $page->find('css', 'form.layout-builder-add-block .form-submit')->click();
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->pageTextContains('Powered by Drupal');

    // Test content preview.
    $page->uncheckField('toggle_content_preview');
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', '.layout-builder-block__content-preview-placeholder-label'));

    $page->pressButton('Finish');
    $assert_session->pageTextContains('The layout has been saved.');

    // Go the layout builder variant.
    $this->drupalGet('admin/structure/page_manager/manage/example/page_variant__example-layout_builder-0__layout_builder');

    // Place another block in the first region.
    $page->find('css', '.layout__region--second .layout-builder__link--add')
      ->click();
    $assert_session->assertWaitOnAjaxRequest();
    $this->clickLink('User account menu');
    $assert_session->assertWaitOnAjaxRequest();
    $page->find('css', 'form.layout-builder-add-block .form-submit')->click();
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->pageTextContains('User account menu');

    // Place user view block.
    $page->find('css', '.layout__region--second .layout-builder__link--add')
      ->click();
    $assert_session->assertWaitOnAjaxRequest();
    $this->clickLink('Entity view (User)');
    $assert_session->assertWaitOnAjaxRequest();
    $page->find('css', 'form.layout-builder-add-block .form-submit')->click();
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->pageTextContains('Entity view (User)');

    // Test contextual links.
    $assert_session->waitForElement('css', '.block-system-powered-by-block .contextual');
    $contextual_link_class = version_compare(\Drupal::VERSION, '9.4', '<')
      ? '.block-system-powered-by-block'
      : '.layout-builder-block.contextual-region';
    $this->clickContextualLink($contextual_link_class, 'Configure');
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', '#drupal-off-canvas'));

    // Save page.
    $page->pressButton('Update and save');

    // Check if block is rendered in the correct region.
    $this->drupalGet('test-page');
    $assert_session->waitForElementVisible('css', '.layout__region--first');
    $assert_session->elementTextContains('css', '.layout__region--first', 'Powered by Drupal');
    $assert_session->waitForElementVisible('css', '.layout__region--second');
    $assert_session->elementTextContains('css', '.layout__region--second', 'User account menu');
    $assert_session->elementTextContains('css', '.layout__region--second', 'Entity view (User)');

    // Check that dependencies are saved.
    $dependencies = $this->config('page_manager.page_variant.example-layout_builder-0')->get('dependencies');
    $this->assertContains('ctools', $dependencies['module']);
  }

}
