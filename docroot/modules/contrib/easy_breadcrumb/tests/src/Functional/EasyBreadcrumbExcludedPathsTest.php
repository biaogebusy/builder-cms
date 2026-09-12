<?php

declare(strict_types=1);

namespace Drupal\Tests\easy_breadcrumb\Functional;

use Drupal\easy_breadcrumb\EasyBreadcrumbConstants;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the EXCLUDED_PATHS configuration.
 */
#[Group('easy_breadcrumb')]
class EasyBreadcrumbExcludedPathsTest extends EasyBreadcrumbBrowserTestBase {

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
      'title' => 'Test Page',
      'path' => ['alias' => '/test'],
    ]);
  }

  /**
   * Tests the EXCLUDED_PATHS configuration.
   */
  public function testEasyBreadcrumbExcludedPaths() {
    $this->easyBreadcrumbSetConfig(EasyBreadcrumbConstants::EXCLUDED_PATHS, '');
    $this->drupalGet('test');
    $this->easyBreadcrumbAssertSegmentTextEquals(2, 'Test Page');

    $this->easyBreadcrumbSetConfig(EasyBreadcrumbConstants::EXCLUDED_PATHS, 'test');
    $this->drupalGet('test');
    $this->easyBreadcrumbAssertSegmentNotExists(2);
  }

}
