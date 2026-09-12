<?php

declare(strict_types=1);

namespace Drupal\Tests\easy_breadcrumb\Functional;

use Drupal\easy_breadcrumb\EasyBreadcrumbConstants;
use Drupal\node\Entity\Node;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the HIDE_SINGLE_HOME_ITEM configuration.
 */
#[Group('easy_breadcrumb')]
class EasyBreadcrumbHideSingleHomeItemTest extends EasyBreadcrumbBrowserTestBase {

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
   * The testing node.
   */
  protected Node $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->easyBreadcrumbCreateAndLoginAdminUser();
    $this->drupalCreateContentType(['type' => 'page']);

    $this->node = $this->drupalCreateNode([
      'title' => 'Test Page',
      'type' => 'page',
      'path' => ['alias' => '/test-page'],
    ]);

    $this->easyBreadcrumbSetConfig(EasyBreadcrumbConstants::EXCLUDED_PATHS, 'test-page');
  }

  /**
   * Tests the HIDE_SINGLE_HOME_ITEM configuration.
   */
  public function testEasyBreadcrumbHideSingleHomeItem() {
    // Tests that Home is the first segment with the config unset.
    $this->easyBreadcrumbSetConfig(EasyBreadcrumbConstants::HIDE_SINGLE_HOME_ITEM, FALSE);
    $this->drupalGet($this->node->toUrl());
    $this->easyBreadcrumbAssertSegmentTextEquals(1, 'Home');
    $this->easyBreadcrumbAssertSegmentNotExists(2);

    // Tests that there are no crumbs with the config set.
    $this->easyBreadcrumbSetConfig(EasyBreadcrumbConstants::HIDE_SINGLE_HOME_ITEM, TRUE);
    $this->drupalGet($this->node->toUrl());
    $this->easyBreadcrumbAssertBreadcrumbNotExists();

    // Tests that there are still crumbs visible on a deeper page with multiple
    // crumbs.
    $this->drupalGet('admin');
    $this->easyBreadcrumbAssertSegmentTextEquals(1, 'Home');
    $this->easyBreadcrumbAssertSegmentTextEquals(2, 'Administration');
  }

}
