<?php

namespace Drupal\Tests\layout_library\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\FieldConfigInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\layout_builder\Entity\LayoutEntityDisplayInterface;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;

/**
 * Tests the internal handling of the layout_selection field.
 *
 * @group layout_library
 */
class LayoutSelectionFieldTest extends KernelTestBase {

  use ContentTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'layout_builder',
    'layout_discovery',
    'layout_library',
    'node',
    'system',
    'text',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig('node');
    $this->createContentType(['type' => 'test']);
  }

  /**
   * Tests that the layout_selection persists if multiple displays are using it.
   */
  public function testFieldPersistsForMultipleDisplays() {
    /** @var \Drupal\layout_builder\Entity\LayoutEntityDisplayInterface $full_display */
    $full_display = \Drupal::service('entity_display.repository')->getViewDisplay('node', 'test', 'full');
    $this->assertInstanceOf(LayoutEntityDisplayInterface::class, $full_display);
    $full_display->enableLayoutBuilder()
      ->setThirdPartySetting('layout_library', 'enable', TRUE)
      ->save();
    $this->assertFieldExists();

    /** @var \Drupal\layout_builder\Entity\LayoutEntityDisplayInterface $teaser_display */
    $teaser_display = \Drupal::service('entity_display.repository')->getViewDisplay('node', 'test', 'teaser');
    $this->assertInstanceOf(LayoutEntityDisplayInterface::class, $teaser_display);
    $teaser_display->enableLayoutBuilder()
      ->setThirdPartySetting('layout_library', 'enable', TRUE)
      ->save();
    $this->assertFieldExists();

    $teaser_display->setThirdPartySetting('layout_library', 'enable', FALSE)
      ->save();
    $this->assertFieldExists();
  }

  /**
   * Asserts that the layout_selection field exists on the test node type.
   */
  private function assertFieldExists() {
    $field = FieldConfig::loadByName('node', 'test', 'layout_selection');
    $this->assertInstanceOf(FieldConfigInterface::class, $field);
  }

}
