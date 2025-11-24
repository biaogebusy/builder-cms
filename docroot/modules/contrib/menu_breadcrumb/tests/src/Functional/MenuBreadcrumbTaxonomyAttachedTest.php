<?php

namespace Drupal\Tests\menu_breadcrumb\Functional;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Tests handling of breadcrumb generation.
 *
 * @coversDefaultClass \Drupal\menu_breadcrumb\MenuBasedBreadcrumbBuilder
 *
 * @group menu_breadcrumb
 */
class MenuBreadcrumbTaxonomyAttachedTest extends MenuBreadcrumbFunctionalTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'field',
    'filter',
    'menu_ui',
    'menu_breadcrumb',
    'node',
    'system',
    'taxonomy',
    'text',
    'user',
  ];

  /**
   * Tests the Menu Breadcrumb builder with taxonomy attachment.
   */
  public function testMenuBreadcrumbsWithTaxonomyAttachment() {
    // Add a field which will relate node to parent terms.
    $field_storage = FieldStorageConfig::create([
      'field_name' => $this->fieldId,
      'type' => 'entity_reference',
      'entity_type' => 'node',
      'cardinality' => 1,
      'settings' => [
        'target_type' => 'taxonomy_term',
      ],
    ]);
    $field_storage->save();
    $this->fieldConfig = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => $this->contentTypeId,
      'label' => 'Test Reference',
      'settings' => [
        'handler' => 'default',
      ],
    ]);
    $this->fieldConfig->save();

    // From module defaults, enable taxattach for $this->menu_id.
    $menu_settings = $this->config('menu_breadcrumb.settings')->get('menu_breadcrumb_menus');
    $menu_settings[$this->menuId]['taxattach'] = 1;
    $this->config('menu_breadcrumb.settings')->set('append_member_page', TRUE);
    $this->config('menu_breadcrumb.settings')->set('menu_breadcrumb_menus', $menu_settings);
    $this->config('menu_breadcrumb.settings')->save();
    // Create term/node content tree with menu items.
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
    $level2TermB = $this->termStorage->create([
      'name' => 'Level 2 Term B',
      'vid' => $this->vocabularyId,
      'parent' => $level0Term->id(),
    ]);
    $level2TermB->save();
    $this->createMenuLink([
      'title' => $level2TermB->get('name')->value,
      'uri' => 'internal:/taxonomy/term/' . $level2TermB->id(),
      'parent' => $level0MenuLink->getPluginId(),
    ]);
    $level2TermC = $this->termStorage->create([
      'name' => 'Level 2 Term C',
      'vid' => $this->vocabularyId,
      'parent' => $level1TermA->id(),
    ]);
    $level2TermC->save();
    $this->createMenuLink([
      'title' => $level2TermC->get('name')->value,
      'uri' => 'internal:/taxonomy/term/' . $level2TermC->id(),
      'parent' => $level1MenuLinkA->getPluginId(),
    ]);

    // No menu link for this node, it has a relation to the parent term.
    /** @var \Drupal\node\NodeInterface $level3Node */
    $level3Node = $this->nodeStorage->create([
      'title' => 'Level 3 Node 1',
      'type' => $this->contentTypeId,
      $this->fieldId => $level2TermC->id(),
    ]);
    $level3Node->save();

    // Taxonomy attachment breadcrumbs present without node menu item.
    $this->assertBreadcrumb('/node/' . $level3Node->id(), [
      $level0Term->toUrl()->toString() => $level0Term->get('name')->value,
      $level1TermA->toUrl()->toString() => $level1TermA->get('name')->value,
      $level2TermC->toUrl()->toString() => $level2TermC->get('name')->value,
    ]);
  }

}
