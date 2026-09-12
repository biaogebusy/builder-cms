<?php

declare(strict_types=1);

namespace Drupal\Tests\easy_breadcrumb\Functional;

use Drupal\easy_breadcrumb\EasyBreadcrumbConstants;
use Drupal\node\Entity\Node;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the TRUNCATOR_MODE configuration.
 */
#[Group('easy_breadcrumb')]
class EasyBreadcrumbTruncatorModeTest extends EasyBreadcrumbBrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'easy_breadcrumb',
    'block',
    'node',
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
      'type' => 'page',
      'title' => 'Page With Long Title',
    ]);
    $this->easyBreadcrumbSetConfig(EasyBreadcrumbConstants::TRUNCATOR_LENGTH, 8);
  }

  /**
   * Tests the TRUNCATOR_MODE configuration.
   */
  public function testEasyBreadcrumbTruncatorMode() {
    $this->easyBreadcrumbSetConfig(EasyBreadcrumbConstants::TRUNCATOR_MODE, FALSE);
    $this->drupalGet($this->node->toUrl());
    $this->easyBreadcrumbAssertSegmentTextEquals(2, 'Page With Long Title');

    $this->easyBreadcrumbSetConfig(EasyBreadcrumbConstants::TRUNCATOR_MODE, TRUE);
    $this->easyBreadcrumbSetConfig(EasyBreadcrumbConstants::TRUNCATOR_DOTS, TRUE);
    $this->drupalGet($this->node->toUrl());
    $this->easyBreadcrumbAssertSegmentTextEquals(2, 'Page ...');

    $this->easyBreadcrumbSetConfig(EasyBreadcrumbConstants::TRUNCATOR_DOTS, FALSE);
    $this->drupalGet($this->node->toUrl());
    $this->easyBreadcrumbAssertSegmentTextEquals(2, 'Page Wit');
  }

}
