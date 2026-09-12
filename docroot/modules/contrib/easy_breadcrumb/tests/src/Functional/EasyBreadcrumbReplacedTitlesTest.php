<?php

declare(strict_types=1);

namespace Drupal\Tests\easy_breadcrumb\Functional;

use Drupal\easy_breadcrumb\EasyBreadcrumbConstants;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the REPLACED_TITLES configuration.
 */
#[Group('easy_breadcrumb')]
class EasyBreadcrumbReplacedTitlesTest extends EasyBreadcrumbBrowserTestBase {

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
      'title' => 'Test',
      'path' => ['alias' => '/test'],
    ]);
  }

  /**
   * Tests the REPLACED_TITLES configuration.
   */
  public function testEasyBreadcrumbReplacedTitles() {
    // Tests that second breadcrumb is the replaced title.
    $this->easyBreadcrumbSetConfig(EasyBreadcrumbConstants::REPLACED_TITLES, 'Test::Replaced');
    $this->drupalGet('test');
    $this->easyBreadcrumbAssertSegmentTextEquals(2, 'Replaced');
  }

}
