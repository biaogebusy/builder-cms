<?php

declare(strict_types=1);

namespace Drupal\Tests\easy_breadcrumb\Functional;

use Drupal\easy_breadcrumb\EasyBreadcrumbConstants;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the HOME_SEGMENT_TITLE configuration.
 */
#[Group('easy_breadcrumb')]
class EasyBreadcrumbHomeSegmentTitleTest extends EasyBreadcrumbBrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'easy_breadcrumb',
    'block',
  ];

  /**
   * Tests the HOME_SEGMENT_TITLE configuration.
   */
  public function testHomeSegmentTitle() {
    // Tests that the default first breadcrumb is "Home".
    $this->easyBreadcrumbCreateAndLoginAdminUser();
    $this->drupalGet('admin');
    $this->easyBreadcrumbAssertSegmentTextEquals(1, 'Home');

    // Tests that first breadcrumb matches changed config.
    $this->easyBreadcrumbSetConfig(EasyBreadcrumbConstants::HOME_SEGMENT_TITLE, 'Front');
    $this->drupalGet('admin');
    $this->easyBreadcrumbAssertSegmentTextEquals(1, 'Front');
  }

}
