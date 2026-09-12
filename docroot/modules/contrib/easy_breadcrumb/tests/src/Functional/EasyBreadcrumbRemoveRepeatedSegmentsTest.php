<?php

declare(strict_types=1);

namespace Drupal\Tests\easy_breadcrumb\Functional;

use Drupal\easy_breadcrumb\EasyBreadcrumbConstants;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the REMOVE_REPEATED_SEGMENTS configuration.
 *
 * Also tests REMOVE_REPEATED_SEGMENTS_TEXT_ONLY.
 */
#[Group('easy_breadcrumb')]
class EasyBreadcrumbRemoveRepeatedSegmentsTest extends EasyBreadcrumbBrowserTestBase {

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
    $this->drupalCreateContentType(['type' => 'page', 'name' => 'Page']);

    // Creates three nodes; two for the first and second path segments and one
    // for the last path segment. This allows testing both
    // REMOVE_REPEATED_SEGMENTS and REMOVE_REPEATED_SEGMENTS_TEXT_ONLY.
    $this->drupalCreateNode([
      'type' => 'page',
      'title' => 'Test Page',
      'path' => [
        'alias' => '/test',
      ],
    ]);
    $this->drupalCreateNode([
      'type' => 'page',
      'title' => 'Test Page',
      'path' => [
        'alias' => '/test/test',
      ],
    ]);
    $this->drupalCreateNode([
      'type' => 'page',
      'title' => 'Test Page',
      'path' => [
        'alias' => '/test/test/repeated/repeated/repeated/segments/segments/test-page',
      ],
    ]);
    $this->easyBreadcrumbSetConfig(EasyBreadcrumbConstants::INCLUDE_INVALID_PATHS, TRUE);
  }

  /**
   * Checks an array of crumbs.
   *
   * @param array $expectedCrumbs
   *   The names of the expected crumbs.
   */
  protected function easyBreadcrumbCheckCrumbs(array $expectedCrumbs) {
    foreach ($expectedCrumbs as $expectedCrumbKey => $expectedCrumb) {
      $crumbPosition = $expectedCrumbKey + 1;
      $this->easyBreadcrumbAssertSegmentTextEquals($crumbPosition, $expectedCrumb);
    }
  }

  /**
   * Tests the REMOVE_REPEATED_SEGMENTS configuration.
   *
   * Also tests REMOVE_REPEATED_SEGMENTS_TEXT_ONLY.
   */
  public function testRemoveRepeatedSegments() {
    // Tests that repeated segments are not removed with the config unset.
    $this->easyBreadcrumbSetConfig(EasyBreadcrumbConstants::REMOVE_REPEATED_SEGMENTS, FALSE);
    $this->drupalGet('test/test/repeated/repeated/repeated/segments/segments/test-page');
    $expectedCrumbs = [
      'Home',
      'Test Page',
      'Test Page',
      'Repeated',
      'Repeated',
      'Repeated',
      'Segments',
      'Segments',
      'Test Page',
    ];
    $this->easyBreadcrumbCheckCrumbs($expectedCrumbs);

    // Tests that repeated segments are removed, but segments that represent
    // real paths are kept.
    $this->easyBreadcrumbSetConfig(EasyBreadcrumbConstants::REMOVE_REPEATED_SEGMENTS, TRUE);
    $this->easyBreadcrumbSetConfig(EasyBreadcrumbConstants::REMOVE_REPEATED_SEGMENTS_TEXT_ONLY, FALSE);
    $this->drupalGet('test/test/repeated/repeated/repeated/segments/segments/test-page');
    $expectedCrumbs = [
      'Home',
      'Test Page',
      'Test Page',
      'Repeated',
      'Segments',
      'Test Page',
    ];
    $this->easyBreadcrumbCheckCrumbs($expectedCrumbs);

    // Tests that repeated segments are removed no matter if any of the paths
    // are real.
    $this->easyBreadcrumbSetConfig(EasyBreadcrumbConstants::REMOVE_REPEATED_SEGMENTS_TEXT_ONLY, TRUE);
    $this->drupalGet('test/test/repeated/repeated/repeated/segments/segments/test-page');
    $expectedCrumbs = [
      'Home',
      'Test Page',
      'Repeated',
      'Segments',
      'Test Page',
    ];
    $this->easyBreadcrumbCheckCrumbs($expectedCrumbs);
  }

}
