<?php

namespace Drupal\Tests\menu_breadcrumb\Functional;

use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\system\Functional\Menu\AssertBreadcrumbTrait;

/**
 * Tests handling of breadcrumb generation.
 *
 * @coversDefaultClass \Drupal\menu_breadcrumb\MenuBasedBreadcrumbBuilder
 *
 * @group menu_breadcrumb
 */
class MenuBreadcrumbMiscTest extends MenuBreadcrumbFunctionalTestBase {

  use AssertBreadcrumbTrait;
  use ContentTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'field',
    'menu_link_content',
    'menu_breadcrumb',
    'node',
    'system',
    'taxonomy',
    'user',
  ];

  /**
   * Test behavior of setting to link current page.
   */
  public function testCurrentPageAsLink() {
    $level0Term = $this->termStorage->create([
      'name' => 'Level 0 Term 0',
      'vid' => $this->vocabularyId,
    ]);
    $level0Term->save();
    $level0MenuLink = $this->createMenuLink([
      'title' => $level0Term->get('name')->value,
      'uri' => 'internal:/taxonomy/term/' . $level0Term->id(),
    ]);
    $level1TermA = $this->termStorage->create([
      'name' => 'Level 1 Term A',
      'vid' => $this->vocabularyId,
      'parent' => $level0Term->id(),
    ]);
    $level1TermA->save();
    $level1MenuLinkA = $this->createMenuLink([
      'title' => $level1TermA->get('name')->value,
      'uri' => 'internal:/taxonomy/term/' . $level1TermA->id(),
      'parent' => $level0MenuLink->getPluginId(),
    ]);
    /** @var \Drupal\node\NodeInterface $level3Node */
    $level2Node = $this->nodeStorage->create([
      'title' => 'Level 2 Node',
      'type' => $this->contentTypeId,
    ]);
    $level2Node->save();
    $this->createMenuLink([
      'title' => $level2Node->get('title')->value,
      'uri' => 'internal:/node/' . $level2Node->id(),
      'parent' => $level1MenuLinkA->getPluginId(),
    ]);

    // Test breadcrumbs with current page linked.
    $this->config('menu_breadcrumb.settings')
      ->set('current_page_as_link', TRUE)
      ->save();
    // Node will appear in the breadcrumb links.
    $this->assertBreadcrumb('/node/' . $level2Node->id(), [
      $level0Term->toUrl()->toString() => $level0Term->get('name')->value,
      $level1TermA->toUrl()->toString() => $level1TermA->get('name')->value,
      $level2Node->toUrl()->toString() => $level2Node->get('title')->value,
    ]);

    // Test breadcrumbs with current page unlinked.
    $this->config('menu_breadcrumb.settings')
      ->set('current_page_as_link', FALSE)
      ->save();
    // Node will not appear in the breadcrumb links.
    $this->assertBreadcrumb('/node/' . $level2Node->id(), [
      $level0Term->toUrl()->toString() => $level0Term->get('name')->value,
      $level1TermA->toUrl()->toString() => $level1TermA->get('name')->value,
    ]);
  }

}
