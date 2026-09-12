<?php

declare(strict_types=1);

namespace Drupal\Tests\easy_breadcrumb\Functional;

use Drupal\easy_breadcrumb\EasyBreadcrumbConstants;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the HOME_SEGMENT_KEEP configuration.
 */
#[Group('easy_breadcrumb')]
class EasyBreadcrumbHomeSegmentKeepTest extends EasyBreadcrumbBrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'easy_breadcrumb',
    'block',
    'node',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->easyBreadcrumbCreateAndLoginAdminUser();
    $this->drupalCreateContentType(['type' => 'page']);

    $node = $this->drupalCreateNode([
      'type' => 'page',
      'title' => 'Front Page',
    ]);

    $this->config('system.site')
      ->set('page.front', '/node/' . $node->id())
      ->save();
  }

  /**
   * Tests the HOME_SEGMENT_KEEP configuration.
   */
  public function testEasyBreadcrumbHomeSegmentKeep() {
    // Tests that breadcrumb block is empty with the config unset.
    $this->easyBreadcrumbSetConfig(EasyBreadcrumbConstants::HOME_SEGMENT_KEEP, FALSE);
    $this->drupalGet('<front>');
    $this->easyBreadcrumbAssertBreadcrumbNotExists();

    // Tests that first breadcrumb is Home with the config set.
    $this->easyBreadcrumbSetConfig(EasyBreadcrumbConstants::HOME_SEGMENT_KEEP, TRUE);
    $this->drupalGet('<front>');
    $this->easyBreadcrumbAssertSegmentTextEquals(1, 'Home');
  }

}
