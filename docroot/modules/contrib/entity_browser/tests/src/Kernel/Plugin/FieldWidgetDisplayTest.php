<?php

namespace Drupal\Tests\entity_browser\Kernel\Plugin;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests field widget display plugins.
 *
 * @group entity_browser
 */
#[Group('entity_browser')]
#[RunTestsInSeparateProcesses]
class FieldWidgetDisplayTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'entity_browser', 'image', 'node', 'user',
  ];

  /**
   * Field widget display plugin manager.
   *
   * @var \Drupal\entity_browser\FieldWidgetDisplayManager
   */
  protected $pluginManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->pluginManager = $this->container->get('plugin.manager.entity_browser.field_widget_display');

    $image_style = $this->container->get('entity_type.manager')->getStorage('image_style');
    $image_style->create(['name' => 'thumbnail', 'label' => 'Thumbnail'])->save();
    $image_style->create(['name' => 'large', 'label' => 'Large'])->save();

    $this->installEntitySchema('node');
    $this->installEntitySchema('user');

    // Ensure the DRUPAL_OPTIONAL constant exists. It is referenced by
    // NodeType on older Drupal versions but may not be loaded in kernel tests.
    if (!defined('DRUPAL_OPTIONAL')) {
      define('DRUPAL_OPTIONAL', 1);
    }

    $node_type = $this->container->get('entity_type.manager')->getStorage('node_type');
    $node_type->create(['type' => 'article', 'name' => 'Article'])->save();

    $view_mode = $this->container->get('entity_type.manager')->getStorage('entity_view_mode');
    $view_mode->create(['id' => 'node.full', 'targetEntityType' => 'node'])->save();
  }

  /**
   * Test field widget display plugins configuration and dependencies.
   */
  public function testDefaultConfiguration() {
    // Check default configuration for image thumbnail plugin.
    $image_thumbnail_plugin = $this->pluginManager->createInstance('thumbnail');
    $this->assertEquals(['image_style' => 'thumbnail'], $image_thumbnail_plugin->defaultConfiguration());
    $this->assertEquals(['image_style' => 'thumbnail'], $image_thumbnail_plugin->getConfiguration());
    $this->assertEquals(['config' => [0 => 'image.style.thumbnail']], $image_thumbnail_plugin->calculateDependencies());
    // Set configuration different then default.
    $image_thumbnail_plugin->setConfiguration(['image_style' => 'large']);
    $this->assertEquals(['image_style' => 'thumbnail'], $image_thumbnail_plugin->defaultConfiguration());
    $this->assertEquals(['image_style' => 'large'], $image_thumbnail_plugin->getConfiguration());
    $this->assertEquals(['config' => [0 => 'image.style.large']], $image_thumbnail_plugin->calculateDependencies());

    // Check default configuration for rendered entity plugin.
    $rendered_entity_plugin = $this->pluginManager->createInstance('rendered_entity', ['entity_type' => 'node']);
    $this->assertEquals(['view_mode' => 'default'], $rendered_entity_plugin->defaultConfiguration());
    $this->assertEquals(['view_mode' => 'default', 'entity_type' => 'node'], $rendered_entity_plugin->getConfiguration());
    $this->assertEquals([], $rendered_entity_plugin->calculateDependencies());
    // Set configuration different then default.
    $rendered_entity_plugin->setConfiguration(['entity_type' => 'node', 'view_mode' => 'full']);
    $this->assertEquals(['view_mode' => 'default'], $rendered_entity_plugin->defaultConfiguration());
    $this->assertEquals(['view_mode' => 'full', 'entity_type' => 'node'], $rendered_entity_plugin->getConfiguration());
    $this->assertEquals(['config' => [0 => 'core.entity_view_mode.node.full']], $rendered_entity_plugin->calculateDependencies());
  }

}
