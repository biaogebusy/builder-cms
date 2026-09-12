<?php

declare(strict_types=1);

namespace Drupal\Tests\easy_breadcrumb\Functional;

use Drupal\easy_breadcrumb\EasyBreadcrumbConstants;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the INCLUDE_INVALID_PATHS configuration.
 */
#[Group('easy_breadcrumb')]
class EasyBreadcrumbIncludeInvalidPathsTest extends BrowserTestBase {

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
  protected $defaultTheme = 'stark';

  /**
   * Tests the INCLUDE_INVALID_PATHS configuration.
   */
  public function testIncludeInvalidPaths() {
    $this->placeBlock('system_breadcrumb_block', ['id' => 'breadcrumb']);

    $this->drupalCreateContentType([
      'type' => 'page',
      'name' => 'Page',
    ]);

    $this->drupalCreateNode([
      'type' => 'page',
      'title' => 'Test Page 1',
      'status' => 1,
      'path' => [
        'alias' => '/test/invalid/paths',
      ],
    ]);

    $this->config(EasyBreadcrumbConstants::MODULE_SETTINGS)
      ->set(EasyBreadcrumbConstants::INCLUDE_INVALID_PATHS, TRUE)
      ->save();

    $this->drupalGet('test/invalid/paths');

    // Tests that first breadcrumb is "Home".
    $this->assertSession()->elementContains(
      'css',
      '#block-breadcrumb li:first-child',
      'Home',
    );
    // Tests that second breadcrumb is "Test".
    $this->assertSession()->elementContains(
      'css',
      '#block-breadcrumb li:nth-child(2)',
      'Test',
    );
    // Tests that third breadcrumb is "Invalid".
    $this->assertSession()->elementContains(
      'css',
      '#block-breadcrumb li:nth-child(3)',
      'Invalid',
    );
    // Tests that fourth breadcrumb is the page title.
    $this->assertSession()->elementContains(
      'css',
      '#block-breadcrumb li:nth-child(4)',
      'Test Page 1',
    );

  }

}
