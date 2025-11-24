<?php

namespace Drupal\Tests\core_context\Functional;

use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\layout_library\Entity\Layout;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests integration with Layout Library.
 *
 * @group core_context
 *
 * @requires module layout_library
 */
class LayoutLibraryIntegrationTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'core_context',
    'field_ui',
    'layout_library',
    'node',
  ];

  /**
   * Data provider for ::test().
   *
   * @return array[]
   *   Sets of arguments to pass to the test method.
   */
  public function provider(): array {
    return [
      'library layouts enabled' => [
        TRUE,
      ],
      'library layouts disabled' => [
        FALSE,
      ],
    ];
  }

  /**
   * Tests that layout entities can be edited with Core Context enabled.
   *
   * @param bool $layout_library_enabled
   *   Whether or not library layouts are enabled for the node type under test.
   *
   * @dataProvider provider
   */
  public function test(bool $layout_library_enabled): void {
    $this->drupalCreateContentType(['type' => 'page']);

    $component = SectionComponent::fromArray([
      'uuid' => $this->container->get('uuid')->generate(),
      'region' => 'content',
      'configuration' => [
        'id' => 'system_powered_by_block',
      ],
      'additional' => [],
      'weight' => 0,
    ]);
    $section = new Section('layout_onecol');
    $section->appendComponent($component);

    $layout = Layout::create([
      'id' => 'test',
      'targetEntityType' => 'node',
      'targetBundle' => 'page',
      'label' => 'Test',
    ]);
    $layout->appendSection($section)->save();

    // We should be able to edit the layout regardless of whether library
    // layouts are enabled for this node type.
    $this->container->get('entity_display.repository')
      ->getViewDisplay('node', 'page')
      ->setThirdPartySetting('layout_library', 'enable', $layout_library_enabled)
      ->save();

    $this->drupalLogin($this->rootUser);
    $this->drupalGet($layout->toUrl());
    $this->assertSession()->statusCodeEquals(200);
  }

}
