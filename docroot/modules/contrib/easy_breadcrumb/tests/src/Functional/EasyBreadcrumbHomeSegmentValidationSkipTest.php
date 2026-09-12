<?php

declare(strict_types=1);

namespace Drupal\Tests\easy_breadcrumb\Functional;

use Drupal\easy_breadcrumb\EasyBreadcrumbConstants;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the HOME_SEGMENT_VALIDATION_SKIP configuration.
 */
#[Group('easy_breadcrumb')]
class EasyBreadcrumbHomeSegmentValidationSkipTest extends EasyBreadcrumbBrowserTestBase {

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
    $this->drupalCreateContentType(['type' => 'page', 'name' => 'Page']);

    $this->drupalCreateNode([
      'type' => 'page',
      'title' => 'Front page',
      'path' => [
        'alias' => '/front-page',
      ],
    ]);

    $this->config('system.site')
      ->set('page.front', '/front-page')
      ->save();

    $this->drupalCreateNode([
      'type' => 'page',
      'title' => 'Child page',
      'path' => [
        'alias' => '/front-page/child-page',
      ],
    ]);
  }

  /**
   * Tests paths that resolve to the front page.
   */
  public function testHomeSegmentValidationSkip() {
    $this->drupalGet('front-page/child-page');
    $this->easyBreadcrumbAssertSegmentTextEquals(2, 'Child Page');

    $this->easyBreadcrumbSetConfig(EasyBreadcrumbConstants::HOME_SEGMENT_VALIDATION_SKIP, TRUE);
    $this->drupalGet('front-page/child-page');
    $this->easyBreadcrumbAssertSegmentTextEquals(2, 'Front Page');
    $this->easyBreadcrumbAssertSegmentTextEquals(3, 'Child Page');
  }

}
