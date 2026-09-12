<?php

declare(strict_types=1);

namespace Drupal\Tests\easy_breadcrumb\Functional;

use Drupal\easy_breadcrumb\EasyBreadcrumbConstants;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the INCLUDE_TITLE_SEGMENT configuration.
 */
#[Group('easy_breadcrumb')]
class EasyBreadcrumbIncludeTitleSegmentTest extends EasyBreadcrumbBrowserTestBase {

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
   * Tests the INCLUDE_TITLE_SEGMENT configuration.
   */
  public function testIncludeTitleSegment() {
    // Tests that second breadcrumb is "Administration" with the config set.
    $this->easyBreadcrumbSetConfig(EasyBreadcrumbConstants::INCLUDE_TITLE_SEGMENT, TRUE);
    $this->drupalGet('admin');
    $this->easyBreadcrumbAssertSegmentTextEquals(2, 'Administration');

    // Tests that second breadcrumb is not there with the config unset.
    $this->easyBreadcrumbSetConfig(EasyBreadcrumbConstants::INCLUDE_TITLE_SEGMENT, FALSE);
    $this->drupalGet('admin');
    $this->easyBreadcrumbAssertSegmentNotExists(2);
  }

}
