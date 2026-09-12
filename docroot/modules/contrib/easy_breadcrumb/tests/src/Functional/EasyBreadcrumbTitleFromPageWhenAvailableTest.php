<?php

declare(strict_types=1);

namespace Drupal\Tests\easy_breadcrumb\Functional;

use Drupal\easy_breadcrumb\EasyBreadcrumbConstants;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the TITLE_FROM_PAGE_WHEN_AVAILABLE configuration.
 */
#[Group('easy_breadcrumb')]
class EasyBreadcrumbTitleFromPageWhenAvailableTest extends EasyBreadcrumbBrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'easy_breadcrumb',
    'block',
    'node',
    'path',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->easyBreadcrumbCreateAndLoginAdminUser();

    $this->drupalCreateContentType(['type' => 'page']);
    $this->drupalCreateNode([
      'type' => 'page',
      'title' => 'Page Title',
      'path' => ['alias' => '/breadcrumb'],
    ]);
  }

  /**
   * Tests the TITLE_FROM_PAGE_WHEN_AVAILABLE configuration.
   */
  public function testTitleFromPageWhenAvailable() {
    $this->easyBreadcrumbSetConfig(EasyBreadcrumbConstants::TITLE_FROM_PAGE_WHEN_AVAILABLE, FALSE);
    $this->easyBreadcrumbSetConfig(EasyBreadcrumbConstants::CUSTOM_PATHS, '/breadcrumb::<title>Test');
    $this->drupalGet('breadcrumb');

    // Tests that the second breadcrumb removes the <title> string, leaving
    // only "Test".
    $this->easyBreadcrumbAssertSegmentTextEquals(2, 'Test');

    $this->easyBreadcrumbSetConfig(EasyBreadcrumbConstants::TITLE_FROM_PAGE_WHEN_AVAILABLE, TRUE);
    $this->drupalGet('breadcrumb');

    // Tests that the second breadcrumb correctly replaces the <title> string.
    $this->easyBreadcrumbAssertSegmentTextEquals(2, 'Page TitleTest');
  }

}
