<?php

declare(strict_types=1);

namespace Drupal\Tests\easy_breadcrumb\Functional;

use Drupal\easy_breadcrumb\EasyBreadcrumbConstants;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the USE_SITE_TITLE configuration.
 */
#[Group('easy_breadcrumb')]
class EasyBreadcrumbUseSiteTitleTest extends EasyBreadcrumbBrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'easy_breadcrumb',
    'block',
  ];

  /**
   * Tests the USE_SITE_TITLE configuration.
   */
  public function testEasyBreadcrumbUseSiteTitle() {
    $this->easyBreadcrumbCreateAndLoginUser();
    $site_name = 'Test Site Name';

    $this->config('system.site')
      ->set('name', $site_name)
      ->save();

    // Tests that first breadcrumb is the default 'Home' with the config unset.
    $this->easyBreadcrumbSetConfig(EasyBreadcrumbConstants::USE_SITE_TITLE, FALSE);
    $this->drupalGet('<front>');
    $this->easyBreadcrumbAssertSegmentTextEquals(1, 'Home');

    // Tests that first breadcrumb is the site name with the config set.
    $this->easyBreadcrumbSetConfig(EasyBreadcrumbConstants::USE_SITE_TITLE, TRUE);
    $this->drupalGet('<front>');
    $this->easyBreadcrumbAssertSegmentTextEquals(1, $site_name);
  }

}
