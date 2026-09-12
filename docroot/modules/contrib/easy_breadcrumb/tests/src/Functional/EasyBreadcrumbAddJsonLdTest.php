<?php

declare(strict_types=1);

namespace Drupal\Tests\easy_breadcrumb\Functional;

use Drupal\easy_breadcrumb\EasyBreadcrumbConstants;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the ADD_STRUCTURED_DATA_JSON_LD configuration.
 */
#[Group('easy_breadcrumb')]
class EasyBreadcrumbAddJsonLdTest extends EasyBreadcrumbBrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'easy_breadcrumb',
    'block',
  ];

  /**
   * Tests the ADD_STRUCTURED_DATA_JSON_LD configuration.
   */
  public function testEasyBreadcrumbAddJsonLd() {
    $this->easyBreadcrumbCreateAndLoginUser();

    $this->easyBreadcrumbSetConfig(EasyBreadcrumbConstants::ADD_STRUCTURED_DATA_JSON_LD, FALSE);
    $this->drupalGet('<front>');

    // Assert that if the configuration is not set, the ld+json element is not
    // added to the HTML head.
    $this->assertSession()->elementNotExists(
      'css',
      'head script[type="application/ld+json"]',
    );

    $this->easyBreadcrumbSetConfig(EasyBreadcrumbConstants::ADD_STRUCTURED_DATA_JSON_LD, TRUE);
    $this->drupalGet('<front>');

    // Assert that the ld+json element is added to the HTML head.
    $this->assertSession()->elementContains(
      'css',
      'head script[type="application/ld+json"]',
      '"@type": "BreadcrumbList"',
    );

  }

}
