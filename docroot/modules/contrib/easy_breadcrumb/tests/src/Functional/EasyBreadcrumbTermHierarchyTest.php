<?php

declare(strict_types=1);

namespace Drupal\Tests\easy_breadcrumb\Functional;

use Drupal\easy_breadcrumb\EasyBreadcrumbConstants;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the TERM_HIERARCHY configuration.
 */
#[Group('easy_breadcrumb')]
class EasyBreadcrumbTermHierarchyTest extends EasyBreadcrumbBrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'easy_breadcrumb',
    'block',
    'taxonomy',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $vocabularyId = 'tags';
    Vocabulary::create(['vid' => $vocabularyId])->save();

    $parent = Term::create([
      'name' => 'Parent',
      'vid' => $vocabularyId,
    ]);
    $parent->save();

    $child = Term::create([
      'name' => 'Child',
      'vid' => $vocabularyId,
      'parent' => [$parent->id()],
    ]);
    $child->save();

    $grandchild = Term::create([
      'name' => 'Grandchild',
      'vid' => $vocabularyId,
      'parent' => [$child->id()],
    ]);
    $grandchild->save();

    $this->easyBreadcrumbSetConfig(EasyBreadcrumbConstants::TERM_HIERARCHY, TRUE);
    $this->drupalGet($grandchild->toUrl());
  }

  /**
   * Tests the TERM_HIERARCHY configuration.
   */
  public function testEasyBreadcrumbTermHierarchy() {
    $this->easyBreadcrumbAssertSegmentTextEquals(2, 'Parent');
    $this->easyBreadcrumbAssertSegmentTextEquals(3, 'Child');
    $this->easyBreadcrumbAssertSegmentTextEquals(4, 'Grandchild');
  }

}
