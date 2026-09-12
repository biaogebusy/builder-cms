<?php

declare(strict_types=1);

namespace Drupal\Tests\easy_breadcrumb\Functional;

use Drupal\easy_breadcrumb\EasyBreadcrumbConstants;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the INCLUDE_HOME_SEGMENT configuration.
 */
#[Group('easy_breadcrumb')]
class EasyBreadcrumbIncludeHomeSegmentTest extends EasyBreadcrumbBrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'easy_breadcrumb',
    'block',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->easyBreadcrumbCreateAndLoginAdminUser();
  }

  /**
   * Tests the INCLUDE_HOME_SEGMENT configuration.
   */
  public function testIncludeHomeSegment() {
    $this->easyBreadcrumbSetConfig(EasyBreadcrumbConstants::INCLUDE_HOME_SEGMENT, TRUE);
    $this->drupalGet('admin');
    $this->easyBreadcrumbAssertSegmentTextEquals(1, 'Home');

    $this->easyBreadcrumbSetConfig(EasyBreadcrumbConstants::INCLUDE_HOME_SEGMENT, FALSE);
    $this->drupalGet('admin');
    $this->easyBreadcrumbAssertSegmentTextEquals(1, 'Administration');
  }

}
