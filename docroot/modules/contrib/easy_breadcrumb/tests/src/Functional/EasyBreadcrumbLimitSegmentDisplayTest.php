<?php

declare(strict_types=1);

namespace Drupal\Tests\easy_breadcrumb\Functional;

use Drupal\easy_breadcrumb\EasyBreadcrumbConstants;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the LIMIT_SEGMENT_DISPLAY configuration.
 */
#[Group('easy_breadcrumb')]
class EasyBreadcrumbLimitSegmentDisplayTest extends EasyBreadcrumbBrowserTestBase {

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
   * Tests the LIMIT_SEGMENT_DISPLAY configuration.
   */
  public function testEasyBreadcrumbLimitSegmentDisplay() {
    // Tests that second breadcrumb is the page title with the config unset.
    $this->easyBreadcrumbSetConfig(EasyBreadcrumbConstants::LIMIT_SEGMENT_DISPLAY, FALSE);
    $this->drupalGet('test');
    $this->easyBreadcrumbAssertSegmentTextEquals(2, 'Test Page');

    // Tests that second breadcrumb is not there with the config set.
    $this->easyBreadcrumbSetConfig(EasyBreadcrumbConstants::LIMIT_SEGMENT_DISPLAY, TRUE);
    $this->drupalGet('test');
    $this->easyBreadcrumbAssertSegmentNotExists(2);
  }

}
