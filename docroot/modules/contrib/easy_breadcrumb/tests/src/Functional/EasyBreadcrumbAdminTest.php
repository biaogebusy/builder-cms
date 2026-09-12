<?php

declare(strict_types=1);

namespace Drupal\Tests\easy_breadcrumb\Functional;

use Drupal\easy_breadcrumb\EasyBreadcrumbConstants;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests easy_breadcrumb admin routes.
 */
#[Group('easy_breadcrumb')]
class EasyBreadcrumbAdminTest extends EasyBreadcrumbBrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'easy_breadcrumb',
    'block',
  ];

  /**
   * Tests the APPLIES_ADMIN_ROUTES configuration option.
   */
  public function testAppliesAdminRoutes() {
    $this->easyBreadcrumbCreateAndLoginAdminUser();

    // Tests that the current page breadcrumb shows on admin routes when the
    // configuration option is used.
    $this->easyBreadcrumbSetConfig(EasyBreadcrumbConstants::APPLIES_ADMIN_ROUTES, TRUE);
    $this->drupalGet('admin');
    $this->easyBreadcrumbAssertSegmentTextEquals(2, 'Administration');

    // Tests that the current page breadcrumb does not show on admin routes
    // when the configuration option is not used.
    $this->config(EasyBreadcrumbConstants::MODULE_SETTINGS)
      ->set(EasyBreadcrumbConstants::APPLIES_ADMIN_ROUTES, FALSE)
      ->save();
    $this->drupalGet('admin');
    $this->easyBreadcrumbAssertSegmentNotExists(2);
  }

}
