<?php

declare(strict_types=1);

namespace Drupal\Tests\easy_breadcrumb\Functional;

use Drupal\easy_breadcrumb\EasyBreadcrumbConstants;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the TITLE_SEGMENT_AS_LINK configuration.
 */
#[Group('easy_breadcrumb')]
class EasyBreadcrumbTitleSegmentAsLinkTest extends EasyBreadcrumbBrowserTestBase {

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
   * Tests the TITLE_SEGMENT_AS_LINK configuration.
   */
  public function testEasyBreadcrumbTitleSegmentAsLink() {
    $this->drupalGet('admin');

    // Tests that second breadcrumb is not a link.
    $this->assertSession()->elementNotExists(
      'css',
      '#block-breadcrumb li:nth-child(2) a',
    );

    $this->easyBreadcrumbSetConfig(EasyBreadcrumbConstants::TITLE_SEGMENT_AS_LINK, TRUE);
    $this->drupalGet('admin');

    // Tests that second breadcrumb is a link containing the Page Title.
    $this->assertSession()->elementContains(
      'css',
      '#block-breadcrumb li:nth-child(2) a',
      'Administration',
    );
  }

}
