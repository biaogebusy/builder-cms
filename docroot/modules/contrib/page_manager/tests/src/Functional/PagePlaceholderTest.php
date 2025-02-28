<?php

namespace Drupal\Tests\page_manager\Functional;

use Drupal\page_manager\Entity\Page;
use Drupal\page_manager\Entity\PageVariant;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests selecting page variants based on nodes.
 *
 * @group page_manager
 */
class PagePlaceholderTest extends BrowserTestBase {

  use PageTestHelperTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['page_manager', 'page_manager_test'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalLogin($this->drupalCreateUser(['administer pages']));
  }

  /**
   * Tests that a node bundle condition controls the node view page.
   */
  public function testPagePlaceHolder() {
    // Access the page callback and check whether string is printed.
    $page_string = 'test-page';
    $this->drupalGet('page-manager-test/' . $page_string);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseHeaderContains('X-Drupal-Cache-Tags', 'page_manager_route_name:page_manager_test.page_view');
    $this->assertSession()->pageTextContains('Hello World! Page ' . $page_string);

    // Create a new page entity with the same path as in the test module.
    $page = Page::create([
      'label' => 'Placeholder test',
      'id' => 'placeholder',
      'path' => '/page-manager-test/%',
    ]);
    $page->save();

    // Create a new variant.
    /* @var $http_status_variant \Drupal\page_manager\Entity\PageVariant */
    $http_status_variant = PageVariant::create([
      'label' => 'HTTP status code',
      'id' => 'http_status_code',
      'page' => 'placeholder',
    ]);

    // Test setting variant post create works.
    $http_status_variant->setVariantPluginId('http_status_code');

    $http_status_variant->getVariantPlugin()->setConfiguration(['status_code' => 200]);
    $http_status_variant->save();
    $this->triggerRouterRebuild();

    // Access the page callback again and check that now the text is not there.
    $this->drupalGet('page-manager-test/' . $page_string);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseHeaderContains('X-Drupal-Cache-Tags', 'page_manager_route_name:page_manager_test.page_view');
    $this->assertSession()->pageTextNotContains('Hello World! Page ' . $page_string);
  }

}
