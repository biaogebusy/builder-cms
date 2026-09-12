<?php

declare(strict_types=1);

namespace Drupal\Tests\easy_breadcrumb\Functional;

use Drupal\easy_breadcrumb\EasyBreadcrumbConstants;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests USE_MENU_TITLE_AS_FALLBACK and MENU_TITLE_PREFERRED_MENU config.
 */
#[Group('easy_breadcrumb')]
class EasyBreadcrumbMenuTitleTest extends EasyBreadcrumbBrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'easy_breadcrumb',
    'block',
    'menu_link_content',
    'node',
    'path',
  ];

  /**
   * The test node page title.
   */
  protected string $pageTitle;

  /**
   * The test node menu title.
   */
  protected string $menuTitle;

  /**
   * The test node path alias.
   */
  protected string $alias;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->pageTitle = 'Test Page';
    $this->menuTitle = 'Test Menu Title';
    $this->alias = '/menu-title-test-page';
    $this->easyBreadcrumbCreateAndLoginAdminUser();
    $this->drupalCreateContentType(['type' => 'page']);

    $this->drupalCreateNode([
      'type' => 'page',
      'title' => $this->pageTitle,
      'path' => [
        'alias' => $this->alias,
      ],
    ]);

    MenuLinkContent::create([
      'title' => $this->menuTitle,
      'link' => ['uri' => "internal:$this->alias"],
      'menu_name' => 'main',
      'weight' => 1,
    ])->save();

    $this->easyBreadcrumbSetConfig(EasyBreadcrumbConstants::MENU_TITLE_PREFERRED_MENU, 'main');
  }

  /**
   * Tests USE_MENU_TITLE_AS_FALLBACK and MENU_TITLE_PREFERRED_MENU config.
   */
  public function testEasyBreadcrumbMenuTitle() {
    // Asserts that when both TITLE_FROM_PAGE_WHEN_AVAILABLE and
    // USE_MENU_TITLE_AS_FALLBACK are enabled, the page title is
    // shown in the breadcrumb.
    $this->easyBreadcrumbSetConfig(EasyBreadcrumbConstants::TITLE_FROM_PAGE_WHEN_AVAILABLE, TRUE);
    $this->easyBreadcrumbSetConfig(EasyBreadcrumbConstants::USE_MENU_TITLE_AS_FALLBACK, TRUE);
    $this->drupalGet($this->alias);
    $this->easyBreadcrumbAssertSegmentTextEquals(2, $this->pageTitle);

    // Asserts that when TITLE_FROM_PAGE_WHEN_AVAILABLE is not enabled and
    // USE_MENU_TITLE_AS_FALLBACK is enabled, the menu title is
    // shown in the breadcrumb.
    $this->easyBreadcrumbSetConfig(EasyBreadcrumbConstants::TITLE_FROM_PAGE_WHEN_AVAILABLE, FALSE);
    $this->drupalGet($this->alias);
    $this->easyBreadcrumbAssertSegmentTextEquals(2, $this->menuTitle);
  }

}
