<?php

declare(strict_types=1);

namespace Drupal\Tests\easy_breadcrumb\Functional;

use Drupal\Core\Url;
use Drupal\easy_breadcrumb\EasyBreadcrumbConstants;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the ABSOLUTE_PATHS configuration.
 */
#[Group('easy_breadcrumb')]
class EasyBreadcrumbAbsolutePathsTest extends EasyBreadcrumbBrowserTestBase {

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
   * Tests the ABSOLUTE_PATHS configuration.
   */
  public function testEasyBreadcrumbAbsolutePaths() {
    $admin_absolute_url = $this->buildUrl('admin');

    $this->easyBreadcrumbSetConfig(EasyBreadcrumbConstants::ABSOLUTE_PATHS, TRUE);
    $this->drupalGet('admin/structure');

    // Tests that the link is absolute when the option is enabled.
    $this->assertSession()->elementExists(
      'css',
      '#block-breadcrumb li:nth-child(2) a[href="' . $admin_absolute_url . '"]',
    );

    $this->easyBreadcrumbSetConfig(EasyBreadcrumbConstants::ABSOLUTE_PATHS, FALSE);
    $this->drupalGet('admin/structure');

    // Tests that the link is relative when the option is disabled.
    $this->assertSession()->elementExists(
      'css',
      '#block-breadcrumb li:nth-child(2) a[href="' . Url::fromRoute('system.admin')->toString() . '"]',
    );

    // Tests that the link is not absolute when the option is disabled.
    $this->assertSession()->elementNotExists(
      'css',
      '#block-breadcrumb li:nth-child(2) a[href="' . $admin_absolute_url . '"]',
    );
  }

}
